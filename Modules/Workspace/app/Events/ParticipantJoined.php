<?php

namespace Modules\Workspace\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;
use Modules\Workspace\Support\CallPayload;

class ParticipantJoined implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Call $call, public CallParticipant $participant) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('presence-call.'.$this->call->id)];
    }

    public function broadcastAs(): string
    {
        return 'participant.joined';
    }

    public function broadcastWith(): array
    {
        return [
            ...CallPayload::make($this->call),
            'participant_user_id' => $this->participant->user_id,
        ];
    }
}
