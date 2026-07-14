# Workspace module

## Projects and tasks

`ProjectService` provides project listing, lookup, creation, update, and deletion. `TaskService` owns task creation, hierarchy, updates, assignment changes, deletion, notifications, and related audit behavior. API resources serialize projects and tasks with their relevant relationships.

Task assignment changes notify affected users and may emit push notifications. Task mutations should remain in the service so API and future command-line entry points share the same invariants.

## Time tracking

Time entries belong to a task and user. The controller exposes daily totals, the current active entry, task history, start, and stop operations. Start/stop changes are broadcast in realtime. `TimeTrackingService::todayTotalSeconds` calculates the current user's daily total.

## Conversations and AI replies

Conversations contain participants and messages. `ConversationService` owns creation, retrieval, participant synchronization, and updates. Message access requires conversation membership. `MessageSent` broadcasts new messages, and `MessageReactionUpdated` broadcasts reaction changes for live chat clients.

When AI chat is enabled and a supported mention is detected, `GenerateAiChatReplyJob` gathers bounded history, calls `GeminiChatService`, persists the bot response, and broadcasts it. The AI identity is resolved by `ChatAiUserResolver`; deployments must seed or otherwise provide that user.

## Notifications

`NotificationService` creates one event with per-recipient deliveries, lists a user's deliveries, marks individual or all notifications read, and dismisses deliveries. Delivery and dismissal events support realtime client updates.

## Workspace resource

The `WorkspaceController` resource methods are currently scaffolded and several methods have no implementation. Treat these routes as incomplete despite being registered; do not build new dependencies on them until ownership, persistence, validation, and response behavior are implemented and tested.
