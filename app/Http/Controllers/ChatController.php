<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\UserPresenceUpdated;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use App\Events\GroupMessageSent;

class ChatController extends Controller
{
    // Tampilkan halaman chat
    public function index()
    {
        // Set user sebagai online
        $user = Auth::user();
        $user->update(['is_online' => true, 'last_seen' => now()]);
        broadcast(new UserPresenceUpdated($user->id, $user->name, true));

        return view('chat');
    }

    // Ambil semua user kecuali yang sedang login
    public function getUsers()
    {
        $users = User::where('id', '!=', Auth::id())
            ->select('id', 'name', 'email', 'is_online', 'last_seen')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_online' => $user->is_online,
                    'last_seen' => $user->last_seen,
                    'avatar' => strtoupper(substr($user->name, 0, 1)),
                ];
            });

        return response()->json($users);
    }

    // Ambil riwayat pesan antara 2 user
    public function getMessages($userId)
    {
        $currentUserId = Auth::id();

        $messages = Message::where(function ($query) use ($currentUserId, $userId) {
                $query->where('sender_id', $currentUserId)
                      ->where('receiver_id', $userId);
            })
            ->orWhere(function ($query) use ($currentUserId, $userId) {
                $query->where('sender_id', $userId)
                      ->where('receiver_id', $currentUserId);
            })
            ->where('type', 'private')
            ->with('sender:id,name')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'sender_id' => $msg->sender_id,
                    'receiver_id' => $msg->receiver_id,
                    'sender_name' => $msg->sender->name,
                    'created_at' => $msg->created_at->toISOString(),
                    'is_mine' => $msg->sender_id === Auth::id(),
                ];
            });

        return response()->json($messages);
    }

    // Kirim pesan
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'content' => 'required|string|max:1000',
        ]);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'content' => $request->content,
            'type' => 'private',
        ]);

        $message->load('sender');

        // Broadcast event via WebSocket
        broadcast(new MessageSent($message, Auth::user()))->toOthers();

        return response()->json([
            'id' => $message->id,
            'content' => $message->content,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'sender_name' => $message->sender->name,
            'created_at' => $message->created_at->toISOString(),
            'is_mine' => true,
        ]);
    }

    // Update status online/offline
    public function updatePresence(Request $request)
    {
        $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $user = Auth::user();
        $user->update([
            'is_online' => $request->is_online,
            'last_seen' => now(),
        ]);

        broadcast(new UserPresenceUpdated($user->id, $user->name, $request->is_online));

        return response()->json(['success' => true]);
    }

    // Ambil daftar user yang sedang online
    public function getOnlineUsers()
    {
        $users = User::where('is_online', true)
            ->where('id', '!=', Auth::id())
            ->pluck('id');

        return response()->json($users);
    }

    // Ambil semua group milik user yang login
    public function getGroups()
    {
        $groups = Auth::user()->groups()
         ->with(['members:id,name', 'creator:id,name'])
            ->get()
            ->map(function ($group) {
             return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'created_by' => $group->created_by,
                'creator_name' => $group->creator->name,
                'member_count' => $group->members->count(),
                'members' => $group->members->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'avatar' => strtoupper(substr($m->name, 0, 1)),
                ]),
                'avatar' => strtoupper(substr($group->name, 0, 1)),
            ];
        });

    return response()->json($groups);
    }

    // Buat group baru
    public function createGroup(Request $request)
    {
     $request->validate([
        'name' => 'required|string|max:100',
        'member_ids' => 'required|array|min:1',
        'member_ids.*' => 'exists:users,id',
    ]);

    $group = Group::create([
        'name' => $request->name,
        'description' => $request->description ?? null,
        'created_by' => Auth::id(),
    ]);

    // Tambah creator sebagai admin
    $group->members()->attach(Auth::id(), ['role' => 'admin']);

    // Tambah member lain
    foreach ($request->member_ids as $memberId) {
        if ($memberId != Auth::id()) {
            $group->members()->attach($memberId, ['role' => 'member']);
        }
    }

    return response()->json([
        'id' => $group->id,
        'name' => $group->name,
        'member_count' => count($request->member_ids) + 1,
        'avatar' => strtoupper(substr($group->name, 0, 1)),
    ]);
    }

    // Ambil pesan group
    public function getGroupMessages($groupId)
    {
    // Pastikan user adalah member group
    $isMember = Auth::user()->groups()->where('groups.id', $groupId)->exists();
    if (!$isMember) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $messages = Message::where('group_id', $groupId)
        ->where('type', 'group')
        ->with('sender:id,name')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function ($msg) {
            return [
                'id' => $msg->id,
                'content' => $msg->content,
                'sender_id' => $msg->sender_id,
                'group_id' => $msg->group_id,
                'sender_name' => $msg->sender->name,
                'created_at' => $msg->created_at->toISOString(),
                'is_mine' => $msg->sender_id === Auth::id(),
            ];
        });

    return response()->json($messages);
    }

    // Kirim pesan ke group
    public function sendGroupMessage(Request $request)
    {
    $request->validate([
        'group_id' => 'required|exists:groups,id',
        'content' => 'required|string|max:1000',
    ]);

    // Pastikan user adalah member
    $isMember = Auth::user()->groups()->where('groups.id', $request->group_id)->exists();
    if (!$isMember) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $message = Message::create([
        'sender_id' => Auth::id(),
        'group_id' => $request->group_id,
        'content' => $request->content,
        'type' => 'group',
    ]);

    $message->load('sender');

    broadcast(new GroupMessageSent($message, Auth::user()))->toOthers();

    return response()->json([
        'id' => $message->id,
        'content' => $message->content,
        'sender_id' => $message->sender_id,
        'group_id' => $message->group_id,
        'sender_name' => $message->sender->name,
        'created_at' => $message->created_at->toISOString(),
        'is_mine' => true,
    ]);
    }

}