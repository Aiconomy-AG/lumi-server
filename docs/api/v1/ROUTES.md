# API Route Overview — `/api/v1`

All API routes are served under the `api/{version}` prefix. The version comes from
`config('app.api_version')` (env `API_VERSION`, default `v1`) and is applied by
`bootstrap/app.php` and each module's `RouteServiceProvider` — route files never
hardcode the version.

## Access levels

| Level | Meaning |
|---|---|
| Public | No authentication |
| Shopify | Public URL, but every request is rejected without a valid Shopify HMAC signature |
| Authenticated | Any user with a valid Sanctum token |
| Owner | Authenticated + `VerifyCustomerOwnership`: the `{customerId}` must belong to the token's user |
| Participant | Authenticated + `VerifyConversationParticipant`: user must be a member of the conversation |
| Staff | Admins and Employees (`EnsureUserIsStaff` blocks Clients and inactive users) |
| Admin | Admins only |

Roles: `admin`, `employee`, `client` (`App\Enums\UserRole`).

**Product permissions** (`Modules\Sales\Policies\ProductPolicy`): Employees and
Admins can create/update products, manage variants, and update stock.
**Deleting products or variants is Admin-only.** Admins additionally manage the
team (users) — see the Admin/Users section.

---

## Auth — `routes/api.php`

| Method | Route | Access |
|---|---|---|
| POST | `/api/v1/auth/login` | Public (throttled) |
| GET | `/api/v1/auth/reset-password/validate` | Public (throttled) |
| POST | `/api/v1/auth/reset-password` | Public (throttled) |
| DELETE | `/api/v1/auth/logout` | Authenticated |
| GET | `/api/v1/auth/me` | Authenticated |
| PATCH | `/api/v1/auth/me/status` | Authenticated |
| GET/POST | `/api/v1/broadcasting/auth` | Authenticated |

## Users — `routes/api.php`

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/users` | Staff |
| GET | `/api/v1/admin/users` | Admin |
| POST | `/api/v1/admin/users` | Admin |
| GET | `/api/v1/admin/users/{user}` | Admin |
| PUT/PATCH | `/api/v1/admin/users/{user}` | Admin |
| DELETE | `/api/v1/admin/users/{user}` | Admin |
| POST | `/api/v1/admin/users/{userId}/resend-invite` | Admin |

## Shop Catalog — `Modules/Sales` (public storefront reads)

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/shop/products` | Public |
| GET | `/api/v1/shop/products/{productId}` | Public |
| GET | `/api/v1/shop/products/{productId}/variants` | Public |
| GET | `/api/v1/shop/products/{productId}/ingredients` | Public |
| GET | `/api/v1/shop/categories` | Public |
| GET | `/api/v1/shop/ingredients` | Public |
| GET | `/api/v1/shop/ingredients/{ingredientId}` | Public |
| GET | `/api/v1/shop/variants/{variantId}` | Public |

## Shop Customer Area — `Modules/Sales`

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/shop/me` | Authenticated |
| GET | `/api/v1/shop/customers/{customerId}` | Owner |
| GET | `/api/v1/shop/customers/{customerId}/cart` | Owner |
| POST | `/api/v1/shop/customers/{customerId}/cart/items` | Owner |
| PUT | `/api/v1/shop/customers/{customerId}/cart/items/{productVariantId}` | Owner |
| DELETE | `/api/v1/shop/customers/{customerId}/cart/items/{productVariantId}` | Owner |
| GET | `/api/v1/shop/customers/{customerId}/wishlist` | Owner |
| POST | `/api/v1/shop/customers/{customerId}/wishlist` | Owner |
| DELETE | `/api/v1/shop/customers/{customerId}/wishlist/{productId}` | Owner |
| GET | `/api/v1/shop/customers/{customerId}/orders` | Owner |
| POST | `/api/v1/shop/orders` | Authenticated |
| GET | `/api/v1/shop/orders/{orderId}` | Authenticated (ownership checked in controller) |

## Admin: Products & Variants — `Modules/Sales`

Routes require Staff; `ProductPolicy` decides per action.

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/admin/products` | Staff |
| POST | `/api/v1/admin/products` | Staff (Employee + Admin) |
| PUT | `/api/v1/admin/products/{productId}` | Staff (Employee + Admin) |
| DELETE | `/api/v1/admin/products/{productId}` | Admin |
| POST | `/api/v1/admin/products/{productId}/variants` | Staff (Employee + Admin) |
| PUT | `/api/v1/admin/products/{productId}/variants/{variantId}` | Staff (Employee + Admin) |
| PATCH | `/api/v1/admin/products/{productId}/variants/{variantId}` (stock) | Staff (Employee + Admin) |
| DELETE | `/api/v1/admin/products/{productId}/variants/{variantId}` | Admin |

## Admin: Orders & Returns — `Modules/Sales`

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/admin/orders` | Staff |
| GET | `/api/v1/admin/orders/{orderId}` | Staff |
| GET | `/api/v1/admin/returns` | Staff |
| GET | `/api/v1/admin/returns/{returnRequestId}` | Staff |
| PATCH | `/api/v1/admin/returns/{returnRequestId}` | Staff (notes only) |
| POST | `/api/v1/admin/returns/{returnRequestId}/approve` | Staff |
| POST | `/api/v1/admin/returns/{returnRequestId}/reject` | Staff |
| POST | `/api/v1/admin/returns/{returnRequestId}/received` | Staff |
| POST | `/api/v1/admin/returns/{returnRequestId}/refunded` | Staff |

## Shopify Integrations — `Modules/Sales`

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/shopify/proxy/wishlist` | Shopify |
| POST | `/api/v1/shopify/proxy/wishlist/items` | Shopify |
| DELETE | `/api/v1/shopify/proxy/wishlist/items/{shopifyProductId}` | Shopify |
| POST | `/api/v1/shopify/proxy/returns` | Shopify |
| POST | `/api/v1/shopify/webhooks/customers/create` | Shopify |
| POST | `/api/v1/shopify/webhooks/customers/update` | Shopify |
| POST | `/api/v1/shopify/webhooks/orders/create` | Shopify |
| POST | `/api/v1/shopify/webhooks/orders/updated` | Shopify |
| POST | `/api/v1/shopify/webhooks/products/update` | Shopify |

> After deploying the `/v1` rename, update the webhook and app-proxy URLs in the
> Shopify admin to the new `/api/v1/shopify/...` paths.

## Workspace — `Modules/Workspace`

All Workspace routes require an authenticated user (any role).

| Method | Route | Access |
|---|---|---|
| GET | `/api/v1/workspaces` | Authenticated |
| POST | `/api/v1/workspaces` | Authenticated |
| GET | `/api/v1/workspaces/{workspace}` | Authenticated |
| PUT/PATCH | `/api/v1/workspaces/{workspace}` | Authenticated |
| DELETE | `/api/v1/workspaces/{workspace}` | Authenticated |
| GET | `/api/v1/workspace/projects` | Authenticated |
| POST | `/api/v1/workspace/projects` | Authenticated |
| GET | `/api/v1/workspace/projects/{projectId}` | Authenticated |
| PUT | `/api/v1/workspace/projects/{projectId}` | Authenticated |
| DELETE | `/api/v1/workspace/projects/{projectId}` | Authenticated |
| GET | `/api/v1/workspace/tasks` | Authenticated |
| POST | `/api/v1/workspace/tasks` | Authenticated |
| GET | `/api/v1/workspace/tasks/{taskId}` | Authenticated |
| PUT | `/api/v1/workspace/tasks/{taskId}` | Authenticated |
| DELETE | `/api/v1/workspace/tasks/{taskId}` | Authenticated |
| POST | `/api/v1/workspace/tasks/{taskId}/assignees` | Authenticated |
| DELETE | `/api/v1/workspace/tasks/{taskId}/assignees/{employeeId}` | Authenticated |
| GET | `/api/v1/workspace/tasks/{taskId}/time-entries` | Authenticated |
| POST | `/api/v1/workspace/tasks/{taskId}/time-entries/start` | Authenticated |
| POST | `/api/v1/workspace/tasks/{taskId}/time-entries/{entryId}/stop` | Authenticated |
| GET | `/api/v1/workspace/conversations` | Authenticated |
| POST | `/api/v1/workspace/conversations` | Authenticated |
| GET | `/api/v1/workspace/conversations/{conversationId}` | Authenticated |
| GET | `/api/v1/workspace/conversations/{conversationId}/messages` | Participant |
| POST | `/api/v1/workspace/conversations/{conversationId}/messages` | Participant |
| GET | `/api/v1/workspace/notifications` | Authenticated |
| PUT | `/api/v1/workspace/notifications/read-all` | Authenticated |
| PUT | `/api/v1/workspace/notifications/{notificationId}/read` | Authenticated |
