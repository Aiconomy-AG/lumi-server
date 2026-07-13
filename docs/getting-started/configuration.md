# Configuration

Configuration is read from `.env` through Laravel configuration files. Never commit `.env`, service-account JSON, tokens, passwords, or signing secrets.

## Application and API

| Variable | Purpose | Typical requirement |
|---|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Runtime environment, error visibility, and canonical backend URL | Always |
| `FRONTEND_URL` | Password-reset and invitation destination | User administration |
| `API_VERSION` | Prefix used by core and module route providers; defaults to `v1` | Optional |
| `APP_KEY` | Laravel encryption and signing key | Always |

## Persistence and workers

| Variables | Purpose |
|---|---|
| `DB_*` | MySQL connection |
| `CACHE_STORE` | Cache, including Shopify access-token caching |
| `QUEUE_CONNECTION` | Background jobs; production must not use an unmanaged synchronous worker |
| `SESSION_*` | Session behavior for web routes |
| `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | Product search index |

## Realtime and presence

| Variables | Purpose |
|---|---|
| `BROADCAST_CONNECTION` | Broadcast driver |
| `REVERB_*` | Reverb application credentials and server endpoint |
| `PRESENCE_HEARTBEAT_INTERVAL_SECONDS` | Recommended client heartbeat interval; default 25 seconds |
| `PRESENCE_OFFLINE_TTL_SECONDS` | Stale-presence threshold; default 90 seconds |

## External services

| Variables | Purpose |
|---|---|
| `SHOPIFY_ADMIN_ID`, `SHOPIFY_ADMIN_SECRET`, `SHOPIFY_SHOP`, `SHOPIFY_API_VERSION` | Shopify Admin API authentication and target shop |
| `SHOPIFY_WEBHOOK_SECRET`, `SHOPIFY_RETURNS_APP_SECRET` | Webhook and proxy signature verification |
| `SHOPIFY_APP_URL`, `SHOPIFY_PROXY_SUBPATH` | Public callback and app-proxy routing |
| `SHOPIFY_PUBLISH_PRODUCTS`, `SHOPIFY_ONLINE_STORE_PUBLICATION_ID` | Product publication behavior |
| `FIREBASE_CREDENTIALS` or `GOOGLE_APPLICATION_CREDENTIALS` | Firebase Admin service account location |
| `MAIL_*`, `RESEND_KEY` | Invitation and password-reset mail delivery |
| `CHAT_AI_ENABLED` | Master switch for AI conversation replies; defaults to false |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Gemini authentication and model selection |
| `CHAT_AI_USER_EMAIL`, `CHAT_AI_USER_NAME`, `CHAT_AI_HISTORY_LIMIT` | AI user identity and conversation context limit |

After changing environment values in a deployed system, clear or rebuild Laravel's configuration cache and restart long-running workers so they load the new configuration.

