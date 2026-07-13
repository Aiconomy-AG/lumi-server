# Data model

The application uses Eloquent models backed by MySQL. The diagram shows primary business relationships; audit and notification metadata fields are omitted for clarity.

```mermaid
erDiagram
    USER ||--o{ DEVICE_TOKEN : owns
    USER ||--o{ AUDIT_LOG : acts
    USER ||--o| CUSTOMER : resolves_to
    CUSTOMER ||--o{ CART : owns
    CART ||--o{ CART_ITEM : contains
    PRODUCT ||--o{ PRODUCT_VARIANT : has
    PRODUCT_VARIANT ||--o{ CART_ITEM : selected_as
    CATEGORY ||--o{ PRODUCT : groups
    PRODUCT }o--o{ INGREDIENT : contains
    CUSTOMER ||--o{ WISHLIST_ITEM : saves
    PRODUCT ||--o{ WISHLIST_ITEM : saved_as
    CUSTOMER ||--o{ ORDER : places
    ORDER ||--o{ ORDER_ITEM : contains
    PRODUCT_VARIANT ||--o{ ORDER_ITEM : purchased_as
    ORDER ||--o{ RETURN_REQUEST : has
    RETURN_REQUEST ||--o{ RETURN_ITEM : contains
    PROJECT ||--o{ TASK : groups
    TASK ||--o{ TASK : parent_of
    TASK }o--o{ USER : assigned_to
    TASK ||--o{ TASK_TIME_ENTRY : tracked_by
    USER ||--o{ TASK_TIME_ENTRY : records
    CONVERSATION }o--o{ USER : participants
    CONVERSATION ||--o{ MESSAGE : contains
    USER ||--o{ MESSAGE : sends
    NOTIFICATION_EVENT ||--o{ NOTIFICATION_DELIVERY : delivers
    USER ||--o{ NOTIFICATION_DELIVERY : receives
```

## Ownership rules

- Shopify identifiers coexist with local integer identifiers on sales records. Normalize Shopify GIDs through `ShopifyId` helpers before querying.
- Cart items identify product variants, not base products.
- Order items preserve unit price and quantity at purchase time.
- Return requests may originate from local orders, Shopify proxies, or customer-account flows.
- Notification events represent one logical event; deliveries track per-recipient read and dismissal state.
- A task may have a parent task, multiple assignees, and multiple time entries. Only one active entry per applicable user/task flow should be allowed by service behavior.

Apply schema changes through new migrations. Review foreign keys, nullability, indexes, casts, resources, factories, and rollback behavior together.

