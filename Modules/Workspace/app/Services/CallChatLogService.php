<?php

namespace Modules\Workspace\Services;

use Illuminate\Database\QueryException;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Messages\MessageType;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Support\CallChatLogPayload;

class CallChatLogService
{
    public function recordCall(Call $call): ?Message
    {
        if ($call->conversation_id === null) {
            return null;
        }

        $attributes = [
            'conversation_id' => $call->conversation_id,
            'sender_id' => $call->initiated_by_user_id,
            'message_type' => MessageType::Call,
            'message' => CallChatLogPayload::preview($call),
        ];

        $message = Message::query()->firstOrNew(['call_id' => $call->id]);
        $created = ! $message->exists;

        try {
            $message->fill($attributes)->save();
        } catch (QueryException $exception) {
            $message = Message::query()->where('call_id', $call->id)->first();
            if (! $message) {
                throw $exception;
            }

            $created = false;
            $message->fill($attributes)->save();
        }

        $message->setRelation('call', $call);
        if ($created) {
            MessageSent::dispatch($message->load('call'));
        }

        return $message;
    }

    public function recordTerminalCall(Call $call): ?Message
    {
        if (! $call->status->isTerminal()) {
            return null;
        }

        return $this->recordCall($call);
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
