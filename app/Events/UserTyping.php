<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $senderId,
        public string $senderName,
        public ?int $receiverId,
        public ?int $groupId,
        public bool $isTyping
    ) {}

    public function broadcastOn(): array
    {
        if ($this->groupId) {
            return [new PresenceChannel('group.' . $this->groupId)];
        }

        $ids = [$this->senderId, $this->receiverId];
        sort($ids);

        return [new PrivateChannel('chat.' . implode('-', $ids))];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id'   => $this->senderId,
            'sender_name' => $this->senderName,
            'is_typing'   => $this->isTyping,
        ];
    }
}
