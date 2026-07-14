<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Workspace\Database\Factories\MessageFactory;
use Modules\Workspace\Domain\Messages\MessageType;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message_type',
        'call_id',
        'message',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected function casts(): array
    {
        return [
            'message_type' => MessageType::class,
        ];
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class)
            ->orderBy('emoji')
            ->orderBy('user_id');
    }

    public function isCallLog(): bool
    {
        return $this->message_type === MessageType::Call;
    }

    public function isSystem(): bool
    {
        return $this->message_type === MessageType::System;
    }
}
