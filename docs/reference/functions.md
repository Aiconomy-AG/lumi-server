# Significant functions

This catalog covers public methods that carry business or integration behavior. Standard Eloquent relationships, resource `toArray` methods, framework provider hooks, and trivial accessors are intentionally omitted.

## Core services and controllers

| Class | Significant methods | Responsibility |
|---|---|---|
| `TokenController` | `store`, `destroy`, `me`, `updateStatus`, `ping`, `disconnect` | Token lifecycle and presence entry points |
| `PasswordResetController` | `validateToken`, `reset` | Password broker token validation and password replacement |
| `UserController` | `index`, `store`, `show`, `update`, `destroy`, `resendInvite` | Staff lookup and administrator lifecycle operations |
| `AuditLogController` | `index` | Filtered, paginated audit retrieval |
| `PresenceService` | `markAlive`, `markOffline`, `setManualStatus` | Presence state transitions and broadcasting |
| `PushNotificationService` | `sendToUser`, `sendToToken` | Firebase delivery and invalid-token handling |

## Sales services

| Class | Significant methods | Responsibility |
|---|---|---|
| `CartService` | `getCart`, `addItem`, `updateItem`, `removeItem` | Cart lifecycle and stock-aware item mutation |
| `WishlistService` | `getProducts`, `addProduct`, `removeProduct` | Customer product selections |
| `CheckoutService` | `processCheckout`, `getOrder`, `getCustomerOrders` | Transactional checkout and order retrieval |
| `ReturnService` | `createReturnFromOrder`, `createShopifyReturn`, `approveReturn`, `rejectReturn`, `markAsReceived`, `markAsRefunded` | Return creation and state machine |
| `ProductSyncService` | `create`, `update`, `sync`, `delete`, `seed`, queue and inventory methods | Local-to-Shopify product and inventory synchronization |
| `ShopifyConnector` | `query` | Authenticated Shopify GraphQL transport and error normalization |
| `ShopifyAccessTokenProvider` | `getAccessToken`, `invalidate` | Cached token lifecycle |
| `CollectionAssignService` | `queueAssign`, `reconcileAll`, `reconcileCategoryById`, `assignProducts`, `removeProducts` | Shopify collection membership |
| `AppProxyVerifier`, `WebhookVerifier` | `verify` | HMAC verification for external Shopify traffic |

## Workspace services

| Class | Significant methods | Responsibility |
|---|---|---|
| `ProjectService` | `getAll`, `create`, `getById`, `update`, `delete` | Project persistence operations |
| `TaskService` | `getAll`, `create`, `getById`, `update`, `delete`, `assignEmployees`, `removeEmployee` | Tasks, assignments, audit, and notifications |
| `ConversationService` | `getAllForUser`, `getById`, `create`, `update` | Conversation membership and lifecycle |
| `NotificationService` | `getForUser`, `createForRecipients`, `markAsRead`, `dismiss`, `markAllAsRead` | Notification events and deliveries |
| `TimeTrackingService` | `todayTotalSeconds` | Daily tracked-time aggregation |
| `GeminiChatService` | `generateReply` | Gemini prompt construction and response extraction |
| `ChatAiUserResolver` | `isEnabled`, `botUser`, `isBotUser`, `isBotEmail` | AI feature state and bot identity |
| `ChatMentionDetector` | `isMentioned`, `stripMentions` | AI mention parsing |

Review the current implementation before changing a method contract; this page is an orientation index, not a substitute for types, tests, or the API specification.
