# Runtime operations

## Required processes

| Process | Purpose |
|---|---|
| Web/PHP runtime | HTTP API and health endpoint |
| Queue worker | Push, Shopify, and AI jobs |
| Scheduler | Stale presence expiry and future scheduled work |
| Reverb server | WebSocket broadcasting when realtime features are enabled |
| MySQL | Primary persistence |
| Meilisearch | Product search |

Use a process supervisor or container orchestrator to restart failed long-running processes. Deployments must restart queue and Reverb processes after code or configuration changes.

## Health and logs

Laravel exposes `/up` for basic application health. A production readiness check should additionally verify database connectivity and, where required, queue backlog and external dependencies without leaking configuration.

Application logs use Laravel channels configured by `LOG_CHANNEL` and `LOG_LEVEL`. Correlate HTTP failures with failed jobs, Shopify response errors, mail delivery, Firebase errors, and Reverb connectivity.

## Useful commands

```bash
php artisan about
php artisan route:list
php artisan schedule:list
php artisan queue:failed
php artisan module:list
```

Run destructive, import, synchronization, or retry commands only after confirming the target environment and expected idempotency.

