# Order Desk Fulfilment Pivot

Order Desk is the active v1 storefront bridge for LTD EDN fulfilment.

Artist storefronts connect to Order Desk. Order Desk sends paid/order-ready order payloads to LTD EDN. LTD EDN remains the system of record for SKU matching, stock and edition allocation, fulfilment queue state, shipment entry, buyer shipping email, and tracking pushback.

The direct Shopify and Squarespace OAuth connector code remains in the app, but it is not the primary v1 onboarding path.

## Flow

1. LTD EDN creates or selects an Order Desk store for the artist.
2. LTD EDN connects the artist's Shopify, Squarespace, or other storefront inside Order Desk.
3. LTD EDN creates an admin storefront connection with platform `orderdesk`.
4. The connection stores:
   - Order Desk store ID in `external_shop_id`.
   - Order Desk API key in encrypted credentials as `api_key`.
   - Optional separate inbound webhook hash secret in `webhook_secret`; if this is blank, LTD EDN verifies with the encrypted API key.
5. In Order Desk, LTD EDN creates a Rule Builder action using `Post Order JSON`.
6. Order Desk posts orders to `POST /api/webhooks/orderdesk/{connection}`.
7. LTD EDN validates `X-ORDER-DESK-STORE-ID` and `X-ORDER-DESK-HASH`.
8. LTD EDN imports paid orders through the existing external order importer.
9. Operators fulfil the order in `/admin/fulfilment`.
10. LTD EDN posts shipment tracking back to Order Desk.
11. Order Desk syncs tracking onward to the original storefront where configured.

## Order Desk Setup

Create an Order Desk API key from the store API settings. Record the store ID and API key.

In LTD EDN:

1. Open `/admin/storefront-connections/create`.
2. Choose platform `Orderdesk`.
3. Enter the artist and connection name.
4. Enter the Order Desk store ID as the external shop ID.
5. Enter the Order Desk API key as the access token/API key.
6. Leave webhook secret blank unless a separate hash secret is configured in Order Desk.
7. Save the connection and copy the generated webhook URL.

In Order Desk:

1. Add a Rule Builder rule for the chosen event, usually order imported or order created after payment capture.
2. Add the `Post Order JSON` action.
3. Paste the LTD EDN webhook URL.
4. Enable the API key/hash option so Order Desk sends `X-ORDER-DESK-HASH`.
5. Send a test order and confirm it appears in `/admin/external-imports`.

## Success Criteria

- A paid Order Desk order with matching SKU imports as a paid LTD EDN order.
- Unknown SKUs and insufficient stock create exception orders.
- Duplicate Order Desk posts do not duplicate LTD EDN orders.
- `/admin/fulfilment` shows imported paid orders ready to ship.
- Marking an Order Desk order shipped posts tracking back to Order Desk.
