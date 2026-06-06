<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    // User yang membuat group
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Semua anggota group
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    // Pesan di group ini
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // Jumlah anggota online
    public function getOnlineMembersCountAttribute(): int
    {
        return $this->members()->where('is_online', true)->count();
    }
}
