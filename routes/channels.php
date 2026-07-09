<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Modules\Workspace\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('team', function (User $user) {
    if ($user->status === 'offline') {
        $user->update(['status' => 'available']);
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role->value,
        'status' => $user->status,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('conversations.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
        ->exists();
}, ['guards' => ['sanctum']]);
