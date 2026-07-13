# Authentication and authorization

## Authentication

`POST /api/v1/auth/login` validates credentials, rejects inactive users, and returns a Sanctum token. Authentication attempts are limited to five per minute per normalized email and IP address. Authenticated clients send:

```http
Authorization: Bearer <token>
Accept: application/json
```

Logout revokes the current token. Deactivating a user revokes all of that user's tokens. Password-reset endpoints use Laravel's password broker and the configured frontend reset URL.

## Roles

| Role | General capability |
|---|---|
| `admin` | User administration, destructive product operations, and all staff functions |
| `employee` | Staff catalog, order, return, and workspace operations |
| `client` | Authenticated customer and collaboration functions allowed by route ownership rules |

`EnsureUserIsStaff` requires an active administrator or employee. `EnsureUserIsAdmin` further limits the route to administrators. Product mutations also pass through `ProductPolicy`; deleting products and variants is administrator-only.

## Ownership and membership

- `VerifyCustomerOwnership` ensures the authenticated user owns the `{customerId}` used for cart, wishlist, profile, and order-list operations.
- `VerifyConversationParticipant` ensures the user belongs to the target conversation before messages or conversation updates are accessed.
- Order-detail access performs ownership checks in the controller.
- Private broadcast channels verify user identity or conversation membership.

## Shopify requests

Shopify app-proxy requests are HMAC verified using query parameters. Webhooks verify `X-Shopify-Hmac-Sha256` against the raw request body. Never bypass verification for debugging in a deployed environment.

