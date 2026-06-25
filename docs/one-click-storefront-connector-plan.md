# One-Click Storefront Connector Plan

Status: paused in favour of the Order Desk fulfilment pivot.

The active v1 integration path is now [Order Desk Fulfilment Pivot](orderdesk-fulfilment-pivot-plan.md). The OAuth connector remains useful long term, but it is not the current onboarding path.

This is the better long-term answer for connecting artists such as Joe Bloggs.

The goal is:

1. Joe logs in to LTD EDN.
2. Joe clicks `Connect Shopify` or `Connect Squarespace`.
3. Joe approves access on the store platform.
4. LTD EDN creates the connection, registers webhooks, checks SKUs, and waits for a test order.
5. Joe sees `Connected`.

Joe should not copy API tokens, paste webhook secrets, or read platform developer docs.

## Product Name

Build this as `LTD EDN Connect`.

It is a small connector app/service inside LTD EDN, not a browser extension.

## Artist Experience

### Step 1: Choose Store

Joe sees:

```text
Connect your store

Choose where you sell this product:
[ Connect Shopify ]
[ Connect Squarespace ]
```

### Step 2: Platform Approval

Joe is sent to Shopify or Squarespace.

He sees the platform's native approval screen and clicks approve/install/accept.

### Step 3: LTD EDN Setup

After approval, Joe returns to LTD EDN.

LTD EDN shows:

```text
Connected to Joe Bloggs Store

Next: match your SKUs
```

### Step 4: SKU Check

Joe sees a short table:

| LTD EDN SKU | Store SKU found | Status |
| --- | --- | --- |
| `JB-PRINT-A2` | Yes | Ready |
| `JB-PRINT-A3` | No | Add this SKU in your store |

### Step 5: Test Order

Joe sees:

```text
Place one small paid test order.
We will confirm when it reaches LTD EDN.
```

Once the order imports, LTD EDN shows:

```text
Connected and ready.
```

## Shopify Approach

Build a Shopify app for LTD EDN.

The flow:

1. Joe enters his Shopify `.myshopify.com` store domain or clicks `Connect Shopify`.
2. LTD EDN redirects him to Shopify OAuth/install.
3. Joe approves the requested permissions.
4. Shopify redirects back to LTD EDN with an authorization code.
5. LTD EDN exchanges the code for an offline access token.
6. LTD EDN creates or updates `storefront_connections`.
7. LTD EDN registers order webhooks for that shop.
8. LTD EDN uses the access token for fulfilment/tracking pushback.

### Shopify App Type

For multiple independent artists, this should be a public Shopify app.

It can have limited visibility if we do not want broad marketplace discovery, but it still needs to follow Shopify's public app review/distribution path.

Custom Shopify apps are not the right default for this, because they are intended for one store or tightly limited store ownership patterns. They do not give us a clean "every artist clicks install" flow.

### Shopify MVP

First version:

- OAuth install.
- Store connection creation.
- Order-created webhook subscription.
- Storefront host validation.
- Encrypted token storage.
- Connection status screen.
- Test-order checker.

Later:

- Fetch Shopify fulfilment order ids automatically.
- Push stock updates from LTD EDN to Shopify.
- Better SKU discovery/import.

## Squarespace Approach

Build a Squarespace OAuth connection flow.

The flow:

1. Joe clicks `Connect Squarespace`.
2. LTD EDN redirects him through Squarespace OAuth.
3. Joe approves access.
4. Squarespace redirects back to LTD EDN.
5. LTD EDN stores the access token.
6. LTD EDN creates a webhook subscription through the Squarespace Webhook Subscriptions API.
7. LTD EDN stores the returned webhook secret.
8. LTD EDN creates or updates `storefront_connections`.
9. LTD EDN waits for a paid test order.

Squarespace is less "plugin marketplace" than Shopify, but the artist experience can still be click-approve-return.

## Minimum Data Model Changes

The current `storefront_connections` table is close, but add fields for a better connector:

- `external_shop_id`
- `external_shop_domain`
- `oauth_scopes`
- `token_expires_at`
- `refresh_token`
- `webhook_subscription_id`
- `connection_status`
- `last_connection_error`
- `tested_at`
- `activated_at`

Keep credentials and secrets encrypted.

## New Routes To Build

Artist/admin UI:

- `GET /connect/storefronts`
- `GET /connect/shopify/start`
- `GET /connect/shopify/callback`
- `GET /connect/squarespace/start`
- `GET /connect/squarespace/callback`
- `GET /connect/storefronts/{connection}/check`

Admin UI:

- `GET /admin/storefront-connections/create`
- `GET /admin/storefront-connections/{connection}`
- `POST /admin/storefront-connections/{connection}/test`
- `POST /admin/storefront-connections/{connection}/activate`

## Build Order

### Phase 1: Internal Wizard

Build this first so LTD EDN can onboard Joe without Tinker.

Outcome:

- Operator selects artist and platform.
- LTD EDN creates the connection.
- Operator gets a webhook URL and status checklist.
- No code console needed.

### Phase 2: Shopify OAuth

Build the real `Connect Shopify` button.

Outcome:

- Joe approves in Shopify.
- LTD EDN stores token and shop domain.
- LTD EDN registers the webhook automatically.
- Joe only needs to fix SKUs and place a test order.

### Phase 3: Squarespace OAuth

Build the real `Connect Squarespace` button.

Outcome:

- Joe approves in Squarespace.
- LTD EDN creates webhook subscription automatically.
- LTD EDN stores the returned webhook secret.
- Joe only needs to fix SKUs and place a test order.

### Phase 4: SKU Discovery

Use platform APIs to read product variants and compare external SKUs to LTD EDN SKUs.

Outcome:

- Joe sees exactly which variants are ready.
- Missing SKU problems are fixed before any customer order fails.

## Success Criteria

This is done when:

- Joe clicks one button.
- Joe approves access on Shopify or Squarespace.
- LTD EDN creates the connection automatically.
- LTD EDN registers webhooks automatically.
- Joe sees a SKU checklist.
- Joe places a test order.
- The order appears in `/admin/fulfilment`.
- No artist sees a webhook secret or API token.

## Short Answer

Yes, we can build the simple tool.

For Shopify, it is an LTD EDN Shopify app.

For Squarespace, it is an LTD EDN OAuth connector that creates webhook subscriptions.

Both can be presented to Joe as the same simple experience:

```text
Connect store -> approve access -> check SKUs -> place test order -> done
```
