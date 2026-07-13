# Core platform

## Authentication and presence

`TokenController` issues and revokes Sanctum tokens, returns the authenticated profile, updates manual status, processes heartbeats, and handles disconnects. `PresenceService` centralizes alive, offline, and manual-status transitions and broadcasts changes.

Password-reset validation and completion use Laravel's password broker. Administrative invitations create a temporary password, require a password change, and send a reset link through the configured mail provider.

## User administration

Administrators can create, inspect, update, deactivate, and resend invitations. Deletion is soft operational deactivation through `is_active`; tokens are revoked. Self-deactivation through the administrative endpoint is rejected.

The staff user list supports status, role, and activity filters. User resources are the canonical external representation.

## Audit logging

`AuditLog::record` captures module, action, entity identity, actor, field changes, description, and occurrence time. Administrative querying supports module, action, entity, actor, date range, and pagination filters. Do not store passwords, tokens, raw credentials, or unnecessary personal data in change payloads.

## Device and push notifications

Authenticated clients register Android or iOS device tokens. A token is reassigned to the current user when registered again. `PushNotificationService` sends through Firebase and the queued job keeps delivery latency outside primary transactions.

