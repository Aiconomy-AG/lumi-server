# Asynchronous processing

## Queue jobs

| Job | Responsibility |
|---|---|
| `SendPushNotificationJob` | Deliver Firebase notifications outside the request path |
| `GenerateAiChatReplyJob` | Build conversation context, call Gemini, and persist/broadcast the AI response |
| `ShopifySyncJob` | Coordinate Shopify synchronization work |
| `SyncShopifyProductJob` | Synchronize one product |
| `DeleteShopifyProductJob` | Delete the corresponding Shopify product |
| `SyncShopifyInventoryJob` | Update Shopify inventory for a variant |
| `AssignShopifyCollectionJob` | Reconcile product membership in Shopify collections |

Run a supervised queue worker in every non-local environment. Restart workers after deployments so they load current code and configuration. Monitor failed jobs and external API throttling.

## Broadcast events

Time-entry start/stop, message delivery, notification delivery/dismissal, and user-status changes broadcast to private or presence channels. Channel authorization lives in `routes/channels.php`.

## Scheduler

`presence:expire-stale` runs every minute and marks users offline when their last heartbeat exceeds the configured TTL. Production must execute Laravel's scheduler continuously, normally with `php artisan schedule:work` or a once-per-minute `schedule:run` trigger.

