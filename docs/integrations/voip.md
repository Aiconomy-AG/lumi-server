# Workspace VoIP calls

Workspace calls use LiveKit for audio/video media, Reverb for foreground signalling, Firebase Cloud Messaging for Android background delivery, and APNs VoIP for iOS incoming-call delivery when the app is terminated.

## Backend setup

1. Create a LiveKit Cloud project (the free Build plan is sufficient for development) or run LiveKit locally.
2. Configure the server:

   ```dotenv
   VOIP_ENABLED=true
   VOIP_RING_TIMEOUT_SECONDS=45
   LIVEKIT_URL=wss://your-project.livekit.cloud
   LIVEKIT_API_KEY=your-key
   LIVEKIT_API_SECRET=your-secret-at-least-32-characters
   LIVEKIT_TOKEN_TTL_SECONDS=900
   LIVEKIT_WEBHOOK_SECRET=
   LIVEKIT_EMPTY_TIMEOUT_SECONDS=60
   LIVEKIT_MAX_PARTICIPANTS_1V1=2
   LIVEKIT_MAX_PARTICIPANTS_GROUP=10
   VOIP_CALLS_QUEUE=calls
   APNS_KEY_ID=
   APNS_TEAM_ID=
   APNS_BUNDLE_ID=
   APNS_KEY_PATH=
   APNS_PRODUCTION=false
   ```

3. Run `php artisan migrate`.
4. Keep the Laravel queue worker and Reverb server running. Ring timeouts, FCM/APNs delivery, and missed-call notifications depend on the queue.
5. Configure Firebase service-account settings for Android FCM.
6. Configure APNs `.p8` auth key for iOS VoIP push (see manual steps below).

The server creates LiveKit rooms on call start and issues room-scoped participant JWTs. Audio calls permit microphone publishing; video calls add camera. Data publishing is disabled.

## API routes

Primary routes (recommended for new clients):

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/calls` | Start call (`callee_ids`, `type`, `mode`, `client_instance_id`) |
| `POST` | `/api/v1/calls/{id}/accept` | Accept incoming call |
| `POST` | `/api/v1/calls/{id}/decline` | Decline while ringing |
| `POST` | `/api/v1/calls/{id}/cancel` | Caller cancels while ringing |
| `POST` | `/api/v1/calls/{id}/leave` | Leave active call (group-safe) |
| `POST` | `/api/v1/calls/{id}/invite` | Mid-call invite (group only) |
| `POST` | `/api/v1/calls/{id}/end` | Force-end active call |
| `GET` | `/api/v1/calls/{id}` | Call details |
| `GET` | `/api/v1/calls/active` | Current active/ringing call |
| `GET` | `/api/v1/calls/history` | Paginated completed calls |

Backward-compatible workspace routes remain under `/api/v1/workspace/calls/*` and `/api/v1/workspace/conversations/{id}/calls`.

Device tokens accept `platform` values: `fcm_android`, `android` (normalized to `fcm_android`), `ios` (alert pushes), `apns_voip`, `web_push`. Include `device_id` for multi-device support.

## Broadcasting

Subscribe to user channels `users.{userId}` for:

- `call.incoming` (new) and `call.ringing` (legacy)
- `call.accepted`, `call.declined`, `call.cancelled`, `call.ended` (new)
- `call.updated` (legacy, still dispatched)

Subscribe to presence channel `presence-call.{callId}` for:

- `participant.joined`
- `participant.left`

## LiveKit webhooks

Register `POST /api/v1/webhooks/livekit` in the LiveKit dashboard. Enable `participant_joined`, `participant_left`, and `room_finished`. Webhook events authoritatively set `joined_at`, `left_at`, `started_at`, and `ended_at`.

## Client setup

- Web: existing API + Reverb env vars; request microphone (and camera for video) on first call.
- Android: register `fcm_android` token; high-priority FCM data messages wake the app for incoming calls.
- iOS: register a separate `apns_voip` token for CallKit incoming calls; keep `ios` token for non-call alert pushes.

## Smoke test

1. `POST /api/v1/calls` (1v1 audio) — verify room in LiveKit dashboard.
2. Accept on one device — verify webhook sets `joined_at`.
3. FCM ring on Android.
4. APNs VoIP ring on iOS (physical device required).
5. Group call with 3 callees — one declines, one accepts → call becomes `active`.
6. `GET /api/v1/calls/history` returns completed calls.

---

## Manual deployment checklist

### Server / Forge

1. `composer install` on production (includes `agence104/livekit-server-sdk` and `edamov/pushok`).
2. `php artisan migrate`.
3. Set env vars (see above).
4. Restart queue worker: `php artisan queue:work --queue=calls,default --tries=3`.
5. Ensure Reverb daemon is running.
6. Ensure scheduler cron runs (`* * * * * php artisan schedule:run`).
7. Redeploy/restart PHP-FPM after env changes.

### LiveKit Cloud

1. Create or use existing LiveKit project.
2. Set `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET` (secret >= 32 chars).
3. Dashboard → Webhooks: add `https://your-domain/api/v1/webhooks/livekit`.
4. Copy webhook signing secret → `LIVEKIT_WEBHOOK_SECRET`.
5. Enable events: `room_started`, `participant_joined`, `participant_left`, `room_finished`.

### Firebase / FCM

1. Confirm `FIREBASE_CREDENTIALS` points to service account JSON.
2. Android app registers `platform: fcm_android` (legacy `android` still accepted).
3. FCM high-priority data messages require no extra server change.

### Apple / APNs VoIP

1. Apple Developer → Keys → create APNs Auth Key (.p8); note Key ID + Team ID.
2. Upload `.p8` to server (e.g. `storage/app/apns/AuthKey_XXXX.p8`); set `APNS_KEY_PATH`.
3. Set `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_BUNDLE_ID` (e.g. `com.lumi.app`).
4. VoIP topic is automatically `{APNS_BUNDLE_ID}.voip`.
5. Xcode: enable Push Notifications + Background Modes → Voice over IP.
6. iOS app registers separate VoIP token → `POST /api/v1/device-tokens` with `platform: apns_voip` and `device_id`.
7. Keep regular `ios` token for non-call pushes.
8. Set `APNS_PRODUCTION=true` for production builds, `false` for sandbox/dev.

### Client migration

1. Point call API to `/api/v1/calls` (workspace routes still work).
2. Register `apns_voip` device token separately from alert token.
3. Subscribe to new events (`call.incoming`, `call.accepted`, etc.) and `presence-call.{callId}`.
4. Handle `type: video` — request camera permission.
5. Group calls: handle partial decline (others still ringing).
6. Use `leave` instead of `end` when a single participant exits an active group call.
