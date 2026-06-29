# Direct Storefront Connector Battle Plan

Status: active implementation path. Shopify MVP is verified; Squarespace validation is blocked on OAuth client approval.

This plan pivots LTD EDN back to direct Shopify and Squarespace connections. Pipe17 remains a possible fallback for complex middleware needs, but it is no longer the preferred v1 bridge for normal artist storefront onboarding.

The target experience is:

1. Artist clicks `Connect Shopify` or `Connect Squarespace`.
2. Artist approves LTD EDN in the platform's native authorization screen.
3. LTD EDN stores the connection in `storefront_connections`.
4. LTD EDN registers order webhooks automatically.
5. Incoming paid orders are queued, normalized, allocated, and shown in fulfilment.
6. LTD EDN pushes tracking back to the originating storefront in a later phase.

## Guiding Decisions

- Keep `storefront_connections` as the single connection table.
- Do not create a second client/platform/token matrix unless the existing table becomes insufficient.
- Keep platform access tokens and webhook secrets encrypted.
- Use queue-first webhook handling for Shopify and Squarespace.
- Keep normalization inside application code, using the existing normalized DTO/import pipeline.
- Keep Squarespace webhook registration on `https://api.squarespace.com/1.0/webhook_subscriptions`.
- Do not rely on Squarespace Developer API keys for webhook subscriptions; this endpoint requires OAuth.
- Do not add `kyon147/laravel-shopify` unless the existing Shopify connector becomes a real maintenance burden.

## Phase 1: De-Prioritize Pipe17

Goal: remove Pipe17 as the active default without destroying the work in case it is useful later.

Tasks:

- Update docs so direct Shopify/Squarespace connectors are the active v1 path.
- Mark Pipe17 docs as fallback/reference material.
- Remove Pipe17 from the normal admin creation path, or clearly label it as advanced/fallback if retained.
- Remove Pipe17 from artist-facing onboarding copy.
- Keep legacy enum/data compatibility so existing `pipe17` rows do not break admin screens.
- Keep the `pipe17:pull-shipping-requests` scheduler disabled by default unless `PIPE17_SCHEDULE_ENABLED` is enabled.

Acceptance:

- A new operator reading the docs understands direct connectors are the current path.
- Artist setup instructions no longer imply Pipe17 is required.
- No existing Pipe17 data crashes admin pages.

Implementation decision:

- Pipe17 stays readable as fallback/legacy data, but it is hidden from normal admin creation and automatic polling is opt-in.

## Phase 2: Confirm Connection Data Model

Goal: keep the existing `storefront_connections` table as the source of truth.

Required fields already represented by the current design:

- `platform`
- `artist_id`
- `name`
- `store_url`
- `external_shop_id`
- `external_shop_domain`
- `credentials`
- `oauth_scopes`
- `refresh_token`
- `token_expires_at`
- `webhook_secret`
- `webhook_subscription_id`
- `connection_status`
- `last_connection_error`
- `last_sync_meta`
- `tested_at`
- `activated_at`

Tasks:

- Audit migrations and model casts for encrypted credentials/secrets.
- Confirm uniqueness rules for one connected external shop per artist/platform.
- Confirm status transitions: draft, webhook-ready, testing, ready, failed.
- Confirm the admin UI exposes enough connection health data for support.

Acceptance:

- No new parallel connection table is introduced.
- Shopify and Squarespace connections can be created, tested, activated, and inspected from the existing admin surfaces.

## Phase 3: Queue-First Webhook Handling

Goal: webhook endpoints verify authenticity, record enough context, dispatch work, and return fast.

Current issue:

- Webhook controllers normalize and import synchronously. That is acceptable for local testing but brittle in production because platform webhooks expect fast responses and will retry on timeouts.

Target flow:

1. Receive webhook request.
2. Resolve `StorefrontConnection`.
3. Verify platform signature.
4. Capture raw payload, headers, delivery id, and connection id.
5. Dispatch `ProcessExternalOrderWebhook`.
6. Return `200 OK`.
7. Job normalizes and imports using `ExternalOrderImportService`.

Tasks:

- Add a queued job such as `ProcessExternalOrderWebhook`.
- Move Shopify normalization out of `ShopifyWebhookController` into a reusable transformer/service.
- Move Squarespace normalization out of `SquarespaceWebhookController` into a reusable transformer/service.
- Keep idempotency in `ExternalOrderImportService`.
- Ensure queue failures preserve enough error detail to debug bad payloads.
- Ensure webhook endpoints still reject invalid signatures before dispatch.

Acceptance:

- Valid Shopify webhook returns quickly and creates/imports an order through the queue.
- Valid Squarespace webhook returns quickly and creates/imports an order through the queue.
- Invalid signatures return `401` and do not enqueue work.
- Duplicate delivery ids or duplicate external order ids do not create duplicate LTD EDN orders.

## Phase 4: Shopify Direct Connector

Goal: use Shopify OAuth install and order webhooks directly.

Existing path:

- `ShopifyConnectionController` starts and completes OAuth.
- `ShopifyConnectorService` exchanges authorization codes and registers `orders/create`.
- `ShopifyWebhookController` verifies HMAC and queues the order payload.

Tasks:

- Confirm Shopify app credentials are configured through env/config.
- Confirm OAuth stores an offline shop token in `storefront_connections.credentials.access_token`.
- Confirm webhook registration stores the Shopify webhook subscription id.
- Add queue-first handling from Phase 3.
- Confirm the app requests order ingest scope and any write scopes required by currently enabled tracking pushback.
- Gate or remove fulfillment pushback before reducing Shopify scopes to read-only.

Webhook registration decision:

- Short term: keeping the current REST webhook registration is acceptable for an MVP if tests cover it.
- Longer term: migrate registration to Shopify GraphQL `webhookSubscriptionCreate`, because Shopify is steering new public apps toward GraphQL Admin API usage.

Acceptance:

- Artist can connect a Shopify store by entering a `.myshopify.com` domain and approving LTD EDN.
- LTD EDN receives paid order webhooks and imports matching SKUs.
- Unpaid orders are ignored.
- Unknown SKUs or insufficient stock create exception orders.

Human decision:

- Whether this app is distributed as a public Shopify app, custom app, or another Shopify app distribution type. For multiple unrelated artist stores, this likely needs the public/distribution path.

## Phase 5: Squarespace Direct Connector

Goal: use Squarespace OAuth and Webhook Subscriptions API directly.

Current status:

- LTD EDN Connect OAuth client request has been submitted to Squarespace.
- Waiting for Squarespace to issue `client_id` and `client_secret`.
- Test site is ready at `https://coconut-dog-nh33.squarespace.com`.
- Product selected for validation: `https://coconut-dog-nh33.squarespace.com/shop/p/ltd-edn-pillow`.
- Terms URL submitted: `https://test.ltdedn.com/terms`.
- Privacy URL submitted: `https://test.ltdedn.com/privacy`.
- Redirect URI submitted: `https://test.ltdedn.com/connect/squarespace/callback`.

Existing path:

- `SquarespaceConnectionController` starts and completes OAuth.
- `SquarespaceConnectorService` exchanges authorization codes and registers `order.create`.
- `SquarespaceWebhookController` verifies the webhook signature and queues the order payload.

Required endpoint:

```text
https://api.squarespace.com/1.0/webhook_subscriptions
```

Tasks:

- Receive Squarespace OAuth client credentials from the submitted request.
- Configure `SQUARESPACE_CONNECT_CLIENT_ID` and `SQUARESPACE_CONNECT_CLIENT_SECRET` on `test.ltdedn.com`.
- Request order ingest scope and any write scopes required by currently enabled tracking pushback.
- Register `order.create` after OAuth using the Squarespace access token.
- Store the returned `websiteId`, subscription id, and webhook secret.
- Add queue-first handling from Phase 3.
- Confirm refresh-token handling for long-lived access.
- Gate or remove fulfillment pushback before reducing Squarespace scopes to read-only.

Acceptance:

- Artist can click `Connect Squarespace`, approve LTD EDN, and return to LTD EDN.
- LTD EDN creates a Squarespace webhook subscription automatically.
- Incoming Squarespace webhook signatures are verified using the stored secret.
- Paid orders import through the same normalized pipeline as Shopify.

Human decision:

- Squarespace OAuth/Extension approval is an external platform process. Work is currently blocked until Squarespace returns OAuth credentials. Production onboarding still depends on approval and working merchant test access.

## Phase 6: Data Normalization And Import

Goal: preserve one internal order pipeline across platforms.

Canonical internal shape:

- external order id
- external order number
- customer email
- shipping address
- currency
- subtotal, shipping, tax, total
- payment status
- fulfillment status
- line items with SKU, title, variant title, quantity, unit amount, line total, and platform attributes

Tasks:

- Extract `ShopifyOrderTransformer`.
- Extract `SquarespaceOrderTransformer`.
- Keep DTO output compatible with `NormalizedOrderData`.
- Keep `ExternalOrderImportService` responsible for idempotency, payment filtering, allocation, exception orders, and admin notifications.
- Add focused tests for transformer edge cases.

Acceptance:

- Shopify and Squarespace payloads produce equivalent normalized DTOs for equivalent orders.
- Missing SKU, unpaid, duplicate, and insufficient-stock cases are covered by tests.

## Phase 7: Fulfillment And Tracking Write-Back

Goal: after LTD EDN ships an imported order, push tracking back to the original storefront.

This is the next phase after reliable ingest.

Shopify tasks:

- Add required fulfillment scopes.
- Store enough Shopify line-item/fulfillment-order identifiers during import.
- Resolve fulfillment orders where needed before creating fulfillment records.
- Push carrier, tracking number, tracking URL, and shipped status.
- Handle partial fulfillment when only LTD EDN SKUs are fulfilled by LTD EDN.

Squarespace tasks:

- Add required order write scope.
- Confirm Squarespace fulfillment endpoint shape and required IDs.
- Store enough Squarespace order/line item metadata during import.
- Push carrier, tracking number, tracking URL, and shipped status.

Acceptance:

- Marking a Shopify-origin LTD EDN order shipped updates tracking in Shopify.
- Marking a Squarespace-origin LTD EDN order shipped updates tracking in Squarespace.
- Failed pushback creates a visible admin error without losing local shipped state.

Human decision:

- Whether LTD EDN should push tracking for every connected artist by default or make tracking pushback optional per connection.

## Test Plan

Minimum automated coverage:

- Shopify OAuth callback creates/updates `storefront_connections`.
- Shopify webhook with valid signature dispatches a job.
- Shopify webhook with invalid signature does not dispatch a job.
- Shopify queued job normalizes/imports a paid order.
- Squarespace OAuth callback creates/updates `storefront_connections`.
- Squarespace webhook with valid signature dispatches a job.
- Squarespace webhook with invalid signature does not dispatch a job.
- Squarespace queued job normalizes/imports a paid order.
- Duplicate external order id is ignored.
- Unknown SKU creates an exception order.
- Insufficient stock creates an exception order.
- Shipped external order dispatches the correct platform pushback job.

Manual/platform checks:

- Shopify development store install.
- Shopify paid test order import.
- Squarespace OAuth test connection after credentials are issued.
- Squarespace paid test order import after credentials are issued.
- Queue worker retry/failure behavior.

## Implementation Order

1. Update docs/admin copy to mark direct connectors active and Pipe17 fallback.
2. Add queue job and move Shopify/Squarespace webhook work into the queue.
3. Extract Shopify and Squarespace transformers.
4. Add tests for fast webhook responses and queued imports.
5. Harden Shopify webhook registration and add GraphQL migration note or implementation.
6. Harden Squarespace refresh-token behavior.
7. Add fulfillment/tracking write-back scopes and platform pushback implementations.

## Open Risks

- Shopify app distribution/review may affect how quickly unrelated artist stores can install LTD EDN Connect.
- Squarespace OAuth/Extension approval is outside the codebase and is the current blocker for end-to-end validation.
- Fulfillment pushback is more complex than order ingest, especially for partial fulfillment and platform-specific IDs.
- Queue configuration must be production-ready before onboarding real artists.
- Platform webhook payloads can change additively, so transformers must tolerate unknown fields.
