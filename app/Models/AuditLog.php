<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'module',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'actor_user_id',
        'actor_name',
        'description',
        'changes',
        'occurred_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Record an audit entry, resolving the actor from the authenticated user.
     *
     * @param array{old?: array<string, mixed>, new?: array<string, mixed>}|null $changes
     */
    public static function record(
        string $module,
        string $action,
        Model $entity,
        ?string $label = null,
        ?array $changes = null,
        ?string $description = null,
    ): self {
        $user = auth()->user();

        return self::create([
            'module' => $module,
            'action' => $action,
            'entity_type' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'entity_label' => $label,
            'actor_user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'System/Automated',
            'description' => $description,
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }
}
