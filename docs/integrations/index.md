# Integration overview

| Integration | Direction | Failure impact |
|---|---|---|
| Shopify Admin GraphQL | Outbound | Product, inventory, and collection synchronization delayed or failed |
| Shopify proxy and webhooks | Inbound | Storefront mutations or synchronization rejected when signatures fail |
| Meilisearch | Outbound | Product search unavailable; database catalog reads remain separate |
| Firebase Cloud Messaging | Outbound | Push delivery fails; application records and realtime events remain authoritative |
| Reverb/Pusher protocol | Bidirectional | Realtime updates stop; persisted state remains available through HTTP |
| Mail/Resend | Outbound | Invitations and password-reset communication fail |
| Gemini | Outbound | Optional AI replies are skipped or fail; normal conversation messages continue |

External calls should have bounded timeouts, safe logs, retry behavior appropriate to idempotency, and monitoring. Never log access tokens, signatures, raw service-account content, or password-reset tokens.

