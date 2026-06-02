<?php

use Illuminate\Support\Facades\Broadcast;

// Private channel untuk chat antara 2 user
// Format: chat.{smallerId}.{largerId}
Broadcast::channel('chat.{id1}.{id2}', function ($user, $id1, $id2) {
    return (int) $user->id === (int) $id1 || (int) $user->id === (int) $id2;
});

// Public channel untuk presence tracking
Broadcast::channel('presence', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// Channel public untuk group chat
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    return $user->groups()->where('groups.id', $groupId)->exists();
});