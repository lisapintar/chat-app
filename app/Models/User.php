<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_online',
        'last_seen_at',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_online'         => 'boolean',
            'last_seen_at'      => 'datetime',
        ];
    }

    // Pesan yang dikirim user ini
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // Pesan yang diterima user ini (private)
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    // Group yang dibuat user ini
    public function createdGroups()
    {
        return $this->hasMany(Group::class, 'created_by');
    }

    // Group yang diikuti user ini
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    // Avatar initials untuk tampilan
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }
}
