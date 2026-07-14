<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Workspace\Domain\Calls\CallStatus;

class Call extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'conversation_id', 'initiated_by_user_id', 'caller_name', 'caller_phone_number',
        'media_type', 'status', 'room_name', 'answered_client_instance_id',
        'ended_by_user_id', 'end_reason', 'answered_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallStatus::class,
            'answered_at' => 'datetime',
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
}
