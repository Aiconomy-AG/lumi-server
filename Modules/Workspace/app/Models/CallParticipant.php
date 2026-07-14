<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Support\LiveKitIdentity;

class CallParticipant extends Model
{
    protected $fillable = [
        'call_id', 'user_id', 'role', 'status',
        'invited_at', 'ringing_delivered_at', 'joined_at', 'left_at',
        'livekit_identity', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
            'invited_at' => 'datetime',
            'ringing_delivered_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [ParticipantStatus::Invited, ParticipantStatus::Ringing], true);
    }

    public function livekitIdentity(): string
    {
        if ($this->livekit_identity) {
            return $this->livekit_identity;
        }

        return LiveKitIdentity::forUser((int) $this->user_id, (string) $this->user_id);
    }
}
