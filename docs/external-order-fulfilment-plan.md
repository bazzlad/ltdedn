# External Order Processing And Fulfilment Plan

## Goal

LTD EDN should become the canonical order and fulfilment hub for artist products sold through external storefronts such as Shopify and Squarespace.

External storefronts remain responsible for checkout, payment capture, tax, fraud checks, and their native customer purchase experience. LTD EDN ingests paid orders, validates SKUs, allocates LTD EDN stock or limited editions, queues fulfilment, records shipment details, notifies the buyer, and pushes tracking back to the source platform.

## Decisions

- LTD EDN is the system of record for inventory, edition allocation, fulfilment state, and shipment tracking.
- Shopify and Squarespace are both v1 external channels.
- External storefronts capture payment; LTD EDN only imports paid/ready orders.
- SKU is mandatory for external line item matching.
- Unknown SKUs or unavailable stock create an exception order for admin review.
- Phase 1 fulfilment is manual carrier/tracking entry by the LTD EDN team.
- The data model should leave a clean path to ShipStation or a 3PL later.

## Target Workflow

1. Artist configures product variants in their external store using LTD EDN SKU codes.
2. External store receives and captures the customer order.
3. Store webhook notifies LTD EDN.
4. LTD EDN verifies the webhook and fetches or normalizes the full order payload.
5. LTD EDN ignores duplicate deliveries using platform order id and payload hash.
6. LTD EDN maps every line item to a local SKU.
7. LTD EDN atomically allocates stock and editions.
8. Successful imports enter the fulfilment queue.
9. Failed imports enter an exception queue with the original payload and error.
10. LTD EDN operator ships the order and enters carrier/tracking.
11. LTD EDN emails the buyer and pushes fulfilment/tracking back to the source platform.

## Implementation Tasks

### Phase 0: Baseline Commerce Backport

- [ ] Review `origin/feature/store-stripe-v1` and identify commerce pieces to bring onto `main`.
- [ ] Backport order, order item, order event, SKU, sales admin, and fulfilment admin foundations without enabling LTD EDN checkout routes by default.
- [ ] Keep Stripe checkout-specific code isolated so external order ingestion does not depend on Stripe.
- [ ] Add or preserve tests for order models, fulfilment queue, manual shipping, and sales admin.
- [ ] Run `composer test` and `npm run build` after the backport.

Acceptance:

- `main` has local order/SKU/fulfilment primitives.
- Admins can view paid unshipped orders and mark an order shipped.
- Existing QR/product/edition flows still pass.

### Phase 1: External Channel Data Model

- [ ] Add `storefront_connections` for platform, artist/site ownership, credentials, webhook secret, status, and last sync metadata.
- [ ] Add `external_order_imports` for platform, external order id, delivery id, payload hash, raw payload, processing status, and error details.
- [ ] Extend orders with source platform, connection id, external order id, external order number, source payment status, source fulfilment status, and exception reason.
- [ ] Extend order events to record import, allocation, exception, shipment, pushback success, and pushback failure events.
- [ ] Add model relationships, casts, factories, and migration tests.

Acceptance:

- A store connection can be represented without exposing credentials in admin responses.
- Duplicate external order deliveries can be detected before creating duplicate orders.
- Orders can be filtered by source platform and exception state.

### Phase 2: Shared Import Pipeline

- [ ] Create a channel-neutral `ExternalOrderImportService`.
- [ ] Define one normalized order DTO shape for all adapters: source ids, customer, shipping address, currency, totals, payment status, fulfilment status, and line items.
- [ ] Require `sku_code`, product title snapshot, variant title snapshot, quantity, unit amount, and line total for each line item.
- [ ] Import paid orders only; store unpaid or unsupported events as ignored import records.
- [ ] Use a database transaction and row locks when allocating SKU stock and editions.
- [ ] Create exception orders when SKU mapping or allocation fails.
- [ ] Notify admins when an import enters exception state.

Acceptance:

- The importer is idempotent.
- Concurrent imports cannot oversell a SKU or limited edition.
- Unknown SKUs and insufficient stock are visible in admin review instead of failing silently.

### Phase 3: Shopify Adapter

- [ ] Add `POST /api/webhooks/shopify/{connection}`.
- [ ] Verify Shopify webhook signatures using the connection secret.
- [ ] Handle paid/order-ready events and normalize Shopify order line items by SKU.
- [ ] Store duplicate webhook deliveries without creating duplicate orders.
- [ ] Add a Shopify fulfilment pushback job for carrier/tracking updates.
- [ ] Record pushback success or failure as order events.

Acceptance:

- A paid Shopify order with valid SKUs becomes a LTD EDN order in the fulfilment queue.
- A duplicate Shopify webhook is safe.
- Shipment tracking is pushed back to Shopify or recorded as retryable failure.

### Phase 4: Squarespace Adapter

- [ ] Add `POST /api/webhooks/squarespace/{connection}`.
- [ ] Verify Squarespace webhook signatures using the connection secret.
- [ ] Handle order create/update notifications.
- [ ] Fetch or normalize the full Squarespace order before import when webhook payloads are incomplete.
- [ ] Map line items by SKU and reuse the shared import service.
- [ ] Add a Squarespace fulfilment pushback job for carrier/tracking updates.

Acceptance:

- A paid Squarespace order with valid SKUs becomes a LTD EDN order in the fulfilment queue.
- Duplicate or partial Squarespace notifications are safe.
- Shipment tracking is pushed back to Squarespace or recorded as retryable failure.

### Phase 5: Admin Operations UI

- [ ] Add an admin connections page for Shopify and Squarespace setup status.
- [ ] Add an external imports page with filters for processed, ignored, failed, and exception imports.
- [ ] Extend fulfilment queue with platform, external order number, artist, SKU, address, and import warnings.
- [ ] Add exception resolution actions: retry import, mark resolved, or link to source platform for refund/manual handling.
- [ ] Add shipment pushback status to sales/order detail pages.

Acceptance:

- Operators can see exactly why an external order is blocked.
- Operators can fulfil successful orders without opening Shopify or Squarespace.
- Platform pushback failures are visible and retryable.

### Phase 6: Stock Sync And Channel Hygiene

- [ ] Add commands/jobs to publish LTD EDN available stock to Shopify and Squarespace.
- [ ] Add a per-connection dry-run mode for stock sync.
- [ ] Add admin warnings for external SKUs not found locally.
- [ ] Add reporting for stock mismatches and stale connections.

Acceptance:

- LTD EDN remains the inventory source of truth.
- External storefront stock can be refreshed from LTD EDN.
- SKU/catalog problems are detected before orders fail.

### Phase 7: ShipStation/3PL Readiness

- [ ] Add a shipping provider abstraction behind manual fulfilment.
- [ ] Keep manual tracking as the default provider.
- [ ] Add an export/job interface that can later create ShipStation orders.
- [ ] Store provider shipment ids separately from platform order ids.
- [ ] Keep platform pushback driven by LTD EDN shipment state, not by a specific provider.

Acceptance:

- Manual fulfilment still works.
- A future ShipStation integration can be added without changing Shopify/Squarespace import contracts.
- Orders can track source platform id, LTD EDN order id, and shipping provider id independently.

## Testing Checklist

- [ ] Shopify valid signature imports a paid order.
- [ ] Shopify invalid signature is rejected.
- [ ] Shopify duplicate delivery does not duplicate order rows.
- [ ] Shopify unknown SKU creates exception state.
- [ ] Shopify shipment pushback success and failure are recorded.
- [ ] Squarespace valid signature imports a paid order.
- [ ] Squarespace invalid signature is rejected.
- [ ] Squarespace duplicate notification does not duplicate order rows.
- [ ] Squarespace unknown SKU creates exception state.
- [ ] Squarespace shipment pushback success and failure are recorded.
- [ ] Import pipeline ignores unpaid orders.
- [ ] Import pipeline prevents oversell under concurrent requests.
- [ ] Manual shipment sends buyer email once.
- [ ] Admin fulfilment queue excludes exceptions and shipped orders.
- [ ] Admin exception queue shows actionable error details.

## Open Follow-Up Decisions

- Exact Shopify event names to subscribe to after credentials are available.
- Exact Squarespace webhook payload shape after a live test payload is captured.
- Whether admins can manually override SKU matching in v1 or only after initial launch.
- Whether customer emails should come only from external platforms, LTD EDN, or both.
- When to switch from manual tracking to ShipStation or a 3PL.
