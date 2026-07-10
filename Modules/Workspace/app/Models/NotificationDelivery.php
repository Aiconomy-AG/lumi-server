<?php

namespace Modules\Workspace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_event_id',
        'recipient_user_id',
        'read_at',
        'seen_at',
        'dismissed_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'seen_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
