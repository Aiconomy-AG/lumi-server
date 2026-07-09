<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('users.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
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
