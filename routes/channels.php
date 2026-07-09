<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('presence-team', function ($user) {
    if ($user->status === 'offline') {
        $user->update(['status' => 'available']);
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
        'status' => $user->status,
    ];
});
