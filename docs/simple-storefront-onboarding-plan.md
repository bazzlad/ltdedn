# Simple Storefront Onboarding Plan

This is the simple way to get an artist, such as Joe Bloggs, connected to LTD EDN fulfilment.

## The Answer

Do not ask Joe to configure webhooks, handle API tokens, read developer docs, or understand fulfilment pushback.

For now, use a concierge setup through direct LTD EDN Connect:

1. Joe fills in a short connection form.
2. Joe confirms the store platform and product SKU codes.
3. Joe clicks the Shopify or Squarespace authorization link, or joins a 10 minute setup call while LTD EDN starts it.
4. LTD EDN confirms the direct webhook connection is active.
5. Joe places one test order.
6. LTD EDN confirms the order appears in the fulfilment queue.

That is the whole artist experience.

## What Joe Sees

Joe gets one short guide:

1. Confirm your product variant SKUs.
2. Give LTD EDN access to connect your store.
3. Place a test order when we ask.

No webhook language. No API language. No secrets.

## Joe's Connection Form

Ask Joe for:

- Artist name
- Store platform: Shopify or Squarespace
- Store URL
- Main contact email
- Product names and SKU codes
- Whether the products are limited editions
- Whether LTD EDN should push tracking back to the store

Example:

| Product | Variant | SKU |
| --- | --- | --- |
| Joe Bloggs Print | A2 | `JB-PRINT-A2` |
| Joe Bloggs Print | A3 | `JB-PRINT-A3` |

The only hard rule Joe needs to understand:

The SKU in Shopify or Squarespace must exactly match the SKU in LTD EDN.

## LTD EDN Internal Setup

An LTD EDN operator does the technical work:

1. Create or confirm Joe's artist record.
2. Create or confirm the local products.
3. Create the matching LTD EDN SKUs.
4. Create limited editions if the product is limited.
5. Create or confirm the single LTD EDN Pipe17 storefront connection.
6. Configure Pipe17 routing to send LTD EDN SKUs to the LTD EDN fulfillment location.
7. Place or wait for Joe's test order.
8. Check `/admin/external-imports`.
9. Check `/admin/fulfilment`.
10. Mark the connection as ready.

If anything fails, Joe should only hear the plain-language fix:

- "The SKU is missing from this product variant."
- "This SKU does not exist in LTD EDN yet."
- "This product has no available editions left."
- "The test order was not paid, so LTD EDN ignored it."

## Artist-Facing Copy

Send Joe something like this:

```text
Hi Joe,

To connect your Shopify/Squarespace store to LTD EDN fulfilment, we only need three things from you:

1. Make sure each product variant has the SKU code we gave you.
2. Click the secure Shopify/Squarespace connection link we send you, or join a short setup call with us.
3. Place one small paid test order when we confirm setup is ready.

After that, paid orders for those SKUs will flow into LTD EDN for fulfilment.

You do not need to configure webhooks or API settings yourself.
```

## Current MVP Process

Use this until a proper connection wizard exists.

### Shopify Through LTD EDN Connect

1. Joe confirms SKUs in Shopify product variants.
2. Joe clicks `Connect Shopify` and approves LTD EDN.
3. LTD EDN stores the Shopify connection in `storefront_connections`.
4. LTD EDN registers the Shopify order webhook.
5. LTD EDN confirms the connection is waiting for a test order.
6. Joe places a paid test order.
7. LTD EDN checks that the order is `processed` and appears in `/admin/fulfilment`.

### Squarespace Through LTD EDN Connect

1. Joe confirms SKUs in Squarespace products.
2. Joe clicks `Connect Squarespace` and approves LTD EDN.
3. LTD EDN stores the Squarespace connection in `storefront_connections`.
4. LTD EDN registers the Squarespace `order.create` webhook subscription.
5. LTD EDN confirms the connection is waiting for a test order.
6. Joe places a paid test order.
7. LTD EDN checks that the order is `processed` and appears in `/admin/fulfilment`.

## What To Build Next

Build a simple internal wizard first. Do not start with a fully public self-service app.

For the active build plan, see [Direct Storefront Connector Battle Plan](direct-storefront-connector-battle-plan.md). Pipe17 remains documented as fallback/reference material in [Pipe17 Fulfilment Pivot](pipe17-fulfilment-pivot-plan.md).

### Step 1: Admin Connection Wizard

Add `/admin/storefront-connections/create`.

Fields:

- Artist
- Platform
- Store name
- Store URL
- Access token or OAuth result
- Webhook secret
- Status: draft, testing, active, failed

Buttons:

- Save draft
- Show webhook URL
- Mark test order received
- Activate connection

This removes Tinker from the setup.

### Step 2: SKU Checklist

Add a connection checklist page:

- Local SKU
- Product name
- External store SKU found: yes/no
- Stock available
- Limited editions available

This gives the operator one page to validate Joe's store before the first order.

### Step 3: Test Order Status

Add a clear connection health state:

- Waiting for first webhook
- Signature failed
- Unknown SKU
- Insufficient stock
- Test order imported
- Active

This makes the setup call easy: Joe places an order, the operator watches one screen.

### Step 4: Artist Self-Service Later

Only after the internal flow is smooth, add artist-facing self-service:

1. Joe logs in.
2. Joe clicks "Connect Shopify" or "Connect Squarespace".
3. Joe authorizes LTD EDN.
4. LTD EDN creates the connection automatically.
5. Joe sees a SKU checklist.
6. Joe places a test order.
7. LTD EDN marks the connection active.

## Success Criteria

The connection flow is good enough when:

- Joe never sees an API token.
- Joe never sees a webhook secret.
- Joe only has to understand SKU matching.
- LTD EDN can validate the connection from one admin screen.
- A paid test order reaches `/admin/fulfilment`.
- Any failure is shown in plain English.

## Related Docs

- [External Order Fulfilment Usage Guide](external-order-fulfilment-usage.md)
- [External Order Processing And Fulfilment Plan](external-order-fulfilment-plan.md)
