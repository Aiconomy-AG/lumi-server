# Troubleshooting

## API returns 401 or 403

- Confirm the bearer token is current and belongs to an active user.
- Check role middleware, customer ownership, conversation participation, and product policy requirements.
- For Shopify traffic, inspect signature inputs without logging the signing secret.

## Queue work is not occurring

- Confirm `QUEUE_CONNECTION` is correct and a worker is running.
- Check `php artisan queue:failed` and worker logs.
- Restart workers after deployments or configuration changes.
- Verify external credentials and rate-limit responses.

## Realtime updates are missing

- Confirm Reverb is running and client host, port, scheme, key, and TLS settings agree.
- Verify `/api/v1/broadcasting/auth` succeeds with the same token.
- Check channel authorization and conversation membership.
- Confirm the event implements broadcasting and the queue mode used by broadcasts is processing.

## Product search differs from the database

- Confirm Meilisearch health and Scout configuration.
- Re-index products using the applicable Scout command after reviewing production impact.
- Check whether synchronization updated MySQL but failed before search indexing.

## Shopify synchronization fails

- Run the connection-test command and inspect normalized Shopify errors.
- Check API-version support, scopes, cached token state, throttling, and queue failures.
- Confirm local Shopify IDs use the expected numeric or GID form.

## AI replies do not appear

- Confirm the feature flag, bot user, Gemini key/model, queue worker, and mention syntax.
- Inspect the AI job failure without logging conversation content unnecessarily.
- Normal messaging should continue even when Gemini is unavailable.

