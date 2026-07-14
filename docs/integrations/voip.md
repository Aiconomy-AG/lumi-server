# Workspace audio calls

Workspace calls use LiveKit for audio media, Reverb for foreground signalling, and Firebase Cloud Messaging for Android background delivery. Phone numbers are self-declared Lumi call handles; this integration does not place PSTN calls.

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
   ```

3. Run `php artisan migrate`.
4. Keep the Laravel queue worker and Reverb server running. Ring timeouts, FCM delivery, and missed-call notifications depend on the queue.
5. Configure the existing Firebase service-account settings and ensure mobile device tokens are registered through the existing device-token API.

The server issues room-scoped participant JWTs that permit microphone publishing and subscribing, but do not permit camera or data publishing.

## Client setup

- Web needs the existing API and Reverb environment variables. The browser requests microphone access on the first call.
- Android requires notification and microphone permissions. High-priority FCM data messages display a native call-style notification and register the call with Core-Telecom.
- iOS includes LiveKit and CallKit. Debug builds expose a `Debug CallKit` button for local CallKit testing. Reliable terminated-app incoming delivery is intentionally unavailable until PushKit/APNs signing is possible with an Apple Developer membership.

## Smoke test

1. Give the caller an E.164-style profile number such as `+40722123456`.
2. Log the recipient into both the web app and an Android device.
3. Start a call from a direct conversation.
4. Confirm both clients ring, answer one client, and confirm the other stops ringing.
5. Confirm two-way audio, mute, and end-call behavior.

Automated backend coverage can be run with:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test Modules/Workspace/tests/Feature/CallTest.php
```

