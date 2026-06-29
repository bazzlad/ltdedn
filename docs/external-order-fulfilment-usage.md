# External Order Fulfilment Usage Guide

This guide covers the current external order fulfilment pipeline for direct Shopify and Squarespace orders. Pipe17 remains a fallback-only bridge.

The external storefront remains responsible for checkout, payment, tax, and fraud checks. LTD EDN imports paid orders, matches line items by SKU, allocates stock or limited editions, exposes the order in the admin fulfilment queue, records shipment tracking, emails the buyer, and attempts to push tracking back to the source storefront.

## What Exists Now

- `POST /api/webhooks/shopify/{connection}` accepts signed Shopify order payloads and queues import work.
- `POST /api/webhooks/squarespace/{connection}` accepts signed Squarespace order payloads and queues import work.
- `/connect/storefronts` exposes Shopify and Squarespace OAuth connection starts for artists/operators.
- `php artisan pipe17:pull-shipping-requests` can import Pipe17 Shipping Requests only if the fallback bridge is deliberately re-enabled.
- `/admin/storefront-connections` lists configured connections and import counts.
- `/admin/external-imports` lists webhook import attempts and their status.
- `/admin/fulfilment` shows paid, unshipped, non-exception orders ready to ship.
- `/admin/sales` lists imported orders with filters for status, platform, and exception state.
- `/admin/sales/{order}` shows order details, line items, events, shipment pushback status, and retry for failed shipment pushback.

Connection creation is exposed at `/admin/storefront-connections/create`.

Operator onboarding steps are maintained in `docs/storefront-operator-onboarding-runbook.md`.

## Squarespace OAuth Hold

Squarespace real-store validation is currently blocked on OAuth client approval from Squarespace. A request has been submitted for LTD EDN Connect with:

- Redirect URI: `https://test.ltdedn.com/connect/squarespace/callback`
- Scopes: `website.orders`, `website.orders.read`, `website.products.read`
- Terms URL: `https://test.ltdedn.com/terms`
- Privacy URL: `https://test.ltdedn.com/privacy`

When Squarespace issues credentials, configure the test environment:

```env
SQUARESPACE_CONNECT_CLIENT_ID=...
SQUARESPACE_CONNECT_CLIENT_SECRET=...
SQUARESPACE_CONNECT_SCOPES=website.orders,website.orders.read,website.products.read
```

Then clear/reload Laravel config before retrying `/connect/storefronts`.

## Data Requirements

Each externally sold variant must have a matching local `product_skus.sku_code`.

For a successful import:

- The webhook must be signed with the connection's `webhook_secret`.
- The connection platform must match the endpoint.
- The external order payment status must be paid.
- Every line item must include a SKU that exists locally and is active.
- The SKU must have enough `stock_on_hand`.
- Limited products must have enough available `product_editions`.

If a paid order has an unknown SKU or insufficient stock, LTD EDN creates an exception order instead of silently dropping it.

## Local Setup

Install and migrate as usual:

```bash
composer install
npm install
php artisan migrate
```

Run the app:

```bash
composer run dev
```

For webhook testing, run a queue worker too. `composer run dev` starts the app, worker, logs, and frontend together.

## Create Local Test Data

Open Tinker:

```bash
php artisan tinker
```

Paste this setup for a Shopify test connection and one limited test SKU:

```php
use App\Enums\ProductEditionStatus;
use App\Enums\StorefrontPlatform;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\StorefrontConnection;
use App\Models\User;

$admin = User::updateOrCreate(['email' => 'admin@example.test'], [
    'name' => 'Local Admin',
    'password' => bcrypt('password'),
    'role' => 'admin',
]);

$artist = Artist::updateOrCreate(['slug' => 'local-test-artist'], [
    'name' => 'Local Test Artist',
    'owner_id' => $admin->id,
]);

$product = Product::updateOrCreate(['slug' => 'local-test-print'], [
    'name' => 'Local Test Print',
    'artist_id' => $artist->id,
    'is_limited' => true,
    'edition_size' => 3,
]);

$sku = ProductSku::updateOrCreate(['sku_code' => 'TEST-PRINT-A2'], [
    'product_id' => $product->id,
    'stock_on_hand' => 3,
    'stock_reserved' => 0,
    'is_active' => true,
]);

foreach ([1, 2, 3] as $number) {
    ProductEdition::updateOrCreate(['product_id' => $product->id, 'number' => $number], [
        'product_sku_id' => $sku->id,
        'status' => ProductEditionStatus::Available,
    ]);
}

$connection = StorefrontConnection::updateOrCreate(['platform' => StorefrontPlatform::Shopify->value, 'name' => 'Local Shopify Test'], [
    'artist_id' => $artist->id,
    'store_url' => 'https://example.myshopify.com',
    'credentials' => ['access_token' => 'local-test-token'],
    'webhook_secret' => 'local-shopify-secret',
]);

$connection->id;
```

Keep the returned connection id. The webhook URL is:

```text
http://127.0.0.1:8000/api/webhooks/shopify/{connection_id}
```

The example shell commands below use `CONNECTION_ID=1`. Replace that value with the id returned by Tinker.

## Test A Successful Shopify Import

Create a payload file:

```bash
cat > /tmp/ltdedn-shopify-order.json <<'JSON'
{
  "id": "local-shopify-order-1001",
  "order_number": "S1001",
  "email": "buyer@example.test",
  "currency": "GBP",
  "financial_status": "paid",
  "fulfillment_status": null,
  "subtotal_price": "25.00",
  "total_tax": "0.00",
  "total_price": "25.00",
  "total_shipping_price_set": {
    "shop_money": { "amount": "0.00" }
  },
  "shipping_address": {
    "name": "Buyer Name",
    "address1": "1 Test Street",
    "city": "London",
    "zip": "N1 1AA",
    "country_code": "GB"
  },
  "line_items": [
    {
      "id": 501,
      "sku": "TEST-PRINT-A2",
      "title": "Local Test Print",
      "variant_title": "A2",
      "quantity": 1,
      "price": "25.00"
    }
  ]
}
JSON
```

Sign and post it:

```bash
CONNECTION_ID=1
SECRET='local-shopify-secret'
BODY="$(cat /tmp/ltdedn-shopify-order.json)"
SIGNATURE="$(php -r 'echo base64_encode(hash_hmac("sha256", file_get_contents($argv[1]), $argv[2], true));' /tmp/ltdedn-shopify-order.json "$SECRET")"

curl -sS -X POST "http://127.0.0.1:8000/api/webhooks/shopify/${CONNECTION_ID}" \
  -H 'Content-Type: application/json' \
  -H "X-Shopify-Hmac-Sha256: ${SIGNATURE}" \
  -H 'X-Shopify-Webhook-Id: local-delivery-1001' \
  --data "$BODY"
```

Expected response:

```json
{"status":"queued"}
```

Then check:

- `/admin/external-imports` shows the import as `processed`.
- `/admin/fulfilment` shows the order ready to ship.
- `/admin/sales` shows a paid Shopify order.
- The SKU stock is reduced by the ordered quantity.
- One limited edition is marked sold.

## Test Duplicate Delivery Handling

Run the same `curl` command again with the same body and `X-Shopify-Webhook-Id`.

Expected behavior:

- The response is still `200`.
- No duplicate order is created.
- No extra stock is consumed.
- The existing import record remains the only import after the queued job runs.

## Test An Exception Order

Change the line item SKU to a value that does not exist:

```bash
perl -0pi -e 's/TEST-PRINT-A2/MISSING-SKU/g' /tmp/ltdedn-shopify-order.json
perl -0pi -e 's/local-shopify-order-1001/local-shopify-order-1002/g' /tmp/ltdedn-shopify-order.json
```

Sign and post again with a new delivery id:

```bash
BODY="$(cat /tmp/ltdedn-shopify-order.json)"
SIGNATURE="$(php -r 'echo base64_encode(hash_hmac("sha256", file_get_contents($argv[1]), $argv[2], true));' /tmp/ltdedn-shopify-order.json "$SECRET")"

curl -sS -X POST "http://127.0.0.1:8000/api/webhooks/shopify/${CONNECTION_ID}" \
  -H 'Content-Type: application/json' \
  -H "X-Shopify-Hmac-Sha256: ${SIGNATURE}" \
  -H 'X-Shopify-Webhook-Id: local-delivery-1002' \
  --data "$BODY"
```

Expected response:

```json
{"status":"queued"}
```

Then check:

- `/admin/external-imports` shows `exception`.
- `/admin/sales?exception=1` shows the blocked order.
- The sales detail page shows the exception reason.
- Admin users receive an `ExternalOrderExceptionNotification`.

## Test Squarespace Import

This section covers local/manual signed webhook testing. It does not prove the live OAuth/webhook subscription path. The real Squarespace test remains blocked until Squarespace OAuth credentials are issued.

Create a Squarespace connection in Tinker:

```php
use App\Enums\StorefrontPlatform;
use App\Models\Artist;
use App\Models\StorefrontConnection;

$artist = $artist ?? Artist::where('slug', 'local-test-artist')->firstOrFail();

$square = StorefrontConnection::updateOrCreate(['platform' => StorefrontPlatform::Squarespace->value, 'name' => 'Local Squarespace Test'], [
    'artist_id' => $artist->id,
    'store_url' => 'https://example.squarespace.com',
    'credentials' => ['access_token' => 'local-test-token'],
    'webhook_secret' => 'local-squarespace-secret',
]);

$square->id;
```

Create a payload:

```bash
cat > /tmp/ltdedn-squarespace-order.json <<'JSON'
{
  "order": {
    "id": "local-squarespace-order-1001",
    "orderNumber": "SQ1001",
    "customerEmail": "buyer@example.test",
    "currency": "GBP",
    "paymentStatus": "paid",
    "subtotal": "25.00",
    "grandTotal": "25.00",
    "shippingAddress": {
      "fullName": "Buyer Name",
      "address1": "1 Test Street",
      "city": "London",
      "postalCode": "N1 1AA",
      "countryCode": "GB"
    },
    "lineItems": [
      {
        "id": "line-1",
        "sku": "TEST-PRINT-A2",
        "productName": "Local Test Print",
        "quantity": 1,
        "unitPricePaid": "25.00"
      }
    ]
  }
}
JSON
```

Sign and post it. Squarespace sends hex secrets and this app converts them to bytes before checking the HMAC. This local example uses a raw manual secret:

```bash
SQUARE_CONNECTION_ID=2
SQUARE_SECRET='local-squarespace-secret'
BODY="$(cat /tmp/ltdedn-squarespace-order.json)"
SIGNATURE="$(php -r 'echo hash_hmac("sha256", file_get_contents($argv[1]), $argv[2]);' /tmp/ltdedn-squarespace-order.json "$SQUARE_SECRET")"

curl -sS -X POST "http://127.0.0.1:8000/api/webhooks/squarespace/${SQUARE_CONNECTION_ID}" \
  -H 'Content-Type: application/json' \
  -H "X-Squarespace-Signature: ${SIGNATURE}" \
  -H 'X-Squarespace-Webhook-Id: local-square-delivery-1001' \
  --data "$BODY"
```

Expected response:

```json
{"status":"queued"}
```

## Fulfil An Imported Order

1. Log in as an admin.
2. Open `/admin/fulfilment`.
3. Find the imported order.
4. Enter the carrier and tracking number in the fulfilment card.
5. Submit the shipment form.

On first shipment:

- `shipping_carrier`, `shipping_tracking_number`, and `shipped_at` are stored on the order.
- A `shipped` order event is recorded.
- A buyer shipment email is queued if `customer_email` is present.
- A platform pushback job is queued for Shopify, Squarespace, or fallback Pipe17 orders.

Run a queue worker if one is not already running:

```bash
php artisan queue:listen --tries=1
```

## Pushback Notes

Pushback is best tested against a real sandbox/dev storefront.

Shopify pushback requires:

- `store_url` using HTTPS and a `*.myshopify.com` host.
- `credentials.access_token`.
- Shopify OAuth scopes for order fulfillment:
  `read_orders`, `write_fulfillments`, `read_merchant_managed_fulfillment_orders`, and
  `write_merchant_managed_fulfillment_orders`.

The Shopify pushback job fetches and caches fulfillment order ids in
`orders.meta.shopify_fulfillment_order_ids` before creating the fulfillment. If Shopify returns HTTP 403
for this lookup, redeploy the app configuration with the fulfillment-order scopes and reinstall the
storefront app so the merchant approves the new permissions.

Squarespace pushback requires:

- `store_url` using HTTPS and a `*.squarespace.com` host.
- `credentials.access_token`.
- OAuth credentials configured in `services.squarespace_connect` so expired access tokens can be refreshed.

Pipe17 pushback requires:

- `storefront_connections.external_shop_id` set to the Pipe17 fulfillment location ID.
- `storefront_connections.credentials.api_key` set to the Pipe17 API key, or `PIPE17_API_KEY` configured.
- `orders.external_order_id` set to the Pipe17 Shipping Request ID.
- `PIPE17_SCHEDULE_ENABLED=true` if LTD EDN should poll Pipe17 automatically.

Pushback success or failure is visible on the sales detail page and recorded as an order event. If a shipped Shopify, Squarespace, or Pipe17 order has `shipment_pushback_status=failed`, shipment data, external order data, and an active storefront connection, an admin can open `/admin/sales/{order}` and click `Retry pushback`. The retry clears the previous error, sets the status back to `pending`, records a `shipment_pushback_retry_queued` event, and queues the platform pushback job again.

Legacy `orderdesk` rows and fallback `pipe17` rows remain readable in admin screens so old data does not break enum casts, but neither platform is part of normal storefront creation.

## Operational Statuses

External import statuses:

- `processed`: paid order imported and stock allocated.
- `ignored`: webhook accepted but not imported, usually because the order was not paid or was already imported.
- `exception`: paid order imported as an exception because SKU or stock allocation failed.
- `failed`: unexpected importer error.

Order statuses:

- `paid`: ready for fulfilment unless already shipped.
- `exception`: needs admin review before fulfilment.
- `pending`, `cancelled`, `failed`: not in the fulfilment queue.

## Useful Verification Commands

Run the focused tests:

```bash
php artisan test tests/Feature/ExternalOrders tests/Feature/Admin/FulfilmentTest.php
```

Run the full backend suite:

```bash
composer test
```

Build the frontend:

```bash
npm run build
```

Known local warning: PHP 8.5 emits deprecation warnings from framework/PDO/PHPUnit internals. They do not indicate a failure when the test command exits successfully.
