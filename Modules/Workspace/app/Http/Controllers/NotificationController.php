<?php

namespace Modules\Workspace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Workspace\Models\NotificationDelivery;
use Modules\Workspace\Services\NotificationService;
use Modules\Workspace\Transformers\NotificationDeliveryResource;

class NotificationController
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->notificationService->getForUser(
            (int) $request->user()->id,
            $request->boolean('unreadOnly')
        );

        return NotificationDeliveryResource::collection($notifications);
    }

    public function markAsRead(Request $request, int $notificationId): NotificationDeliveryResource|JsonResponse
    {
        $delivery = NotificationDelivery::query()
            ->where('recipient_user_id', $request->user()->id)
            ->find($notificationId);

        if (! $delivery) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Notification not found.'], 404);
        }

        return new NotificationDeliveryResource(
            $this->notificationService->markAsRead($delivery)
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updatedCount = $this->notificationService->markAllAsRead((int) $request->user()->id);

        return response()->json([
            'message' => 'Notifications marked as read successfully.',
            'updated_count' => $updatedCount,
        ]);
    }
}
