<?php

use App\Events\UserPresenceChanged;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Setiap 2 menit: set offline user yang heartbeat-nya sudah lama ───────────
// Ini menangani kasus browser crash / koneksi putus tanpa beforeunload
Schedule::call(function () {
    $staleUsers = User::where('is_online', true)
        ->where('last_seen_at', '<', now()->subMinutes(2))
        ->get();

    foreach ($staleUsers as $user) {
        $user->update(['is_online' => false]);
        broadcast(new UserPresenceChanged($user, 'offline'));
    }
})->everyTwoMinutes()->name('mark-stale-users-offline');
