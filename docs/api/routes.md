# Route inventory

This page is generated from `php artisan route:list --json`. The canonical OpenAPI document contains request and response details.

Registered API operations: **113**.

## Audit logs

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/admin/audit-logs` | Administrator | `App\Http\Controllers\Admin\AuditLogController@index` |

## Authentication and presence

| Method | Path | Access | Handler |
|---|---|---|---|
| `POST` | `/auth/login` | Public | `App\Http\Controllers\Auth\TokenController@store` |
| `DELETE` | `/auth/logout` | Authenticated user | `App\Http\Controllers\Auth\TokenController@destroy` |
| `GET` | `/auth/me` | Authenticated user | `App\Http\Controllers\Auth\TokenController@me` |
| `POST` | `/auth/me/presence/disconnect` | Public | `App\Http\Controllers\Auth\TokenController@disconnect` |
| `POST` | `/auth/me/presence/ping` | Authenticated user | `App\Http\Controllers\Auth\TokenController@ping` |
| `PATCH` | `/auth/me/status` | Authenticated user | `App\Http\Controllers\Auth\TokenController@updateStatus` |
| `PUT` | `/auth/phone` | Authenticated user | `App\Http\Controllers\Api\ProfileController@updatePhone` |
| `POST` | `/auth/reset-password` | Public | `App\Http\Controllers\Auth\PasswordResetController@reset` |
| `GET` | `/auth/reset-password/validate` | Public | `App\Http\Controllers\Auth\PasswordResetController@validateToken` |
| `GET` | `/broadcasting/auth` | Authenticated user | `Illuminate\Broadcasting\BroadcastController@authenticate` |
| `POST` | `/broadcasting/auth` | Authenticated user | `Illuminate\Broadcasting\BroadcastController@authenticate` |

## Conversations

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/workspace/conversations` | Authenticated user | `Modules\Workspace\Http\Controllers\ConversationController@index` |
| `POST` | `/workspace/conversations` | Authenticated user | `Modules\Workspace\Http\Controllers\ConversationController@store` |
| `GET` | `/workspace/conversations/{conversationId}` | Authenticated user | `Modules\Workspace\Http\Controllers\ConversationController@show` |
| `PUT` | `/workspace/conversations/{conversationId}` | Authenticated conversation participant | `Modules\Workspace\Http\Controllers\ConversationController@update` |
| `GET` | `/workspace/conversations/{conversationId}/messages` | Authenticated conversation participant | `Modules\Workspace\Http\Controllers\MessageController@index` |
| `POST` | `/workspace/conversations/{conversationId}/messages` | Authenticated conversation participant | `Modules\Workspace\Http\Controllers\MessageController@store` |

## Device tokens

| Method | Path | Access | Handler |
|---|---|---|---|
| `DELETE` | `/device-tokens` | Authenticated user | `App\Http\Controllers\DeviceTokenController@destroy` |
| `DELETE` | `/device-tokens/{deviceTokenId}` | Authenticated user (owner) | `App\Http\Controllers\DeviceTokenController@destroyById` |
| `POST` | `/device-tokens` | Authenticated user | `App\Http\Controllers\DeviceTokenController@store` |

## VoIP calls

| Method | Path | Access | Handler |
|---|---|---|---|
| `POST` | `/calls` | Staff | `Modules\Workspace\Http\Controllers\CallController@create` |
| `GET` | `/calls/active` | Staff | `Modules\Workspace\Http\Controllers\CallController@active` |
| `GET` | `/calls/history` | Staff | `Modules\Workspace\Http\Controllers\CallController@history` |
| `GET` | `/calls/{callId}` | Staff | `Modules\Workspace\Http\Controllers\CallController@show` |
| `POST` | `/calls/{callId}/accept` | Staff | `Modules\Workspace\Http\Controllers\CallController@accept` |
| `POST` | `/calls/{callId}/decline` | Staff | `Modules\Workspace\Http\Controllers\CallController@decline` |
| `POST` | `/calls/{callId}/cancel` | Staff | `Modules\Workspace\Http\Controllers\CallController@cancel` |
| `POST` | `/calls/{callId}/leave` | Staff | `Modules\Workspace\Http\Controllers\CallController@leave` |
| `POST` | `/calls/{callId}/invite` | Staff | `Modules\Workspace\Http\Controllers\CallController@invite` |
| `POST` | `/calls/{callId}/end` | Staff | `Modules\Workspace\Http\Controllers\CallController@end` |
| `POST` | `/webhooks/livekit` | Public (signed) | `Modules\Workspace\Http\Controllers\LiveKitWebhookController` |
| `POST` | `/workspace/conversations/{conversationId}/calls` | Staff conversation participant | `Modules\Workspace\Http\Controllers\CallController@store` |
| `GET` | `/workspace/calls/active` | Staff | `Modules\Workspace\Http\Controllers\CallController@active` |
| `GET` | `/workspace/calls/history` | Staff | `Modules\Workspace\Http\Controllers\CallController@history` |
| `GET` | `/workspace/calls/{callId}` | Staff | `Modules\Workspace\Http\Controllers\CallController@show` |
| `POST` | `/workspace/calls/{callId}/accept` | Staff | `Modules\Workspace\Http\Controllers\CallController@accept` |
| `POST` | `/workspace/calls/{callId}/decline` | Staff | `Modules\Workspace\Http\Controllers\CallController@decline` |
| `POST` | `/workspace/calls/{callId}/cancel` | Staff | `Modules\Workspace\Http\Controllers\CallController@cancel` |
| `POST` | `/workspace/calls/{callId}/leave` | Staff | `Modules\Workspace\Http\Controllers\CallController@leave` |
| `POST` | `/workspace/calls/{callId}/invite` | Staff | `Modules\Workspace\Http\Controllers\CallController@invite` |
| `POST` | `/workspace/calls/{callId}/end` | Staff | `Modules\Workspace\Http\Controllers\CallController@end` |

## Notifications

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/workspace/notifications` | Authenticated user | `Modules\Workspace\Http\Controllers\NotificationController@index` |
| `PUT` | `/workspace/notifications/read-all` | Authenticated user | `Modules\Workspace\Http\Controllers\NotificationController@markAllAsRead` |
| `DELETE` | `/workspace/notifications/{notificationId}` | Authenticated user | `Modules\Workspace\Http\Controllers\NotificationController@dismiss` |
| `PUT` | `/workspace/notifications/{notificationId}/read` | Authenticated user | `Modules\Workspace\Http\Controllers\NotificationController@markAsRead` |

## Order administration

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/admin/orders` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\OrderController@index` |
| `GET` | `/admin/orders/{orderId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\OrderController@show` |

## Product administration

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/admin/products` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductController@index` |
| `POST` | `/admin/products` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductController@store` |
| `DELETE` | `/admin/products/{productId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductController@destroy` |
| `PUT` | `/admin/products/{productId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductController@update` |
| `POST` | `/admin/products/{productId}/variants` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductVariantController@store` |
| `DELETE` | `/admin/products/{productId}/variants/{variantId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductVariantController@destroy` |
| `PATCH` | `/admin/products/{productId}/variants/{variantId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductVariantController@updateStock` |
| `PUT` | `/admin/products/{productId}/variants/{variantId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ProductVariantController@update` |

## Projects

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/workspace/projects` | Authenticated user | `Modules\Workspace\Http\Controllers\ProjectController@index` |
| `POST` | `/workspace/projects` | Authenticated user | `Modules\Workspace\Http\Controllers\ProjectController@store` |
| `DELETE` | `/workspace/projects/{projectId}` | Authenticated user | `Modules\Workspace\Http\Controllers\ProjectController@destroy` |
| `GET` | `/workspace/projects/{projectId}` | Authenticated user | `Modules\Workspace\Http\Controllers\ProjectController@show` |
| `PUT` | `/workspace/projects/{projectId}` | Authenticated user | `Modules\Workspace\Http\Controllers\ProjectController@update` |

## Return administration

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/admin/returns` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@index` |
| `GET` | `/admin/returns/{returnRequestId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@show` |
| `PATCH` | `/admin/returns/{returnRequestId}` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@updateNotes` |
| `POST` | `/admin/returns/{returnRequestId}/approve` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@approve` |
| `POST` | `/admin/returns/{returnRequestId}/received` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@markReceived` |
| `POST` | `/admin/returns/{returnRequestId}/refunded` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@markRefunded` |
| `POST` | `/admin/returns/{returnRequestId}/reject` | Active administrator or employee | `Modules\Sales\Http\Controllers\Admin\ReturnRequestController@reject` |
| `GET` | `/workspace/returns` | Active administrator or employee | `Modules\Workspace\Http\Controllers\ReturnRequestController@index` |
| `GET` | `/workspace/returns/{returnRequestId}` | Active administrator or employee | `Modules\Workspace\Http\Controllers\ReturnRequestController@show` |
| `PATCH` | `/workspace/returns/{returnRequestId}` | Active administrator or employee | `Modules\Workspace\Http\Controllers\ReturnRequestController@update` |

## Shopify proxy cart

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/shopify/proxy/cart` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyCartController@show` |
| `POST` | `/shopify/proxy/cart/items` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyCartController@storeItem` |
| `DELETE` | `/shopify/proxy/cart/items/{productVariantId}` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyCartController@destroyItem` |
| `PUT` | `/shopify/proxy/cart/items/{productVariantId}` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyCartController@updateItem` |

## Shopify proxy wishlist

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/shopify/proxy/wishlist` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyWishlistController@index` |
| `POST` | `/shopify/proxy/wishlist` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyWishlistController@store` |
| `DELETE` | `/shopify/proxy/wishlist/items/{shopifyProductId}` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyWishlistController@destroy` |

## Shopify returns

| Method | Path | Access | Handler |
|---|---|---|---|
| `POST` | `/shopify/customer-account/returns` | Public | `Modules\Sales\Http\Controllers\Shopify\ProxyReturnController@storeFromCustomerAccount` |
| `POST` | `/shopify/customer-account/returns/by-order` | Public | `Modules\Sales\Http\Controllers\Shopify\ProxyReturnController@indexByOrderFromCustomerAccount` |
| `POST` | `/shopify/customer-account/returns/lookup` | Public | `Modules\Sales\Http\Controllers\Shopify\ProxyReturnController@lookupFromCustomerAccount` |
| `POST` | `/shopify/proxy/returns` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyReturnController@store` |
| `GET` | `/shopify/proxy/returns/ping` | Shopify signed request | `Modules\Sales\Http\Controllers\Shopify\ProxyReturnController@ping` |

## Shopify webhooks

| Method | Path | Access | Handler |
|---|---|---|---|
| `POST` | `/shopify/webhooks/customers/create` | Shopify HMAC | `Modules\Sales\Http\Controllers\Shopify\WebhookController@customer` |
| `POST` | `/shopify/webhooks/customers/update` | Shopify HMAC | `Modules\Sales\Http\Controllers\Shopify\WebhookController@customer` |
| `POST` | `/shopify/webhooks/orders/create` | Shopify HMAC | `Modules\Sales\Http\Controllers\Shopify\WebhookController@order` |
| `POST` | `/shopify/webhooks/orders/updated` | Shopify HMAC | `Modules\Sales\Http\Controllers\Shopify\WebhookController@order` |
| `POST` | `/shopify/webhooks/products/update` | Shopify HMAC | `Modules\Sales\Http\Controllers\Shopify\WebhookController@product` |

## Storefront

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/shop/categories` | Public | `Modules\Sales\Http\Controllers\CatalogController@categories` |
| `GET` | `/shop/customers/{customerId}` | Authenticated customer owner | `Modules\Sales\Http\Controllers\CustomerController@show` |
| `GET` | `/shop/customers/{customerId}/cart` | Authenticated customer owner | `Modules\Sales\Http\Controllers\CartController@show` |
| `POST` | `/shop/customers/{customerId}/cart/items` | Authenticated customer owner | `Modules\Sales\Http\Controllers\CartController@storeItem` |
| `DELETE` | `/shop/customers/{customerId}/cart/items/{productVariantId}` | Authenticated customer owner | `Modules\Sales\Http\Controllers\CartController@destroyItem` |
| `PUT` | `/shop/customers/{customerId}/cart/items/{productVariantId}` | Authenticated customer owner | `Modules\Sales\Http\Controllers\CartController@updateItem` |
| `GET` | `/shop/customers/{customerId}/orders` | Authenticated customer owner | `Modules\Sales\Http\Controllers\CheckoutController@customerOrders` |
| `GET` | `/shop/customers/{customerId}/wishlist` | Authenticated customer owner | `Modules\Sales\Http\Controllers\WishlistController@index` |
| `POST` | `/shop/customers/{customerId}/wishlist` | Authenticated customer owner | `Modules\Sales\Http\Controllers\WishlistController@store` |
| `DELETE` | `/shop/customers/{customerId}/wishlist/{productId}` | Authenticated customer owner | `Modules\Sales\Http\Controllers\WishlistController@destroy` |
| `GET` | `/shop/ingredients` | Public | `Modules\Sales\Http\Controllers\CatalogController@ingredients` |
| `GET` | `/shop/ingredients/{ingredientId}` | Public | `Modules\Sales\Http\Controllers\CatalogController@ingredientDetails` |
| `GET` | `/shop/me` | Authenticated user | `Modules\Sales\Http\Controllers\CustomerController@me` |
| `POST` | `/shop/orders` | Authenticated user | `Modules\Sales\Http\Controllers\CheckoutController@store` |
| `GET` | `/shop/orders/{orderId}` | Authenticated user | `Modules\Sales\Http\Controllers\CheckoutController@show` |
| `GET` | `/shop/products` | Public | `Modules\Sales\Http\Controllers\CatalogController@index` |
| `GET` | `/shop/products/search` | Public | `Modules\Sales\Http\Controllers\ProductSearchController` |
| `GET` | `/shop/products/{productId}` | Public | `Modules\Sales\Http\Controllers\CatalogController@show` |
| `GET` | `/shop/products/{productId}/ingredients` | Public | `Modules\Sales\Http\Controllers\CatalogController@productIngredients` |
| `GET` | `/shop/products/{productId}/variants` | Public | `Modules\Sales\Http\Controllers\CatalogController@productVariants` |
| `GET` | `/shop/variants/{variantId}` | Public | `Modules\Sales\Http\Controllers\CatalogController@variantDetails` |

## Tasks and time tracking

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/workspace/me/active-time-entry` | Authenticated user | `Modules\Workspace\Http\Controllers\TimeTrackingController@active` |
| `GET` | `/workspace/tasks` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@index` |
| `POST` | `/workspace/tasks` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@store` |
| `DELETE` | `/workspace/tasks/{taskId}` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@destroy` |
| `GET` | `/workspace/tasks/{taskId}` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@show` |
| `PUT` | `/workspace/tasks/{taskId}` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@update` |
| `POST` | `/workspace/tasks/{taskId}/assignees` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@assignEmployees` |
| `DELETE` | `/workspace/tasks/{taskId}/assignees/{employeeId}` | Authenticated user | `Modules\Workspace\Http\Controllers\TaskController@removeEmployee` |
| `GET` | `/workspace/tasks/{taskId}/time-entries` | Authenticated user | `Modules\Workspace\Http\Controllers\TimeTrackingController@index` |
| `POST` | `/workspace/tasks/{taskId}/time-entries/start` | Authenticated user | `Modules\Workspace\Http\Controllers\TimeTrackingController@start` |
| `POST` | `/workspace/tasks/{taskId}/time-entries/{entryId}/stop` | Authenticated user | `Modules\Workspace\Http\Controllers\TimeTrackingController@stop` |
| `GET` | `/workspace/users/{userId}/time-entries/daily-total` | Authenticated user | `Modules\Workspace\Http\Controllers\TimeTrackingController@dailyTotal` |

## User administration

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/admin/users` | Administrator | `App\Http\Controllers\Admin\UserController@index` |
| `POST` | `/admin/users` | Administrator | `App\Http\Controllers\Admin\UserController@store` |
| `POST` | `/admin/users/{userId}/resend-invite` | Administrator | `App\Http\Controllers\Admin\UserController@resendInvite` |
| `DELETE` | `/admin/users/{user}` | Administrator | `App\Http\Controllers\Admin\UserController@destroy` |
| `GET` | `/admin/users/{user}` | Administrator | `App\Http\Controllers\Admin\UserController@show` |
| `PATCH` | `/admin/users/{user}` | Administrator | `App\Http\Controllers\Admin\UserController@update` |
| `PUT` | `/admin/users/{user}` | Administrator | `App\Http\Controllers\Admin\UserController@update` |
| `GET` | `/users` | Active administrator or employee | `App\Http\Controllers\Admin\UserController@index` |

## Workspaces

| Method | Path | Access | Handler |
|---|---|---|---|
| `GET` | `/workspaces` | Authenticated user | `Modules\Workspace\Http\Controllers\WorkspaceController@index` |
| `POST` | `/workspaces` | Authenticated user | `Modules\Workspace\Http\Controllers\WorkspaceController@store` |
| `DELETE` | `/workspaces/{workspace}` | Authenticated user | `Modules\Workspace\Http\Controllers\WorkspaceController@destroy` |
| `GET` | `/workspaces/{workspace}` | Authenticated user | `Modules\Workspace\Http\Controllers\WorkspaceController@show` |
| `PATCH` | `/workspaces/{workspace}` | Authenticated user | `Modules\Workspace\Http\Controllers\WorkspaceController@update` |
| `PUT` | `/workspaces/{workspace}` | Authenticated user | `Modules\Workspace\Http\Controllers\WorkspaceController@update` |
