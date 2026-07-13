# Testing

Tests are divided between the root application and each module. The suite includes authentication, user administration, audit logs, presence, device tokens, push notifications, catalog, cart, checkout, orders, returns, Shopify proxy/webhook behavior, synchronization jobs, projects, tasks, time tracking, conversations, messages, notifications, and AI reply behavior.

## Commands

```bash
composer test
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

Run a focused file or filter while developing, then run the full suite before deployment.

## Documentation checks

```bash
python scripts/docs/build_openapi.py
python scripts/docs/check_route_coverage.py
python scripts/docs/check_links.py
npx --yes @redocly/cli lint docs/api/openapi.yaml
mkdocs build --strict
```

Feature tests should assert authentication, authorization, validation, response shape, persistence, audit behavior, dispatched jobs/events, and important failure paths. External services should be faked unless the test is explicitly an integration test.

