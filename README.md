# Lumi Server

Laravel backend for Lumi's sales, Shopify, administration, and workspace functions.

## Documentation

The complete maintenance documentation is published at:

https://aiconomy-ag.github.io/lumi-server/

It includes local setup, architecture, data ownership, API contracts, significant functions, integrations, background processing, deployment, testing, and troubleshooting.

To run the documentation locally:

```bash
python3 -m venv .venv-docs
source .venv-docs/bin/activate
python -m pip install -r requirements-docs.txt
python scripts/docs/build_openapi.py
mkdocs serve
```

The local site is available at `http://127.0.0.1:8000/`.

After manually updating the documentation, regenerate, validate, commit, push,
and wait for publication with one command:

```bash
./scripts/docs/update_and_publish.sh
```

To provide a custom commit message:

```bash
./scripts/docs/update_and_publish.sh "docs: describe the latest backend changes"
```

The command intentionally stops if it finds uncommitted application changes.
Commit or stash application changes before publishing the documentation.

## Application setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Run the application development processes with `composer run dev`. Run the test suite with `composer test`.

## Global search indexes

Global search uses Laravel Scout with Meilisearch. Configure `SCOUT_DRIVER`, `SCOUT_QUEUE=false`, `MEILISEARCH_HOST`, and `MEILISEARCH_KEY` in `.env`, then sync indexes:

```bash
php artisan scout:sync-index-settings
php artisan scout:import "Modules\\Sales\\Models\\Product"
php artisan scout:import "Modules\\Workspace\\Models\\Task"
php artisan scout:import "Modules\\Workspace\\Models\\Project"
php artisan scout:import "Modules\\Sales\\Models\\Order"
php artisan scout:import "Modules\\Sales\\Models\\ReturnRequest"
php artisan scout:import "App\\Models\\User"
```

For local Sail development, use `MEILISEARCH_HOST=http://meilisearch:7700`. For local development without Meilisearch, set `SCOUT_DRIVER=collection`.

Do not commit environment files, credentials, service-account documents, tokens, or generated documentation output.
