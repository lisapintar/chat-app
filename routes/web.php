<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// ─── Auth routes ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [LoginController::class,    'showLogin'])->name('login');
    Route::post('/login',   [LoginController::class,    'login']);
    Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
    Route::post('/register',[RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])
     ->middleware('auth')
     ->name('logout');

// ─── Chat routes (harus login) ───────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');

    // AJAX endpoints
    Route::get('/messages/private/{user}',  [ChatController::class, 'getPrivateMessages'])->name('chat.private');
    Route::get('/messages/group/{group}',   [ChatController::class, 'getGroupMessages'])->name('chat.group');
    Route::post('/messages/send',           [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/typing',                  [ChatController::class, 'typing'])->name('chat.typing');
    Route::post('/groups/create',           [ChatController::class, 'createGroup'])->name('group.create');
    Route::get('/unread-counts',            [ChatController::class, 'unreadCounts'])->name('chat.unread');

    // Presence endpoints
    Route::post('/set-offline',  [ChatController::class, 'setOffline'])->name('chat.offline');
    Route::post('/heartbeat',    [ChatController::class, 'heartbeat'])->name('chat.heartbeat');
});
