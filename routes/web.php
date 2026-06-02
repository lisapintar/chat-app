<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/chat');
    }
    return redirect('/login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/api/users', [ChatController::class, 'getUsers']);
    Route::get('/api/messages/{userId}', [ChatController::class, 'getMessages']);
    Route::post('/api/send-message', [ChatController::class, 'sendMessage']);
    Route::post('/api/update-presence', [ChatController::class, 'updatePresence']);
    Route::get('/api/online-users', [ChatController::class, 'getOnlineUsers']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');

    // Private chat
    Route::get('/api/users', [ChatController::class, 'getUsers']);
    Route::get('/api/messages/{userId}', [ChatController::class, 'getMessages']);
    Route::post('/api/send-message', [ChatController::class, 'sendMessage']);
    Route::post('/api/update-presence', [ChatController::class, 'updatePresence']);
    Route::get('/api/online-users', [ChatController::class, 'getOnlineUsers']);

    // Group chat
    Route::get('/api/groups', [ChatController::class, 'getGroups']);
    Route::post('/api/groups', [ChatController::class, 'createGroup']);
    Route::get('/api/groups/{groupId}/messages', [ChatController::class, 'getGroupMessages']);
    Route::post('/api/groups/send-message', [ChatController::class, 'sendGroupMessage']);
});

require __DIR__.'/auth.php';