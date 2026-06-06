<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        // Load relasi sender supaya tersedia di broadcastWith
        $this->message = $message->load('sender');
    }

    /**
     * Channel tempat event ini di-broadcast.
     * - Private chat  → private-chat.{userId1}-{userId2}  (ID kecil selalu duluan)
     * - Group chat    → presence-group.{groupId}
     */
    public function broadcastOn(): array
    {
        if ($this->message->group_id) {
            // Gunakan PrivateChannel untuk broadcast pesan group
            // lebih reliable daripada PresenceChannel
            return [new PrivateChannel('group.' . $this->message->group_id)];
        }

        // Urutkan ID supaya nama channel konsisten (misal: chat.1-3, bukan chat.3-1)
        $ids = [$this->message->sender_id, $this->message->receiver_id];
        sort($ids);

        return [new PrivateChannel('chat.' . implode('-', $ids))];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->message->id,
            'body'        => $this->message->body,
            'sender_id'   => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'group_id'    => $this->message->group_id,
            'created_at'  => $this->message->created_at->format('H:i'),
            'sender'      => [
                'id'       => $this->message->sender->id,
                'name'     => $this->message->sender->name,
                'initials' => $this->message->sender->initials,
            ],
        ];
    }
}
