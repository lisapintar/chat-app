<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $status // 'online' atau 'offline'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('presence')];
    }

    public function broadcastAs(): string
    {
        return 'user.presence';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'  => $this->user->id,
            'name'     => $this->user->name,
            'initials' => $this->user->initials,
            'status'   => $this->status,
        ];
    }
}
