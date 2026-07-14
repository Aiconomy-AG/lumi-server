<?php

namespace Modules\Workspace\Services;

use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Messages\MessageType;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Support\CallChatLogPayload;

class CallChatLogService
{
    public function recordTerminalCall(Call $call): ?Message
    {
        if ($call->conversation_id === null || ! $call->status->isTerminal()) {
            return null;
        }

        if (Message::query()->where('call_id', $call->id)->exists()) {
            return null;
        }

        $message = Message::query()->create([
            'conversation_id' => $call->conversation_id,
            'sender_id' => $call->initiated_by_user_id,
            'message_type' => MessageType::Call,
            'call_id' => $call->id,
            'message' => CallChatLogPayload::preview($call),
        ]);

        $message->setRelation('call', $call);
        MessageSent::dispatch($message->load('call'));

        return $message;
    }

    public function shouldRecord(Call $call): bool
    {
        return $call->conversation_id !== null
            && in_array($call->status, [
                CallStatus::Ended,
                CallStatus::Missed,
                CallStatus::Declined,
                CallStatus::Cancelled,
                CallStatus::Failed,
            ], true);
    }
}
