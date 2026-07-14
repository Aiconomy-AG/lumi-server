<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workspace\Domain\Calls\ParticipantStatus;

class CallParticipant extends Model
{
    protected $fillable = ['call_id', 'user_id', 'role', 'status', 'answered_at', 'left_at'];

    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
            'answered_at' => 'datetime',
            'left_at' => 'datetime',
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
}
