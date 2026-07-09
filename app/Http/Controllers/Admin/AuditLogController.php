<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'module' => ['sometimes', 'string', 'max:255'],
            'action' => ['sometimes', 'string', 'max:255'],
            'entity_type' => ['sometimes', 'string', 'max:255'],
            'entity_id' => ['sometimes', 'integer'],
            'actor_user_id' => ['sometimes', 'integer'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->when($validated['module'] ?? null, fn ($q, $module) => $q->where('module', $module))
            ->when($validated['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($validated['entity_type'] ?? null, fn ($q, $type) => $q->where('entity_type', $type))
            ->when($validated['entity_id'] ?? null, fn ($q, $id) => $q->where('entity_id', $id))
            ->when($validated['actor_user_id'] ?? null, fn ($q, $id) => $q->where('actor_user_id', $id))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('occurred_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20);

        return JsonResource::collection($logs);
    }
}
