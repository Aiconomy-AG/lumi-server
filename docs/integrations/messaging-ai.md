# Messaging, realtime, and AI

## Reverb

The application broadcasts user status, time-entry, conversation-message, and notification events. Clients authenticate subscriptions through `/api/v1/broadcasting/auth` using Sanctum.

Private user channels restrict subscriptions to the matching user. Conversation channels query participant membership. The `team` presence channel returns user identity, role, and current status and refreshes presence on join.

## Firebase push notifications

Device tokens are stored per user and platform. Push delivery normally runs through `SendPushNotificationJob`. Firebase credential discovery follows the Laravel Firebase package configuration. Invalid or expired device tokens should be removed according to service behavior and delivery failures monitored.

## Gemini chat replies

AI replies are disabled unless `CHAT_AI_ENABLED` is true. Messages containing configured mention patterns can dispatch `GenerateAiChatReplyJob`. The job resolves the configured bot user, loads a bounded message history, strips mention tokens, calls Gemini, stores the response, and broadcasts it.

Failures must not roll back or remove the user's original message. Do not place secrets, private operational data, or unrestricted conversation histories into prompts.

