# Storefront Connector Work Document

Status: Shopify MVP verified on `test.ltdedn.com`; Squarespace validation is blocked on OAuth client approval.

Last updated: 2026-06-29.

## Executive Summary

LTD EDN now has a working direct Shopify connector MVP. A Shopify development store has been connected through LTD EDN OAuth, a real paid test order has been imported, SKU stock has been allocated, the order has been shipped from LTD EDN, the buyer shipment email has been sent, and tracking has been pushed back to Shopify successfully.

This validates the direct connector approach for Shopify. Pipe17 is no longer the active v1 bridge. It remains fallback/reference only.

The project is not yet a polished production onboarding product. Connection lifecycle status now updates after a successful paid direct storefront import. The next work should focus on operational hardening, a repeatable onboarding runbook, and then Squarespace validation once OAuth credentials are issued.

## Current Hold

Squarespace validation is paused until Squarespace issues OAuth client credentials for LTD EDN Connect.

Submitted request:

- Subject: `OAuth client request for LTD EDN Connect Squarespace fulfilment integration`
- Redirect URI: `https://test.ltdedn.com/connect/squarespace/callback`
- Requested scopes:
  - `website.orders`
  - `website.orders.read`
  - `website.products.read`
- Terms URL: `https://test.ltdedn.com/terms`
- Privacy URL: `https://test.ltdedn.com/privacy`
- Test shop/product URL: `https://coconut-dog-nh33.squarespace.com/shop/p/ltd-edn-pillow`

When Squarespace responds, configure:

```text
SQUARESPACE_CONNECT_CLIENT_ID=...
SQUARESPACE_CONNECT_CLIENT_SECRET=...
SQUARESPACE_CONNECT_SCOPES=website.orders,website.orders.read,website.products.read
```

Then deploy/reload config, connect the Squarespace test site from `/connect/storefronts`, and continue the real paid order test.

## Verified End-To-End State

Environment: `test.ltdedn.com`.

Verified Shopify deployed commit:

```text
b28c59c Fix storefront connect page contrast
```

Verified Shopify flow:

1. Shopify dev store connected through LTD EDN OAuth.
2. Shopify app scopes approved, including fulfillment-order scopes.
3. LTD EDN registered an `orders/create` webhook.
4. A real paid Shopify order was placed.
5. LTD EDN accepted the webhook and queued import work.
6. The order was normalized and imported.
7. SKU matched local `product_skus.sku_code`.
8. Stock was allocated.
9. The order appeared in LTD EDN fulfilment/admin.
10. Shipment tracking was recorded in LTD EDN.
11. Buyer shipment email was sent.
12. Shopify fulfillment order id was fetched.
13. Tracking pushback to Shopify succeeded with HTTP `201`.

Verified test record:

```text
Order: #1001
Platform: shopify
Import status: processed
Order status: paid
Shipment pushback status: succeeded
Shopify fulfillment order id: 7120207052952
```

## Current Architecture

The active v1 architecture is direct storefront integration:

```text
Storefront checkout
  -> Shopify/Squarespace webhook
  -> LTD EDN signed webhook endpoint
  -> queue job
  -> platform transformer
  -> normalized order import
  -> SKU/edition stock allocation
  -> fulfilment queue
  -> shipment email
  -> platform tracking pushback
```

Primary table:

```text
storefront_connections
```

Important model fields:

- `platform`
- `artist_id`
- `store_url`
- `external_shop_domain`
- `credentials`
- `oauth_scopes`
- `webhook_secret`
- `webhook_subscription_id`
- `connection_status`
- `last_connection_error`
- `tested_at`
- `activated_at`

Important routes:

- `GET /connect/storefronts`
- `GET /connect/storefronts/{connection}/check`
- `GET /connect/shopify/start`
- `GET /connect/shopify/callback`
- `POST /api/webhooks/shopify/{connection}`
- `POST /api/webhooks/squarespace/{connection}`
- `GET /admin/storefront-connections`
- `GET /admin/external-imports`
- `GET /admin/fulfilment`
- `GET /admin/sales`

## Completed Work

Shopify connector:

- OAuth install flow implemented.
- `.myshopify.com` domain validation implemented.
- Access token stored encrypted in `storefront_connections.credentials`.
- Shopify webhook registration implemented using REST.
- Shopify HMAC verification implemented.
- Queue-first webhook handling implemented.
- Shopify order transformer implemented.
- Paid order import implemented.
- Unpaid order ignore path implemented.
- Unknown SKU exception path implemented.
- Duplicate import protection implemented.
- Shopify tracking pushback implemented.
- Fulfillment order lookup and cache implemented.
- Required Shopify scopes updated:
  - `read_orders`
  - `write_fulfillments`
  - `read_merchant_managed_fulfillment_orders`
  - `write_merchant_managed_fulfillment_orders`

Order operations:

- External order import records exist.
- Admin fulfilment queue exists.
- Shipment capture exists.
- Buyer shipment email exists.
- Shipment email now uses `SUPPORT_EMAIL` instead of telling buyers to reply to `noreply`.
- Shipment pushback success/failure events are recorded.

SKU/product support:

- Product-level storefront SKUs are exposed in admin.
- SKU CSV export exists.
- QR CSV export has been renamed to make its purpose clearer.
- Default SKU backfill exists.

UI/docs/ops:

- Artist-facing connect/check pages exist.
- Artist-facing connect page exposes Shopify and Squarespace options.
- Storefront connect errors now render on the connect page instead of appearing as a silent reload.
- Connect page contrast bug fixed.
- Terms and privacy pages have non-placeholder connector-aware copy for the Squarespace OAuth request.
- Successful paid direct storefront imports now mark storefront connections as tested and ready without activating them.
- Direct connector docs are the active path.
- Pipe17 is hidden from normal onboarding and treated as fallback/legacy.
- GitHub linter workflow fixed.
- `.node-version` now targets Node `22.22.3`, matching the verified local build lane.

## Known Gaps

### 1. Shopify Distribution Decision Still Needed

The technical flow works for a development/custom-style app. For multiple unrelated artist Shopify stores, decide whether LTD EDN will operate as:

- a public/distributed Shopify app,
- a custom app installed per merchant,
- or an unlisted/custom distribution path if Shopify permits the intended use.

This affects review requirements, protected customer data approval, merchant install UX, and production support.

### 2. Squarespace Is Blocked On OAuth Client Approval

Squarespace is planned and partially implemented, but it has not been proven with a real merchant/test account in the same way Shopify has. The test site exists, and the OAuth client request has been submitted to Squarespace. Real validation cannot continue until Squarespace issues `client_id` and `client_secret`.

Current intended Squarespace path:

- OAuth only.
- Webhook subscriptions created at:

```text
https://api.squarespace.com/1.0/webhook_subscriptions
```

Remaining work:

- Receive and configure Squarespace OAuth credentials.
- Connect a real Squarespace Commerce test site.
- Register order webhook.
- Import real paid order.
- Confirm tracking pushback.

### 3. Operational Monitoring Needs Hardening

Before production customer usage, confirm:

- queue workers are supervised and always running,
- failed jobs are visible and actionable,
- webhook failures are logged with enough context,
- external import failures surface in admin,
- shipment pushback failures are easy to retry,
- support can see connection health without database access.

## Production Readiness Checklist

Shopify:

- [x] OAuth install works.
- [x] Webhook registration works.
- [x] Protected customer data access approved for test app.
- [x] Paid order webhook imports successfully.
- [x] SKU allocation works.
- [x] Shipment email sends.
- [x] Fulfillment tracking pushback works.
- [x] Connection status auto-updates after successful test order.
- [ ] Operator can retry failed shipment pushback from admin.
- [ ] Production Shopify app distribution model decided.
- [ ] Production app scopes and protected customer data settings confirmed.

Squarespace:

- [x] OAuth client request submitted.
- [ ] OAuth app credentials received and configured.
- [ ] Real test site connected.
- [ ] Webhook subscription registration verified.
- [ ] Signed order webhook verified.
- [ ] Paid order import verified.
- [ ] Shipment/tracking pushback verified.

Operations:

- [ ] Queue worker supervision confirmed.
- [ ] Failed job monitoring confirmed.
- [ ] Webhook retry/idempotency tested under duplicate delivery.
- [ ] Admin support workflow documented.
- [ ] Store owner onboarding checklist written.
- [ ] Final production environment variables reviewed.

## Recommended Next Work

### P0: Wait For Squarespace OAuth Client Credentials

Goal: resume Squarespace validation as soon as Squarespace approves the OAuth client request.

Blocked on:

- Squarespace returning `client_id` and `client_secret`.

Immediate next steps after approval:

1. Add credentials to `test.ltdedn.com`.
2. Confirm config cache is refreshed.
3. Connect the Squarespace test site from `/connect/storefronts`.
4. Confirm webhook subscription registration.
5. Confirm the test product SKU matches `product_skus.sku_code`.
6. Place a paid test order.
7. Confirm import, allocation, fulfilment email, and tracking pushback.

### P1: Repeat Shopify From A Blank Store

Goal: prove the process is repeatable, not just working once.

Test script:

1. Create a second Shopify dev store or reset the current test setup.
2. Create an LTD EDN artist/product/SKU.
3. Connect Shopify from `/connect/storefronts`.
4. Confirm scopes and webhook registration.
5. Add Shopify product variant with exact LTD EDN SKU.
6. Place paid test order.
7. Confirm import.
8. Mark shipped.
9. Confirm buyer email.
10. Confirm Shopify fulfillment/tracking update.

Acceptance:

- The whole flow works using documented steps only.
- Any confusing screens or copy are fixed.

### P2: Squarespace Validation

Goal: determine whether Squarespace can match the Shopify UX closely enough.

Tasks:

1. Receive and configure Squarespace OAuth credentials.
2. Connect a real Commerce test site.
3. Verify webhook subscription endpoint.
4. Place test paid order.
5. Import and allocate stock.
6. Push fulfillment/tracking back.

Acceptance:

- A Squarespace order can travel through the same pipeline as Shopify.
- Any Squarespace-specific constraints are documented.

## Shopify Artist Onboarding Draft

Use this once connection status automation is cleaned up.

1. LTD EDN creates or confirms the artist and products.
2. LTD EDN provides the artist with product SKUs.
3. Artist creates Shopify products/variants using the exact LTD EDN SKU.
4. Artist opens LTD EDN Connect.
5. Artist enters their `.myshopify.com` domain.
6. Artist approves LTD EDN in Shopify.
7. LTD EDN shows the connection check page.
8. Artist places one paid test order.
9. LTD EDN imports the order.
10. LTD EDN confirms SKU match and stock allocation.
11. LTD EDN marks the test order shipped.
12. Shopify receives tracking.
13. Connection is activated for live orders.

## Support Runbook

If an order does not appear:

1. Check `/admin/external-imports`.
2. Confirm the connection platform is `shopify`.
3. Confirm the webhook was accepted and queued.
4. Check whether import status is `ignored`, `exception`, `failed`, or `processed`.
5. For `ignored`, confirm payment status was paid.
6. For `exception`, check SKU and stock.
7. For `failed`, check logs and failed jobs.

If a Shopify pushback fails:

1. Check `/admin/sales/{order}` for `shipment_pushback_error`.
2. Confirm the connection has an access token.
3. Confirm app scopes include fulfillment-order scopes.
4. Confirm merchant reinstalled/approved the app after scope changes.
5. Confirm Shopify returns fulfillment orders for the external order id.
6. Retry pushback.

If the check page is confusing:

1. Confirm the connection status.
2. Confirm whether a successful paid test order exists.
3. Confirm SKU checklist rows match Shopify variant SKUs.
4. Confirm any connection error is visible in `last_connection_error`.

## Key Project Files

Connector controllers:

- `app/Http/Controllers/Connect/ShopifyConnectionController.php`
- `app/Http/Controllers/Connect/SquarespaceConnectionController.php`
- `app/Http/Controllers/Webhooks/ShopifyWebhookController.php`
- `app/Http/Controllers/Webhooks/SquarespaceWebhookController.php`

Connector services/jobs:

- `app/Services/StorefrontConnect/ShopifyConnectorService.php`
- `app/Services/StorefrontConnect/SquarespaceConnectorService.php`
- `app/Jobs/ProcessExternalOrderWebhook.php`
- `app/Jobs/PushShopifyFulfilment.php`
- `app/Jobs/PushSquarespaceFulfilment.php`

Order import:

- `app/Services/ExternalOrderImportService.php`
- `app/Services/ExternalOrders/ShopifyOrderTransformer.php`
- `app/Services/ExternalOrders/SquarespaceOrderTransformer.php`

Artist/admin UI:

- `resources/js/pages/Connect/Storefronts.vue`
- `resources/js/pages/Connect/Check.vue`
- `resources/js/pages/Admin/StorefrontConnections/Show.vue`
- `resources/js/pages/Admin/ExternalImports/Index.vue`
- `resources/js/pages/Admin/Fulfilment/Index.vue`
- `resources/js/pages/Admin/Sales/Show.vue`

Docs:

- `docs/direct-storefront-connector-battle-plan.md`
- `docs/external-order-fulfilment-usage.md`
- `docs/storefront-connector-work-document.md`
