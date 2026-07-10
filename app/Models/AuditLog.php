<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    private const EXCLUDED_CHANGE_FIELDS = [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
    ];

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
     * Build old/new change arrays from a model's dirty attributes.
     *
     * @param  array<int, string>  $except
     * @return array{old: array<string, mixed>, new: array<string, mixed>}|null
     */
    public static function diffChanges(Model $model, array $except = []): ?array
    {
        $except = array_merge(self::EXCLUDED_CHANGE_FIELDS, $except);

        $dirty = collect($model->getChanges())->except($except);

        if ($dirty->isEmpty()) {
            return null;
        }

        $old = [];
        $new = [];

        foreach ($dirty as $key => $value) {
            $old[$key] = $model->getOriginal($key);
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Record an audit entry for a model entity.
     *
     * @param  array{old?: array<string, mixed>, new?: array<string, mixed>}|null  $changes
     */
    public static function record(
        string $module,
        string $action,
        Model $entity,
        ?string $label = null,
        ?array $changes = null,
        ?string $description = null,
        ?User $actor = null,
        ?string $actorName = null,
    ): self {
        $authUser = auth()->user();
        $resolvedActor = $actor ?? ($authUser instanceof User ? $authUser : null);

        return self::create([
            'module' => $module,
            'action' => $action,
            'entity_type' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'entity_label' => $label,
            'actor_user_id' => $resolvedActor?->id,
            'actor_name' => $actorName ?? $resolvedActor?->name ?? 'System/Automated',
            'description' => $description,
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Record an audit entry without a backing Eloquent model (e.g. batch imports).
     *
     * @param  array{old?: array<string, mixed>, new?: array<string, mixed>}|null  $changes
     */
    public static function recordSystem(
        string $module,
        string $action,
        string $entityType,
        int $entityId,
        ?string $label = null,
        ?array $changes = null,
        ?string $description = null,
        ?string $actorName = 'System/Automated',
    ): self {
        return self::create([
            'module' => $module,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $label,
            'actor_user_id' => null,
            'actor_name' => $actorName,
            'description' => $description,
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }
}
