<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'group_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // Pengirim pesan
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Penerima (private chat)
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // Group (group chat)
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    // Cek apakah ini pesan group
    public function isGroupMessage(): bool
    {
        return $this->group_id !== null;
    }
}
