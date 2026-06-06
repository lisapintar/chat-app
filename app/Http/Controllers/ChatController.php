<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Halaman utama chat — tampilkan semua user & group
     */
    public function index()
    {
        $currentUser = Auth::user();

        // Semua user kecuali diri sendiri
        $users = User::where('id', '!=', $currentUser->id)
                     ->orderByDesc('is_online')
                     ->orderBy('name')
                     ->get();

        // Group yang diikuti user ini
        $groups = $currentUser->groups()->withCount('members')->get();

        return view('chat.index', compact('currentUser', 'users', 'groups'));
    }

    /**
     * Ambil pesan private antara dua user (AJAX)
     */
    public function getPrivateMessages(Request $request, int $userId)
    {
        $currentUser = Auth::id();
        $otherUser   = User::findOrFail($userId);

        $messages = Message::with('sender')
            ->whereNull('group_id')
            ->where(function ($q) use ($currentUser, $userId) {
                $q->where('sender_id', $currentUser)->where('receiver_id', $userId);
            })
            ->orWhere(function ($q) use ($currentUser, $userId) {
                $q->where('sender_id', $userId)->where('receiver_id', $currentUser)
                  ->whereNull('group_id');
            })
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => $this->formatMessage($m));

        // Tandai pesan yang belum dibaca sebagai sudah dibaca
        Message::where('sender_id', $userId)
               ->where('receiver_id', $currentUser)
               ->whereNull('read_at')
               ->update(['read_at' => now()]);

        return response()->json([
            'messages'   => $messages,
            'other_user' => [
                'id'        => $otherUser->id,
                'name'      => $otherUser->name,
                'initials'  => $otherUser->initials,
                'is_online' => $otherUser->is_online,
            ],
        ]);
    }

    /**
     * Ambil pesan group (AJAX)
     */
    public function getGroupMessages(Request $request, int $groupId)
    {
        $group = Group::with(['members' => function ($q) {
            $q->orderByDesc('is_online')->orderBy('name');
        }])->findOrFail($groupId);

        // Pastikan user adalah anggota group
        if (!$group->members()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Kamu bukan anggota group ini.'], 403);
        }

        $messages = Message::with('sender')
            ->where('group_id', $groupId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => $this->formatMessage($m));

        return response()->json([
            'messages' => $messages,
            'group'    => [
                'id'             => $group->id,
                'name'           => $group->name,
                'members_count'  => $group->members->count(),
                'online_count'   => $group->members->where('is_online', true)->count(),
                'members'        => $group->members->map(fn($u) => [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'initials'  => $u->initials,
                    'is_online' => $u->is_online,
                ]),
            ],
        ]);
    }

    /**
     * Kirim pesan (AJAX)
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'body'        => ['required', 'string', 'max:5000'],
            'receiver_id' => ['nullable', 'integer', 'exists:users,id'],
            'group_id'    => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        // Harus ada salah satu
        if (!$request->receiver_id && !$request->group_id) {
            return response()->json(['error' => 'Tujuan pesan tidak valid.'], 422);
        }

        // Kalau group, pastikan user adalah anggota
        if ($request->group_id) {
            $group = Group::findOrFail($request->group_id);
            $isMember = $group->members()->where('user_id', Auth::id())->exists();
            if (!$isMember) {
                return response()->json(['error' => 'Kamu bukan anggota group ini.'], 403);
            }
        }

        $message = Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'group_id'    => $request->group_id,
            'body'        => $request->body,
        ]);

        // Broadcast event — ShouldBroadcastNow artinya langsung, tanpa queue
        broadcast(new MessageSent($message));

        return response()->json($this->formatMessage($message->load('sender')));
    }

    /**
     * Broadcast typing indicator (AJAX)
     */
    public function typing(Request $request)
    {
        $request->validate([
            'receiver_id' => ['nullable', 'integer'],
            'group_id'    => ['nullable', 'integer'],
            'is_typing'   => ['required', 'boolean'],
        ]);

        broadcast(new UserTyping(
            senderId:    Auth::id(),
            senderName:  Auth::user()->name,
            receiverId:  $request->receiver_id,
            groupId:     $request->group_id,
            isTyping:    $request->boolean('is_typing')
        ));

        return response()->json(['ok' => true]);
    }

    /**
     * Buat group baru (AJAX)
     */
    public function createGroup(Request $request)
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'member_ids'  => ['required', 'array', 'min:1'],
            'member_ids.*'=> ['integer', 'exists:users,id'],
        ]);

        $group = Group::create([
            'name'       => $request->name,
            'created_by' => Auth::id(),
        ]);

        // Tambah creator sebagai admin
        $group->members()->attach(Auth::id(), ['role' => 'admin']);

        // Tambah anggota lain sebagai member
        foreach ($request->member_ids as $memberId) {
            if ($memberId != Auth::id()) {
                $group->members()->attach($memberId, ['role' => 'member']);
            }
        }

        return response()->json([
            'id'   => $group->id,
            'name' => $group->name,
            'members_count' => $group->members()->count(),
        ]);
    }

    /**
     * Set user offline — dipanggil saat browser ditutup via sendBeacon
     */
    public function setOffline(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->update(['is_online' => false, 'last_seen_at' => now()]);
            broadcast(new \App\Events\UserPresenceChanged($user, 'offline'));
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Heartbeat — update last_seen_at setiap 30 detik
     * Jika last_seen_at lebih dari 2 menit yang lalu, user dianggap offline
     */
    public function heartbeat(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $wasOffline = !$user->is_online;
            $user->update(['is_online' => true, 'last_seen_at' => now()]);

            // Broadcast online jika sebelumnya offline (misal reconnect)
            if ($wasOffline) {
                broadcast(new \App\Events\UserPresenceChanged($user, 'online'));
            }
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Hitung unread messages untuk sidebar badge
     */
    public function unreadCounts()
    {
        $userId = Auth::id();

        // Unread per private conversation
        $privateUnread = Message::whereNull('group_id')
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) as count')
            ->groupBy('sender_id')
            ->pluck('count', 'sender_id');

        return response()->json(['private' => $privateUnread]);
    }

    // Helper format pesan untuk response JSON
    private function formatMessage(Message $m): array
    {
        return [
            'id'          => $m->id,
            'body'        => $m->body,
            'sender_id'   => $m->sender_id,
            'receiver_id' => $m->receiver_id,
            'group_id'    => $m->group_id,
            'created_at'  => $m->created_at->format('H:i'),
            'sender'      => [
                'id'       => $m->sender->id,
                'name'     => $m->sender->name,
                'initials' => $m->sender->initials,
            ],
        ];
    }
}
