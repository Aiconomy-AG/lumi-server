<?php

namespace Modules\Workspace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Workspace\Models\Conversation;
use Symfony\Component\HttpFoundation\Response;

class VerifyConversationParticipant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $conversation = Conversation::find($request->route('conversationId'));

        if (!$conversation) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Conversation does not exist',
            ], 404);
        }

        $isParticipant = $conversation->participants()
            ->whereKey($request->user()->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'You are not a participant in this conversation',
            ], 403);
        }

        return $next($request);
    }
}