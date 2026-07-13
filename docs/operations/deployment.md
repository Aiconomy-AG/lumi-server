# Deployment

## Application deployment

1. Build and test the exact revision being released.
2. Install production Composer dependencies with an optimized autoloader.
3. Build frontend assets if the deployment serves Laravel views.
4. Provide environment configuration through the deployment platform.
5. Put the application into maintenance mode when the migration risk requires it.
6. Run forward migrations with `php artisan migrate --force`.
7. Rebuild Laravel caches appropriate to the environment.
8. Restart PHP, queue workers, scheduler, and Reverb.
9. Verify `/up`, authentication, representative API operations, queues, and realtime connectivity.
10. Confirm Shopify callback URLs when the hostname or API version changes.

Rollback application code only when its schema expectations remain compatible with migrated data. Prefer forward-fix migrations when destructive rollback would lose information.

## Documentation deployment

Changes pushed to `main` run the documentation workflow. The workflow regenerates OpenAPI and the route inventory, validates route coverage and links, builds MkDocs in strict mode, and deploys the `site/` artifact through GitHub Pages.

Published documentation: `https://aiconomy-ag.github.io/lumi-server/`

