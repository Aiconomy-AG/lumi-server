# Shopify integration

## Outbound synchronization

`ShopifyConnector` sends GraphQL requests using credentials provided by `ShopifyAccessTokenProvider`. Products, variants, inventory, and collection memberships are synchronized through services, commands, and queued jobs. Shopify GIDs are normalized through `ShopifyId` helpers.

Commands include connection testing, product synchronization, inventory synchronization, webhook registration, CSV import, and collection assignment. Run each command with `--help` in the target release to see supported arguments.

## App proxy

Cart, wishlist, and return proxy endpoints accept Shopify storefront traffic. `VerifyShopifyProxySignature` or `AppProxyVerifier` validates signed query parameters before resolving a customer or changing data. Proxy URLs must match the configured Shopify application URL and subpath.

## Customer-account returns

Customer-account endpoints look up locally synchronized orders, submit returns, and list returns for an order. These routes currently perform controller-level validation and should be reviewed carefully if the Shopify customer-account authentication model changes.

## Webhooks

Customer create/update, order create/update, and product update webhooks validate the raw-body HMAC. Order webhooks upsert the customer and order and replace synchronized line items within a database transaction. Product webhooks update matching local products.

Webhook handlers must remain idempotent because Shopify may retry delivery. After changing the API version or public hostname, re-register webhook and proxy URLs in Shopify.

## Operational checks

1. Run the Shopify connection-test command.
2. Confirm the configured API version is supported by Shopify.
3. Confirm queue workers are processing synchronization jobs.
4. Inspect failed jobs and application logs for throttling or GraphQL errors.
5. Verify webhook HMAC failures before replaying events.

