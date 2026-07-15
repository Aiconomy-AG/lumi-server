# Backend VoIP Implementation Guide

This document is written for coding agents and backend contributors who need maximum local context before changing the Lumi workspace calling stack. It describes how the current Laravel backend implements 1-to-1 and group calls, how calls connect to chat history, LiveKit, push notifications, presence, and webhooks, and which invariants must be preserved.

For deployment steps and environment examples, also read `docs/integrations/voip.md`.

## Fast Orientation

The backend call feature lives mostly in `Modules/Workspace`, with push and device-token support in `app/`.

Primary entry points:

- Routes:
  - `routes/api.php`
  - `Modules/Workspace/routes/api.php`
- Controller:
  - `Modules/Workspace/app/Http/Controllers/CallController.php`
- Domain service:
  - `Modules/Workspace/app/Services/CallService.php`
- LiveKit webhooks:
  - `Modules/Workspace/app/Http/Controllers/LiveKitWebhookController.php`
  - `Modules/Workspace/app/Services/CallWebhookService.php`
- LiveKit room/token integration:
  - `Modules/Workspace/app/Services/LiveKitService.php`
  - `Modules/Workspace/app/Infrastructure/LiveKitMediaRoomTokenProvider.php`
- Realtime payloads:
  - `Modules/Workspace/app/Support/CallPayload.php`
  - `Modules/Workspace/app/Transformers/CallResource.php`
  - `Modules/Workspace/app/Events/Call*.php`
  - `Modules/Workspace/app/Events/Participant*.php`
- Chat call log rows:
  - `Modules/Workspace/app/Services/CallChatLogService.php`
  - `Modules/Workspace/app/Support/CallChatLogPayload.php`
  - `Modules/Workspace/app/Transformers/MessageResource.php`
- Ringing, timeouts, push:
  - `Modules/Workspace/app/Jobs/DispatchCallRingJob.php`
  - `Modules/Workspace/app/Jobs/ExpireUnansweredCallJob.php`
  - `Modules/Workspace/app/Jobs/SendCallPushJob.php`
  - `app/Services/Push/IncomingCallPushDispatcher.php`
  - `app/Services/PushNotificationService.php`
  - `app/Services/Push/ApnsVoipPushService.php`
- Presence:
  - `Modules/Workspace/app/Services/CallPresenceService.php`
  - `app/Services/PresenceService.php`
- Cleanup:
  - `app/Console/Commands/VoipCleanupCommand.php`
- Tests:
  - `Modules/Workspace/tests/Feature/CallTest.php`
  - `Modules/Workspace/tests/Unit/CallConnectionResolverTest.php`

## Routing Surface

There are two public call route families.

Primary call API, recommended for new clients:

```text
POST /api/v1/calls
GET  /api/v1/calls/active
GET  /api/v1/calls/history
GET  /api/v1/calls/{callId}
POST /api/v1/calls/{callId}/accept
POST /api/v1/calls/{callId}/decline
POST /api/v1/calls/{callId}/cancel
POST /api/v1/calls/{callId}/leave
POST /api/v1/calls/{callId}/invite
POST /api/v1/calls/{callId}/end
```

Compatibility workspace routes:

```text
POST /api/v1/workspace/conversations/{conversationId}/calls
GET  /api/v1/workspace/calls/active
GET  /api/v1/workspace/calls/history
GET  /api/v1/workspace/calls/{callId}
POST /api/v1/workspace/calls/{callId}/accept
POST /api/v1/workspace/calls/{callId}/decline
POST /api/v1/workspace/calls/{callId}/cancel
POST /api/v1/workspace/calls/{callId}/leave
POST /api/v1/workspace/calls/{callId}/invite
POST /api/v1/workspace/calls/{callId}/end
```

LiveKit webhook route:

```text
POST /api/v1/webhooks/livekit
```

Important route behavior:

- `/api/v1/calls` supports optional `conversation_id`.
- When `conversation_id` is omitted, the call is not linked to chat history.
- When `conversation_id` is present, the call can be audio or video and creates or updates one call message in that conversation.
- `/workspace/conversations/{id}/calls` remains a compatibility endpoint. It calls all other conversation members and delegates into the same service flow.
- Call routes require `auth:sanctum` and `staff`, except the webhook.
- Conversation-scoped routes additionally use `VerifyConversationParticipant`.

## Request Contracts

`CreateCallRequest` for `POST /api/v1/calls`:

```json
{
  "callee_ids": [2, 3],
  "conversation_id": 10,
  "type": "video",
  "mode": "group",
  "client_instance_id": "web-tab-abc"
}
```

Fields:

- `callee_ids`: required array of user ids. The caller is removed if included.
- `conversation_id`: optional conversation id. If present, caller and callees must all be members.
- `type`: optional `audio` or `video`; defaults to `audio`.
- `mode`: optional `1v1` or `group`.
- `client_instance_id`: required device/tab/install identifier, max 100 chars. It is used for LiveKit identity and device locking.

`StartCallRequest` for `POST /workspace/conversations/{id}/calls`:

```json
{
  "type": "video",
  "client_instance_id": "ios-install-123"
}
```

This route derives recipients from the conversation and calls every other participant.

`AcceptCallRequest`:

```json
{
  "client_instance_id": "android-install-123"
}
```

`InviteCallRequest`:

```json
{
  "user_ids": [4, 5]
}
```

## Persistent Data Model

Core tables:

- `calls`
- `call_participants`
- `call_events`
- `messages` with nullable unique `call_id`

`calls` model:

- File: `Modules/Workspace/app/Models/Call.php`
- UUID primary key.
- `conversation_id` nullable. A null value means a call was started outside a chat thread.
- `initiated_by_user_id`, `caller_name`, `destination_type`.
- `mode`: `1v1` or `group`.
- `type`: `audio` or `video`.
- `media_type`: legacy mirror of `type`.
- `status`: `ringing`, `active`, `declined`, `cancelled`, `missed`, `ended`, `failed`.
- `room_name`: LiveKit room name, always `call_{uuid}`.
- `answered_client_instance_id`: legacy/1-to-1 accepted-device lock and first accepted device for group calls.
- `answered_at`, `started_at`, `ended_at`.
- `ended_by_user_id`, `end_reason`.

`call_participants` model:

- File: `Modules/Workspace/app/Models/CallParticipant.php`
- Unique key on `call_id` plus `user_id`.
- `role`: `caller` or `callee`.
- `status`: `invited`, `ringing`, `joined`, `declined`, `missed`, `left`.
- `client_instance_id`: device/tab that accepted or initiated.
- `livekit_identity`: identity observed in LiveKit webhook.
- `invited_at`, `ringing_delivered_at`, `answered_at`, `joined_at`, `left_at`.

`messages.call_id`:

- Added by `000044_add_call_log_to_messages.php`.
- Nullable, unique.
- Used to guarantee one chat row per conversation-linked call.
- The call row is inserted when a LiveKit room is created and updated at terminal status.

`call_events`:

- Append-only operational audit stream for call lifecycle and webhook events.
- Not the same as Laravel audit logs. It is scoped to call debugging.
- Pruned by `voip:cleanup`.

## Domain Enums

Files in `Modules/Workspace/app/Domain/Calls`:

- `CallMode`: `OneToOne = "1v1"`, `Group = "group"`.
- `CallType`: `Audio = "audio"`, `Video = "video"`.
- `CallStatus`:
  - non-terminal: `ringing`, `active`
  - terminal: `declined`, `cancelled`, `missed`, `ended`, `failed`
- `ParticipantStatus`:
  - pending: `invited`, `ringing`
  - active: `joined`
  - terminal/departed: `declined`, `missed`, `left`
- `CallDomainException`: API-safe exception that returns `{ code, message }`.
- `LiveKitWebhookException`: API-safe webhook error for malformed or unauthorized webhook calls.

## Main Service Responsibilities

`CallController` should remain thin. Almost all business rules belong in `CallService`.

`CallService` owns:

- Validating workspace call eligibility.
- Starting unlinked calls.
- Starting conversation-linked calls.
- Deriving group mode from group conversations.
- Creating call and participant rows transactionally.
- Creating the LiveKit room after DB creation.
- Creating chat log rows.
- Marking users busy and restoring presence.
- Dispatching ring jobs and timeout jobs.
- Accepting, declining, cancelling, ending, leaving, inviting, and marking missed.
- Broadcasting call events.
- Creating notifications and audit logs.

`LiveKitService` owns:

- Creating LiveKit rooms through `RoomServiceClient`.
- Deleting LiveKit rooms on explicit terminal flows.
- Converting `wss://` LiveKit URLs to `https://` for server API calls.
- Applying room capacity:
  - `LIVEKIT_MAX_PARTICIPANTS_1V1`
  - `LIVEKIT_MAX_PARTICIPANTS_GROUP`

`LiveKitMediaRoomTokenProvider` owns:

- Creating participant JWTs for LiveKit.
- Room grant:
  - join room
  - publish
  - subscribe
  - no data publishing
- Publish sources:
  - audio call: `microphone`
  - video call: `microphone`, `camera`, `screen_share`
- Identity:
  - `user:{user_id}:client:{client_instance_id}`

`CallConnectionResolver` owns:

- Deciding whether the API response or broadcast should include a `connection` object.
- Caller can get a token while call is `ringing` or `active`.
- Callees only get a token after they have joined and the call is active.
- 1-to-1 callee token must match `calls.answered_client_instance_id`.
- Group callee token must match the participant-level `call_participants.client_instance_id`.

## Starting Calls

### Primary API without conversation_id

`POST /api/v1/calls` without `conversation_id` calls `CallService::startCall`.

High-level flow:

1. Verify `voip.enabled`.
2. Normalize `callee_ids`.
3. Reject empty recipients.
4. For `1v1`, require exactly one callee.
5. Enforce group max participant limit if group.
6. Load caller and callees.
7. Ensure every participant is active staff and not the Lumi AI bot.
8. Lock participant users with `lockForUpdate`.
9. Check whether caller or callees are busy in another non-terminal call.
10. Create `calls` row with `status = ringing`.
11. Create caller participant as `joined`.
12. Create callee participants as `ringing`.
13. Create LiveKit room.
14. For conversation-linked calls only, insert chat call message.
15. Mark participants busy.
16. Dispatch `DispatchCallRingJob`.
17. Dispatch `ExpireUnansweredCallJob` scoped to initial callee ids.
18. Log call event and audit log.
19. Return `CallResource`, usually with caller LiveKit connection.

Busy logic uses participant statuses `invited`, `ringing`, `joined`. Users who previously declined, missed, or left are not treated as busy.

### Primary API with conversation_id

`POST /api/v1/calls` with `conversation_id` calls `CallService::startConversationCall`.

Additional rules:

- Caller must be a conversation participant.
- Every callee must be a conversation participant.
- Caller can call any subset of other conversation members.
- Caller is removed from `callee_ids` if accidentally included.
- If the conversation type is `group`, the call mode is always `group`, even with one selected callee.
- If caller explicitly passes `mode` and it conflicts with the derived mode, request fails with `INVALID_CALL_MODE`.
- Linked group video is supported. This is the chat plus video API gap that this implementation fills.

### Conversation route

`POST /api/v1/workspace/conversations/{conversationId}/calls` calls `CallService::startWorkspaceCall`.

Rules:

- Uses all other conversation participants as callees.
- Supports `type: audio` and `type: video`.
- Delegates to `startConversationCall`.
- Preserves backward compatibility for existing clients.

## Accept, Device Locking, and Connections

`POST /calls/{id}/accept` calls `CallService::accept`.

Rules:

- Only callees can accept.
- Call must be `ringing` or, for group pending invitees, `active`.
- Participant must be pending unless this is an idempotent repeated accept from the same device.
- Accept updates participant:
  - `status = joined`
  - `answered_at = now`
  - `joined_at = now`
  - `client_instance_id = request client_instance_id`
- First accept changes call to `active`.
- First accept sets `calls.answered_client_instance_id` if null.
- Group invitees can accept after call is already active.
- A second accept from a different device fails with `ANSWERED_ELSEWHERE`.
- Accepted devices are enforced by `CallConnectionResolver`.

Broadcast side effects:

- `call.updated` to all participants.
- `call.accepted` to all participants.
- Each recipient receives a LiveKit `connection` only if `CallConnectionResolver` allows it.

## Decline, Cancel, End, Leave

### Decline

`POST /calls/{id}/decline` calls `CallService::decline`.

Rules:

- Only callees can decline.
- Pending group callees may decline after another participant has already activated the call.
- Declining an active group call does not end the call.
- For ringing calls, the call only becomes terminal when all callees have either declined or stopped pending.
- If all callees declined, terminal call status is `declined`.
- Otherwise, final unanswered terminal status is `missed`.
- Presence is restored only for the declining participant when the group call continues.

### Cancel

`POST /calls/{id}/cancel` calls `finish(..., CallStatus::Cancelled)`.

Rules:

- Only caller can cancel.
- Call must still be `ringing`.
- Terminal status becomes `cancelled`.
- Presence restored for all participants.
- Chat row updated for conversation-linked calls.

### End

`POST /calls/{id}/end` calls `finish(..., CallStatus::Ended)`.

Rules:

- Used for 1-to-1 active calls.
- Group calls reject `/end` with `GROUP_CALL_LEAVE_REQUIRED`.
- Group callers and participants must use `/leave` individually.
- For 1-to-1, call must be active.
- Terminal status becomes `ended`.
- Joined participants are marked `left`.
- LiveKit room is deleted.
- Chat row updated if linked.

### Leave

`POST /calls/{id}/leave` calls `CallService::leave`.

Rules:

- Call must be active.
- Participant must already be `joined`.
- Pending group participants cannot leave.
- Participant becomes `left`.
- If any joined participant remains, call stays active.
- If no joined participants remain:
  - pending invitees become `missed`
  - call becomes `ended`
  - LiveKit room is deleted
  - chat row is updated
- Presence is restored for leaving participant, and for all participants if the call ends.

## Group Invite Flow

`POST /calls/{id}/invite` calls `CallService::invite`.

Rules:

- Call feature must be enabled.
- Only group calls can invite.
- Call must be `ringing` or `active`.
- Inviter must be a joined participant.
- Already existing participants are filtered out.
- If all requested users are already participants, request fails with `ALREADY_INVITED`.
- If the call is conversation-linked, every invitee must be a member of that conversation.
- Invitees must be active staff and not the Lumi AI bot.
- Invitees must not be busy in another non-terminal call.
- Group max participant limit is enforced.

For each new invitee:

- Participant row is created with `role = callee`, `status = invited`, `invited_at = now`.
- Presence is marked busy for only the new invitees.
- `DispatchCallRingJob` is dispatched with only the new invitee ids.
- `ExpireUnansweredCallJob` is dispatched with only the new invitee ids.
- This is how initial and mid-call invite batches expire independently.

## Ringing and Timeout Jobs

`DispatchCallRingJob`:

- Runs on `config('voip.queues.calls', 'calls')`.
- Loads call with participants and conversation.
- Only proceeds for call status `ringing` or `active`.
- If `inviteeUserIds` is provided, rings only those participants.
- Filters to pending participants using `CallParticipant::isPending`.
- Dispatches realtime:
  - `call.incoming`
  - `call.ringing`
- Dispatches push through `IncomingCallPushDispatcher`.
- Sets `ringing_delivered_at`.
- Logs `rung` in `call_events`.

`ExpireUnansweredCallJob`:

- Calls `CallService::markMissed($callId, $participantUserIds)`.
- Initial call start passes initial callee ids.
- Mid-call invite passes only newly invited ids.
- Empty participant list means all pending callees for the call, which is used by cleanup.

`CallService::markMissed`:

- Works on `ringing` and `active` calls.
- Marks selected pending callees as `missed`.
- Restores presence for those missed users.
- Creates missed call notifications for those users.
- If the call is still `ringing` and no pending or joined callee remains, call becomes terminal `missed`.
- If the call is active, missed invitees do not end it.
- Updates chat row only if the call becomes terminal.

## Realtime Events

Broadcast channels:

- Private per-user channel: `users.{userId}`
- Presence call channel: `presence-call.{callId}`
- Conversation channel for messages: `conversations.{conversationId}`

Call events on `users.{userId}`:

- `call.incoming`
- `call.ringing`
- `call.updated`
- `call.accepted`
- `call.declined`
- `call.cancelled`
- `call.ended`

Presence call events on `presence-call.{callId}`:

- `participant.joined`
- `participant.left`

Chat row event:

- `message.sent` on `conversations.{conversationId}` when the call row is first created.

Payload source:

- All call resources and call broadcasts use `CallPayload::make`.
- The optional `connection` field appears only when the recipient should get a LiveKit token.

Call payload includes:

- call ids and conversation id
- caller info
- participants with role, status, and lifecycle timestamps
- mode/type/media type/status
- answered client id
- timestamps
- optional `connection`

## Chat Call Rows

`CallChatLogService` is responsible for chat call messages.

Behavior:

- Only conversation-linked calls are recorded.
- A call message is created immediately after LiveKit room creation.
- `messages.call_id` is unique.
- `recordCall` uses `firstOrNew` and catches duplicate insert races by reloading existing row.
- `message.sent` is broadcast only when the row is first created.
- Terminal updates reuse the same row and update the preview text.

Message preview rules:

- Non-terminal/default audio: `Call`
- Non-terminal/default video: `Video call`
- Missed: `Missed Call` or `Missed Video call`
- Declined: `Declined Call` or `Declined Video call`
- Cancelled: `Cancelled Call` or `Cancelled Video call`
- Failed: `Failed Call` or `Failed Video call`
- Ended with duration: `Call · {minutes} min` or `Video call · {minutes} min`

`MessageResource` includes `call` metadata through `CallChatLogPayload::make`.

## LiveKit Room and Token Details

Room creation:

- `CallService::startCall` writes DB rows first.
- `LiveKitService::createRoom` creates the LiveKit room.
- If room creation fails:
  - call status becomes `failed`
  - `end_reason = livekit_error`
  - `ended_at = now`
  - presence restore is attempted
  - terminal chat log is recorded for linked calls
  - original domain exception is rethrown

Room options:

- name: `call_{uuid}`
- empty timeout: `LIVEKIT_EMPTY_TIMEOUT_SECONDS`
- max participants:
  - 1-to-1: `LIVEKIT_MAX_PARTICIPANTS_1V1`
  - group: `LIVEKIT_MAX_PARTICIPANTS_GROUP`

Token creation:

- `LiveKitMediaRoomTokenProvider` builds an `AccessToken`.
- LiveKit identity is deterministic per user/device:
  - `user:{user_id}:client:{client_instance_id}`
- Token metadata includes:
  - `user_id`
  - `call_id`
  - `client_instance_id`
- TTL uses `LIVEKIT_TOKEN_TTL_SECONDS`.
- Data publishing is disabled.

Media permissions:

- Audio calls publish sources: `microphone`
- Video calls publish sources: `microphone`, `camera`, `screen_share`

Frontend responsibilities:

- Minimize call UI.
- PiP integration on mobile.
- Camera/microphone/screen-share capture UX.
- Reconnecting to LiveKit with the token provided by backend.

## LiveKit Webhooks

Route:

```text
POST /api/v1/webhooks/livekit
```

Verification:

- `CallWebhookService::validateAuthorizationHeader` decodes LiveKit authorization JWT with `LIVEKIT_API_SECRET`.
- It verifies:
  - JWT issuer equals `LIVEKIT_API_KEY`
  - JWT `sha256` claim equals base64 SHA-256 hash of raw request body
- The SDK `WebhookReceiver` then parses the event with the same key and secret.
- There is no separate `LIVEKIT_WEBHOOK_SECRET`.

Supported events:

- `participant_joined`
- `participant_left`
- `room_finished`

Unknown events:

- Logged as webhook events if the room maps to a call.
- Otherwise ignored.

`participant_joined`:

- Finds call by LiveKit room name.
- Finds participant by `livekit_identity` or by user id parsed from identity.
- Locks call and participant.
- Ignores terminal calls.
- Ignores duplicate already-processed joins.
- Sets participant `status = joined`, `joined_at`, and `livekit_identity`.
- Sets call `started_at` if empty.
- If call is ringing and participant is callee, sets call `active` and `answered_at`.
- Broadcasts `participant.joined`.
- Logs `joined`.

`participant_left`:

- Finds call and participant by identity.
- Locks call and participant.
- Ignores duplicate already-left participants.
- Sets participant `status = left`, `left_at`.
- If no joined participants remain and call is active:
  - pending participants become `missed`
  - call becomes `ended`
  - `end_reason = ended`
- Broadcasts `participant.left`.
- Restores presence for the leaving participant.
- If the call ended:
  - restores presence for all call participants
  - broadcasts `call.ended`
  - updates chat row
- Logs `left`.

`room_finished`:

- Accepts either a `WebhookEvent` or direct `Call`.
- Locks the call with participants.
- Marks joined participants `left`.
- Marks pending participants `missed`.
- If call is not already terminal, marks it `ended`.
- Broadcasts `call.ended` only on first transition to terminal.
- Restores presence.
- Logs `ended` with source `room_finished`.
- Updates terminal chat row.

Idempotency expectations:

- Webhooks can arrive late or more than once.
- Delayed joins for terminal calls are ignored.
- Duplicate room finished calls must not create duplicate chat messages because `messages.call_id` is unique.
- Duplicate participant left calls should not rebroadcast left if participant is already left.

## Push Notifications and Device Tokens

Device token registration:

- Route: `POST /api/v1/device-tokens`
- Controller: `app/Http/Controllers/DeviceTokenController.php`
- Platforms:
  - `fcm_android`
  - legacy `android`, normalized to `fcm_android`
  - `voip_ios`
  - legacy `apns_voip`, normalized to `voip_ios`
  - `ios`
  - `web_push`
- `device_id` distinguishes multiple devices/installations for the same user.

Incoming call push:

- `DispatchCallRingJob` calls `IncomingCallPushDispatcher::dispatchIncomingCall`.
- FCM Android tokens receive high priority data messages through `PushNotificationService::sendCallEventToToken`.
- iOS VoIP tokens receive direct APNs HTTP/2 `voip` pushes through `ApnsVoipPushService`.
- APNs invalidates tokens on 410 responses.

Incoming call payload:

```json
{
  "type": "workspace_call_incoming",
  "call_id": "uuid",
  "caller_name": "Name",
  "caller_user_id": "1",
  "call_type": "audio",
  "call_mode": "1v1",
  "conversation_id": "",
  "status": "ringing"
}
```

Call update push:

- `CallService::announceUpdate` dispatches `SendCallPushJob` for each participant.
- Payload type is `workspace_call_updated`.
- Used as a background nudge; realtime Reverb events remain the primary foreground signal.

APNs configuration:

- File: `config/apns.php`
- Uses:
  - `APNS_P8_PATH`
  - `APNS_KEY_ID`
  - `APNS_TEAM_ID`
  - `APNS_BUNDLE_ID`
  - `APNS_USE_SANDBOX`
- Host switches between:
  - sandbox: `https://api.sandbox.push.apple.com`
  - production: `https://api.push.apple.com`
- VoIP topic is `{APNS_BUNDLE_ID}.voip`.

## Presence Integration

`CallPresenceService` changes workspace user presence around calls.

When a call starts or a user is invited:

- Users with status `available` or `busy` are force-set to `busy`.
- Original status is stored in:
  - `call_status_restore_status`
  - `call_status_restore_call_id`
- `UserStatusUpdated` is broadcast if status changed.

When a user declines, misses, leaves, or a call ends:

- `restoreForCall` restores users whose `call_status_restore_call_id` matches the call.
- If the user still has another ringing or active call with participant status `invited`, `ringing`, or `joined`, status is not restored.
- Manual away status clears call restore state.
- Manual available/busy during a call updates the future restore status while keeping current status busy.

## Authorization and Eligibility Rules

Workspace calls are not for every user.

`CallService::ensureWorkspaceCallUser` requires:

- `user.is_active = true`
- role is `Employee` or `Admin`
- user is not the configured Lumi AI bot email

Conversation-linked calls require:

- Caller is a conversation member.
- Callees are conversation members.
- Mid-call invitees are conversation members if the call has `conversation_id`.

Call action rules:

- Accept: callee only.
- Decline: callee only.
- Cancel: caller only, ringing only.
- End: active 1-to-1 only.
- Leave: joined participants only.
- Invite: joined group participants only.

## Status Lifecycle Cheat Sheet

1-to-1 normal answer:

```text
call: ringing -> active -> ended
caller participant: joined -> left
callee participant: ringing -> joined -> left
```

1-to-1 missed:

```text
call: ringing -> missed
caller participant: joined
callee participant: ringing -> missed
```

1-to-1 declined:

```text
call: ringing -> declined
caller participant: joined
callee participant: ringing -> declined
```

Group, one accepts and another is still ringing:

```text
call: ringing -> active
caller participant: joined
callee A: ringing -> joined
callee B: ringing
```

Group, pending callee declines active call:

```text
call: active
callee B: ringing/invited -> declined
```

Group, invitee timeout during active call:

```text
call: active
invitee: invited -> missed
```

Group ends by last joined participant leaving:

```text
call: active -> ended
joined participants: joined -> left
pending participants: ringing/invited -> missed
```

LiveKit room finished:

```text
call: any non-terminal -> ended
joined participants: joined -> left
pending participants: ringing/invited -> missed
```

## Connection Field Rules

The API may return:

```json
{
  "connection": {
    "url": "wss://...",
    "token": "..."
  }
}
```

Rules:

- Caller gets connection on create while call is ringing or active.
- Callee does not get connection before accepting.
- Callee gets connection in accept response if accepted successfully.
- Group callees are locked to participant-level `client_instance_id`.
- 1-to-1 callee is locked to `calls.answered_client_instance_id`.
- `GET /calls/{id}?client_instance_id=...` and `GET /calls/active?client_instance_id=...` include connection only if the requester/device passes resolver checks.
- Broadcasts include connection only for the recipient who should get it.

## Error Codes Worth Preserving

Common `CallDomainException` codes:

- `VOIP_DISABLED`
- `VOIP_NOT_CONFIGURED`
- `CALLEE_REQUIRED`
- `INVITEE_REQUIRED`
- `INVALID_CALL_MODE`
- `CALL_RECIPIENTS_REQUIRED`
- `CONVERSATION_PARTICIPANT_REQUIRED`
- `WORKSPACE_CALL_PARTICIPANT_REQUIRED`
- `CALL_PARTICIPANT_LIMIT_EXCEEDED`
- `USER_BUSY`
- `CALL_NOT_FOUND`
- `FORBIDDEN`
- `INVALID_CALL_ACTION`
- `CALL_NOT_RINGING`
- `CALL_NOT_ACTIVE`
- `GROUP_CALL_REQUIRED`
- `GROUP_CALL_LEAVE_REQUIRED`
- `ALREADY_INVITED`
- `PARTICIPANT_NOT_FOUND`
- `ANSWERED_ELSEWHERE`

Clients likely key on these codes, so avoid changing them casually.

## Configuration

VoIP config:

- File: `config/voip.php`
- Env:
  - `VOIP_ENABLED`
  - `VOIP_RING_TIMEOUT_SECONDS`
  - `VOIP_CALLS_QUEUE`
  - `LIVEKIT_URL`
  - `LIVEKIT_API_KEY`
  - `LIVEKIT_API_SECRET`
  - `LIVEKIT_TOKEN_TTL_SECONDS`
  - `LIVEKIT_EMPTY_TIMEOUT_SECONDS`
  - `LIVEKIT_MAX_PARTICIPANTS_1V1`
  - `LIVEKIT_MAX_PARTICIPANTS_GROUP`
  - cleanup env vars

APNs config:

- File: `config/apns.php`
- Env:
  - `APNS_P8_PATH`
  - `APNS_KEY_ID`
  - `APNS_TEAM_ID`
  - `APNS_BUNDLE_ID`
  - `APNS_USE_SANDBOX`

Queues:

- Ring and timeout jobs use the calls queue.
- Production worker should consume both calls and default:

```bash
php artisan queue:work database --queue=calls,default --sleep=1 --tries=3
```

Scheduler:

- Must run `voip:cleanup` if scheduled in the app scheduler.
- The command prunes stale device tokens, expires old ringing calls, and deletes old call events.

## Test Coverage Map

Main feature file:

- `Modules/Workspace/tests/Feature/CallTest.php`

It covers:

- Primary 1-to-1 creation.
- Audio token publish source.
- Video token publish sources.
- Accept broadcasts and connection payload.
- Conversation route compatibility.
- Conversation route video calls.
- Primary linked group video calls.
- Conversation member authorization.
- Conflicting mode rejection.
- Busy callee behavior.
- Presence busy and restore behavior.
- Group `/end` rejection.
- 1-to-1 device locking.
- Group partial decline.
- Later group accept after active.
- Group participant device locking.
- Pending active group decline.
- Leave ending behavior.
- Force end for 1-to-1.
- Group invites.
- Group capacity limits.
- Linked group invite member restriction.
- Scoped invite timeout.
- Pending group participant cannot leave/invite.
- History.
- Missed timeout.
- Webhook auth rejection.
- Webhook malformed JSON rejection.
- Unknown room handling.
- Participant left webhook behavior.
- Room finished idempotency.
- Immediate and terminal chat call row behavior.

Unit tests:

- `Modules/Workspace/tests/Unit/CallConnectionResolverTest.php`
- Covers connection token inclusion in per-user broadcasts.

Recommended local verification:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test Modules/Workspace/tests/Feature/CallTest.php Modules/Workspace/tests/Feature/MessageTest.php Modules/Workspace/tests/Feature/ConversationTest.php
vendor/bin/pint --test Modules/Workspace/app/Http/Controllers/CallController.php Modules/Workspace/app/Services/CallService.php Modules/Workspace/app/Services/CallWebhookService.php Modules/Workspace/tests/Feature/CallTest.php
```

Preferred full verification:

```bash
./vendor/bin/sail artisan test Modules/Workspace/tests/Feature/CallTest.php
```

The repository's default testing config expects MySQL host `mysql`, so running `php artisan test` directly outside Sail may fail unless database env vars are overridden.

## Safe Extension Points

When adding backend call behavior, prefer these extension points:

- Add request validation in the relevant FormRequest.
- Put lifecycle/business rules in `CallService`.
- Put token eligibility rules in `CallConnectionResolver`.
- Put media grant changes in `LiveKitMediaRoomTokenProvider`.
- Put room creation/deletion changes in `LiveKitService`.
- Put webhook reconciliation in `CallWebhookService`.
- Put chat preview/message changes in `CallChatLogService` or `CallChatLogPayload`.
- Put broadcast payload additions in `CallPayload`.
- Add tests in `CallTest` first, then narrower unit tests if the behavior is isolated.

Avoid:

- Duplicating group call logic outside `CallService`.
- Creating a separate group-call stack.
- Giving pending callees LiveKit tokens before accept.
- Ending group calls through `/end`.
- Letting non-conversation members into conversation-linked calls.
- Recreating chat rows for the same call.
- Treating declined, missed, or left participants as busy.
- Adding a `LIVEKIT_WEBHOOK_SECRET`; the backend verifies LiveKit webhooks with API key and API secret.

## Frontend Contract Summary

Backend supports:

- 1-to-1 audio calls.
- 1-to-1 video calls.
- Group audio calls.
- Group video calls.
- Video screen-share permission via LiveKit token grant.
- Mid-call group invite.
- Conversation-linked chat call rows.
- Realtime call state events.
- Android FCM incoming-call data pushes.
- iOS APNs VoIP incoming-call pushes.

Frontend owns:

- Rendering call UI.
- Local device permissions.
- Camera/microphone/screen capture.
- PiP and minimized-call state.
- LiveKit client join/leave.
- CallKit/native incoming-call UX.
- Deduplicating/reconciling realtime events with REST responses.

## Practical Agent Workflow

For any future change:

1. Start by reading `CallService`, `CallConnectionResolver`, and `CallWebhookService`.
2. Check `CallTest` for the closest lifecycle scenario.
3. Preserve current error codes and response shapes unless the task explicitly changes API contract.
4. Add or update tests around the lifecycle edge first.
5. Run focused call tests.
6. Run Pint on touched PHP files.
7. If behavior touches production sync, reason through both API action and LiveKit webhook paths. Both may happen, and either can arrive first.
