# Local setup

## Requirements

- PHP 8.3 or later compatible with `composer.json`
- Composer
- Node.js and npm for frontend assets
- MySQL 8.4
- Meilisearch
- Python 3.11 or later for documentation tooling

Laravel Sail is the supported containerized development path. The Compose stack provides the application, MySQL, and Meilisearch.

## Application setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

To start the normal development processes without Sail:

```bash
composer run dev
```

This starts the Laravel server, queue listener, Pail log viewer, and Vite development server. With Sail, start the infrastructure using `./vendor/bin/sail up -d` and run Artisan commands through Sail when the host PHP environment differs.

## Documentation setup

```bash
python3 -m venv .venv-docs
source .venv-docs/bin/activate
python -m pip install -r requirements-docs.txt
python scripts/docs/build_openapi.py
mkdocs serve
```

The local documentation URL is `http://127.0.0.1:8000/`.

## Initial verification

```bash
php artisan test
python scripts/docs/check_route_coverage.py
python scripts/docs/check_links.py
mkdocs build --strict
```

Do not use production credentials locally. Integration-dependent tests should use test credentials, fakes, or disabled feature flags.

