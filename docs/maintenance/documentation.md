# Documentation maintenance

Documentation changes are required when routes, validation, response resources, permissions, data relationships, jobs, events, commands, integrations, environment keys, or deployment procedures change.

## Workflow

1. Update the relevant Markdown architecture or functional page.
2. Update `Shop.yaml` or `Workspace.yaml` when an existing detailed API contract changes.
3. Run `python scripts/docs/build_openapi.py` to regenerate the canonical specification and route inventory.
4. Enrich any generated baseline operation with exact request fields, response schemas, status codes, and examples.
5. Run route coverage, link checking, Redocly linting, and `mkdocs build --strict`.
6. Review the rendered site locally.

The generator treats Laravel's registered routes as the route source of truth. Route coverage does not prove schema accuracy; code review must compare Form Requests, inline validation, controllers, resources, transformers, policies, and feature tests.

Do not publish credentials, real tokens, private hostnames, customer data, raw webhook bodies, or sensitive recovery procedures.

