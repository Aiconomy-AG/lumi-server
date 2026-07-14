<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;
use Modules\Workspace\Domain\Calls\CallMode;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\CallType;
use Modules\Workspace\Domain\Calls\ParticipantStatus;

class Call extends Model
{
    use HasUuids;

    public const DESTINATION_WORKSPACE_USER = 'workspace_user';

    protected $fillable = [
        'id', 'conversation_id', 'initiated_by_user_id', 'caller_name', 'destination_type',
        'mode', 'type', 'media_type', 'status', 'room_name', 'answered_client_instance_id',
        'ended_by_user_id', 'end_reason', 'answered_at', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallStatus::class,
            'mode' => CallMode::class,
            'type' => CallType::class,
            'answered_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallEvent::class);
    }

    public function isGroup(): bool
    {
        return $this->mode === CallMode::Group;
    }

    public function isVideo(): bool
    {
        return $this->type === CallType::Video;
    }

    public function activeParticipants(): Collection
    {
        return $this->participants
            ->filter(fn (CallParticipant $participant) => $participant->status === ParticipantStatus::Joined);
    }

    public function callTypeValue(): string
    {
        return $this->type?->value ?? $this->media_type ?? CallType::Audio->value;
    }

    public function callModeValue(): string
    {
        return $this->mode?->value ?? CallMode::OneToOne->value;
    }
}
