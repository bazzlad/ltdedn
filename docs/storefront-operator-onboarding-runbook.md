# Storefront Operator Onboarding Runbook

Last updated: 2026-06-29.

This runbook is for LTD EDN operators onboarding a Shopify or Squarespace storefront to the direct connector.

## Current Status

- Shopify has been verified end to end on `test.ltdedn.com`.
- Squarespace code exists, but real-store validation is on hold until Squarespace issues OAuth credentials.
- Pipe17 is fallback/reference only and is not part of normal artist onboarding.

## Required Production Configuration

Base app:

```env
APP_URL=https://test.ltdedn.com
QUEUE_CONNECTION=database
MAIL_MAILER=...
SUPPORT_EMAIL=...
```

Shopify:

```env
SHOPIFY_CONNECT_CLIENT_ID=...
SHOPIFY_CONNECT_CLIENT_SECRET=...
SHOPIFY_CONNECT_SCOPES=read_orders,write_fulfillments,read_merchant_managed_fulfillment_orders,write_merchant_managed_fulfillment_orders
SHOPIFY_CONNECT_API_VERSION=2025-10
```

Squarespace, once approved:

```env
SQUARESPACE_CONNECT_CLIENT_ID=...
SQUARESPACE_CONNECT_CLIENT_SECRET=...
SQUARESPACE_CONNECT_SCOPES=website.orders,website.orders.read,website.products.read
SQUARESPACE_CONNECT_USER_AGENT="LTD EDN Connect"
```

After changing env vars:

```bash
php artisan config:clear
php artisan config:cache
```

## Artist, Product, And SKU Setup

1. Create or confirm the artist in `/admin/artists`.
2. Create the LTD EDN product in `/admin/products`.
3. Add one active `ProductSku` per externally sold variant.
4. For limited products, create enough available editions for the test order quantity.
5. Export or copy the exact LTD EDN SKU.
6. Give the store operator the exact SKU and require it on the storefront product variant.
7. Before a paid test, open `/connect/storefronts/{connection}/check` and confirm the SKU checklist is ready.

Acceptance:

- Every external variant has an exact local `product_skus.sku_code` match.
- Stock or limited editions are available.
- The check page does not show missing SKU or no-stock rows.

## Shopify Onboarding And Test Order

1. Confirm Shopify env vars are configured.
2. Create or confirm the Shopify product variant with the exact LTD EDN SKU.
3. Ask the artist/operator to open `/connect/storefronts`.
4. Select `Shopify`.
5. Select the artist.
6. Enter the `.myshopify.com` store domain.
7. Enter a connection name.
8. Click `Connect Shopify`.
9. Approve the LTD EDN app in Shopify.
10. Return to `/connect/storefronts/{connection}/check`.
11. Confirm status, SKU checklist, and any connection errors.
12. Place one real paid test order in Shopify.
13. Wait for the queue worker to process the webhook.
14. Confirm `/connect/storefronts/{connection}/check` shows the last successful test order/import.
15. Confirm `/admin/external-imports` shows `processed`.
16. Confirm `/admin/fulfilment` shows the paid order.
17. Open `/admin/sales/{order}` and confirm line items, events, and customer email.
18. Mark the order shipped with carrier and tracking.
19. Wait for the queue worker to process shipment pushback.
20. Confirm `/admin/sales/{order}` shows pushback `succeeded`.
21. Confirm Shopify shows the fulfillment/tracking update.

If Shopify pushback fails:

1. Open `/admin/sales/{order}`.
2. Read `shipment_pushback_error`.
3. Confirm the app scopes include fulfillment-order scopes.
4. If scopes changed, ask the merchant to reinstall/approve the app.
5. Confirm the order still has shipment data, external order data, and an active storefront connection.
6. Click `Retry pushback` on the sales detail page.
7. Confirm a `shipment_pushback_retry_queued` event is recorded and the retry job succeeds.

## Squarespace Onboarding And Test Order

Squarespace is blocked until OAuth credentials are issued. The requested OAuth setup is:

- Redirect URI: `https://test.ltdedn.com/connect/squarespace/callback`
- Scopes: `website.orders`, `website.orders.read`, `website.products.read`
- Terms URL: `https://test.ltdedn.com/terms`
- Privacy URL: `https://test.ltdedn.com/privacy`

Once credentials arrive:

1. Set the Squarespace env vars.
2. Clear/cache Laravel config.
3. Open `/connect/storefronts`.
4. Select `Squarespace`.
5. Confirm the readiness panel says `OAuth credentials configured`.
6. Confirm the readiness panel redirect URI and scopes match the approved Squarespace client.
7. Select the artist.
8. Enter the Squarespace website id if the account has multiple sites.
9. Enter a connection name.
10. Click `Connect Squarespace`.
11. Approve the Squarespace OAuth request.
12. Return to `/connect/storefronts/{connection}/check`.
13. Confirm webhook registration and connection errors.
14. Create or confirm the Squarespace Commerce product SKU matches LTD EDN exactly.
15. Place one real paid test order.
16. Confirm `/connect/storefronts/{connection}/check` shows the last successful test order/import.
17. Confirm `/admin/external-imports` shows `processed`.
18. Confirm `/admin/fulfilment` shows the paid order.
19. Mark the order shipped.
20. Confirm shipment pushback succeeds in LTD EDN and Squarespace.

If the readiness panel says credentials are missing, `Connect Squarespace` is disabled and the live Squarespace OAuth test cannot continue.

## Success Verification

For every onboarding test, record:

- Connection id and platform.
- Storefront order number.
- `/admin/external-imports` import id and status.
- `/admin/sales/{order}` order id and status.
- SKU allocation result.
- Buyer shipment email status.
- Shipment pushback status.
- Any retry event ids.

The minimum success state is:

- Connection status is ready.
- Latest paid test import is processed.
- Check page shows the last successful test order/import.
- Order appears in fulfilment before shipping.
- Shipment email is queued/sent.
- Platform pushback succeeds or has a visible failure with retry available when the order still has shipment data, external order data, and an active storefront connection.

## Production Ops Basics

Confirmed in the repo:

- Webhook controllers return quickly and queue import jobs.
- `jobs`, `job_batches`, and `failed_jobs` migrations exist.
- Shopify, Squarespace, and Pipe17 pushback jobs implement `ShouldQueue`.
- Failed external imports are visible in `/admin/external-imports`.
- Shipment pushback success/failure is visible in `/admin/sales/{order}` and order events.
- Admin retry exists for failed shipment pushback on shipped Shopify, Squarespace, and Pipe17 orders with active storefront connections.

Confirm on each deployed environment:

1. A queue worker is supervised and restarted on deploy.
2. The worker runs against the same `QUEUE_CONNECTION` configured for the app.
3. Failed jobs are reviewed with `php artisan queue:failed`.
4. Failed jobs can be inspected in the `failed_jobs` table.
5. Application logs are available to support for webhook, OAuth, import, and pushback failures.
6. Deploys restart workers after code changes:

```bash
php artisan queue:restart
```

Recommended worker command for database queues:

```bash
php artisan queue:work database --queue=default --tries=3 --backoff=60
```

If the app is run through `composer run dev` locally, the worker and logs are started for development only. Production still needs process supervision such as Forge daemon, Supervisor, systemd, or the hosting platform worker equivalent.

## Troubleshooting

Order missing:

1. Check `/admin/external-imports`.
2. Confirm import status.
3. For `ignored`, confirm the storefront payment status is paid.
4. For `exception`, check SKU and stock/edition availability.
5. For `failed`, check app logs and failed jobs.

Connection failed:

1. Check `/connect/storefronts`.
2. Open `/connect/storefronts/{connection}/check`.
3. Read `last_connection_error`.
4. Confirm OAuth env vars and redirect URI.
5. Re-run the OAuth connection after config is corrected.

Pushback failed:

1. Open `/admin/sales/{order}`.
2. Read `shipment_pushback_error`.
3. Confirm platform credentials and scopes.
4. Confirm carrier/tracking are present.
5. Confirm the order still has external order data and an active storefront connection.
6. Click `Retry pushback`.
7. Confirm the retry job succeeds or inspect the new failure.
