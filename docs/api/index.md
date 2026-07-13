# API usage and conventions

All application API routes use the configured `/api/{version}` prefix; the current version is `v1`. The [interactive reference](reference.md) is generated from Laravel's route registry and maintained OpenAPI definitions.

## Conventions

- Send and receive JSON unless an endpoint explicitly documents another media type.
- Use `Authorization: Bearer <token>` for Sanctum-protected routes.
- Collection responses generally use Laravel resource collection envelopes and may include pagination metadata.
- Validation failures use HTTP 422 with a message and field errors.
- Not-found responses use HTTP 404.
- Authorization failures use HTTP 403; missing or invalid authentication uses HTTP 401.
- Identifiers in local API paths are local database identifiers unless the name explicitly says `shopify...`.

## Source of truth

The route-coverage script requires every registered API method/path pair to exist in `docs/api/openapi.yaml`. Existing detailed Sales and Workspace specifications are merged into that canonical document. Newly registered routes receive a generated baseline and must then be enriched with request and response schemas in the maintained source specifications.

The API server shown in the interactive page is a placeholder. Replace it with the target environment before using "Try it out". Never enter production credentials into an untrusted browser session.

