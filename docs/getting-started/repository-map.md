# Repository map

| Location | Contents |
|---|---|
| `app/` | Shared application code: users, authentication, audit, presence, device tokens, mail, push notifications |
| `Modules/Sales/` | Sales domain code, migrations, configuration, routes, and tests |
| `Modules/Workspace/` | Collaboration domain code, migrations, routes, and tests |
| `routes/` | Core API, web, console schedule, and broadcast-channel routes |
| `database/` | Core migrations, factories, and seeders |
| `config/` | Application and integration configuration |
| `docs/` | This documentation and OpenAPI source files |
| `scripts/docs/` | OpenAPI generation, coverage, and link validation |
| `tests/` | Core feature and unit tests; module tests live inside each module |

Modules are loaded by `nwidart/laravel-modules`. Each module registers its own route and event providers. All API route providers use the same configured `api/{version}` prefix.

Generated site output belongs in `site/` and is not an application runtime dependency.

