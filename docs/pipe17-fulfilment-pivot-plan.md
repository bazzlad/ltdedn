# Pipe17 Fulfilment Pivot

Status: fallback/reference.

Pipe17 is no longer the preferred active v1 storefront bridge for LTD EDN fulfilment. The active path is [Direct Storefront Connector Battle Plan](direct-storefront-connector-battle-plan.md), using direct Shopify and Squarespace OAuth/webhook connections.

Keep this document as a reference if LTD EDN later needs Pipe17's routing, inventory, ERP, or complex middleware features.

If LTD EDN later chooses the Pipe17 fallback, artist storefronts connect to Pipe17. Pipe17 pulls Shopify and Squarespace orders, routes LTD EDN SKUs to the LTD EDN fulfillment location, and exposes those routed line items as Shipping Requests. LTD EDN has one active Pipe17 hub connection, polls those requests, allocates stock and limited editions locally, fulfils the order, then pushes tracking back to Pipe17 as a Fulfillment.

## Operating Flow

1. LTD EDN creates a Pipe17 account and fulfillment location for LTD EDN.
2. LTD EDN connects each artist Shopify or Squarespace store inside Pipe17.
3. LTD EDN configures Pipe17 order routing so LTD EDN SKUs route to the LTD EDN fulfillment location.
4. LTD EDN creates one admin storefront connection with platform `pipe17`.
   - Pipe17 fulfillment location ID in `external_shop_id`.
   - Pipe17 API key in encrypted credentials as `api_key`.
5. If `PIPE17_SCHEDULE_ENABLED=true`, the scheduled `pipe17:pull-shipping-requests` command polls Pipe17 every 15 minutes.
6. LTD EDN imports ready Shipping Requests as local orders.
7. Matching SKUs allocate stock and limited editions.
8. Unknown SKUs or insufficient stock create exception orders.
9. LTD EDN marks the order shipped with carrier and tracking.
10. LTD EDN posts a Pipe17 Fulfillment with tracking.
11. Pipe17 syncs tracking onward to the original storefront.

## Pipe17 Setup If Re-Enabled

In Pipe17:

1. Connect the artist's Shopify or Squarespace selling channel.
2. Confirm the product SKUs match LTD EDN SKU codes.
3. Create or confirm the LTD EDN fulfillment location.
4. Configure order routing for LTD EDN SKUs to that location.
5. Confirm API access can list Shipping Requests and create Fulfillments.

In LTD EDN:

1. Open `/admin/storefront-connections/create`.
2. Choose platform `pipe17`.
3. Enter the Pipe17 fulfillment location ID as the external shop ID.
4. Enter the Pipe17 API key as the access token/API key.
5. Leave webhook secret blank.
6. Save the connection and place a paid test order.
7. Run `php artisan pipe17:pull-shipping-requests {connection_id}` manually, or enable `PIPE17_SCHEDULE_ENABLED=true` and wait for the scheduler.

`PIPE17_API_URL` must be HTTPS and must use `api-v3.pipe17.com`, `api.pipe17.com`, another `*.pipe17.com` host, or a host explicitly listed in `PIPE17_ALLOWED_HOSTS`.

## Acceptance Criteria

- A ready Pipe17 Shipping Request with matching SKU imports as a paid LTD EDN order.
- LTD EDN marks the Shipping Request as `sentToFulfillment` after successful import.
- Unknown SKUs create exception orders and notify admins.
- Duplicate pulls do not duplicate LTD EDN orders.
- Marking a Pipe17 order shipped posts a Fulfillment back to Pipe17.
