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

Do not commit environment files, credentials, service-account documents, tokens, or generated documentation output.
