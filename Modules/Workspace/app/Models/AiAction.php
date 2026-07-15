<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workspace\Enums\AiActionStatus;

class AiAction extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'requested_by_user_id',
        'tool_name',
        'arguments',
        'summary',
        'status',
        'expires_at',
        'executed_at',
        'result',
        'error',
    ];

    protected $casts = [
        'arguments' => 'array',
        'result' => 'array',
        'status' => AiActionStatus::class,
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === AiActionStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
