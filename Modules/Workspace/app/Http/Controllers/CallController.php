<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Domain\Calls\CallMode;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\CallType;
use Modules\Workspace\Http\Requests\AcceptCallRequest;
use Modules\Workspace\Http\Requests\CreateCallRequest;
use Modules\Workspace\Http\Requests\InviteCallRequest;
use Modules\Workspace\Http\Requests\StartCallRequest;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Services\CallService;
use Modules\Workspace\Transformers\CallResource;

class CallController extends Controller
{
    public function __construct(
        private readonly CallService $calls,
        private readonly MediaRoomTokenProvider $mediaTokens,
    ) {}

    public function create(CreateCallRequest $request): CallResource
    {
        $validated = $request->validated();
        $type = isset($validated['type']) ? CallType::from($validated['type']) : CallType::Audio;
        $mode = isset($validated['mode'])
            ? CallMode::from($validated['mode'])
            : (count($validated['callee_ids']) > 1 ? CallMode::Group : CallMode::OneToOne);

        $call = $this->calls->startCall(
            caller: $request->user(),
            calleeIds: $validated['callee_ids'],
            type: $type,
            mode: $mode,
            clientInstanceId: $validated['client_instance_id'],
        );

        return (new CallResource($call))->withConnection(
            $this->mediaTokens->connectionFor($call, $request->user(), $validated['client_instance_id']),
        );
    }

    public function store(StartCallRequest $request, int $conversationId): CallResource
    {
        $call = $this->calls->startWorkspaceCall(
            Conversation::query()->findOrFail($conversationId),
            $request->user(),
            $request->validated('client_instance_id'),
        );
        $connection = $this->mediaTokens->connectionFor(
            $call,
            $request->user(),
            $request->validated('client_instance_id'),
        );

        return (new CallResource($call))->withConnection($connection);
    }

    public function active(Request $request): CallResource|array
    {
        $call = $this->calls->activeForUser((int) $request->user()->id);
        if (! $call) {
            return ['data' => null];
        }

        $resource = new CallResource($call);
        $clientInstanceId = trim((string) $request->query('client_instance_id'));
        $participant = $call->participants->firstWhere('user_id', $request->user()->id);
        $canJoin = $participant?->role === 'caller'
            || ($call->status === CallStatus::Active
                && ($call->isGroup() || $call->answered_client_instance_id === $clientInstanceId));

        if ($canJoin && $clientInstanceId !== '') {
            $resource->withConnection($this->mediaTokens->connectionFor(
                $call,
                $request->user(),
                $clientInstanceId,
            ));
        }

        return $resource;
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 20), 50);

        return CallResource::collection(
            $this->calls->historyForUser((int) $request->user()->id, $perPage),
        );
    }

    public function show(Request $request, string $callId): CallResource
    {
        return new CallResource($this->calls->getForParticipant($callId, (int) $request->user()->id));
    }

    public function accept(AcceptCallRequest $request, string $callId): CallResource
    {
        $clientInstanceId = $request->validated('client_instance_id');
        $call = $this->calls->accept($callId, $request->user(), $clientInstanceId);

        return (new CallResource($call))->withConnection(
            $this->mediaTokens->connectionFor($call, $request->user(), $clientInstanceId),
        );
    }

    public function decline(Request $request, string $callId): CallResource
    {
        return new CallResource($this->calls->decline($callId, $request->user()));
    }

    public function cancel(Request $request, string $callId): CallResource
    {
        return new CallResource($this->calls->cancel($callId, $request->user()));
    }

    public function end(Request $request, string $callId): CallResource
    {
        return new CallResource($this->calls->end($callId, $request->user()));
    }

    public function leave(Request $request, string $callId): CallResource
    {
        return new CallResource($this->calls->leave($callId, $request->user()));
    }

    public function invite(InviteCallRequest $request, string $callId): CallResource
    {
        return new CallResource(
            $this->calls->invite($callId, $request->user(), $request->validated('user_ids')),
        );
    }
}
