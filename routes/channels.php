<?php

use App\Models\Group;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Di sini kita otorisasi siapa yang boleh subscribe ke channel tertentu.
*/

// Private chat: hanya dua user yang terlibat boleh join
// Format channel: private-chat.{id1}-{id2}  (id kecil selalu duluan)
Broadcast::channel('chat.{ids}', function ($user, $ids) {
    [$id1, $id2] = explode('-', $ids);
    return (int) $user->id === (int) $id1 || (int) $user->id === (int) $id2;
});

// Presence channel untuk group chat — untuk tracking siapa yang online
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    $group = Group::find($groupId);
    if (!$group) return false;

    $isMember = $group->members()->where('user_id', $user->id)->exists();
    if (!$isMember) return false;

    return [
        'id'        => $user->id,
        'name'      => $user->name,
        'initials'  => $user->initials,
        'is_online' => true,
    ];
});

// Public channel untuk presence global (online/offline indicator di sidebar)
Broadcast::channel('presence', function ($user) {
    return true; // semua user yang login boleh subscribe
});
