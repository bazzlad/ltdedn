# Shop Release Plan

## Status
- Phase 0: In progress
- Owner: Engineering
- Scope: Sell physical products with SKU variants, stock, Stripe checkout, sales ops

---

## Phase 0: Architecture Lock

### 0.1 Canonical Terms
- **Product**: merch concept displayed in catalogue (for example, LTD/EDN Tee).
- **Variant Axis**: option dimension (for example, Size, Colour).
- **Variant Value**: option value on an axis (for example, Medium, Black).
- **SKU**: concrete purchasable stock row, with price and quantity (for example, TEE-BLK-M).
- **Reservation**: temporary stock hold before payment completes.
- **Order**: purchase transaction record.
- **Order Item**: line item for a specific SKU and quantity.

### 0.2 State Machines

#### Product sale lifecycle
- `draft` -> `active` -> `paused` -> `archived`

#### Order lifecycle
- `pending` -> `paid`
- `pending` -> `failed`
- Optional future: `paid` -> `refunded`

#### Inventory reservation lifecycle
- `active` -> `consumed` (checkout paid)
- `active` -> `expired` (timeout)
- `active` -> `released` (checkout failed/cancelled)

### 0.3 Data Model (target)

#### products (existing, extend)
- add `is_sellable` boolean default false
- add `sale_status` enum(`draft`,`active`,`paused`,`archived`) default `draft`
- add `currency` char(3) default `gbp`
- add `sale_starts_at` nullable timestamp
- add `sale_ends_at` nullable timestamp

#### product_variant_axes (new)
- id
- product_id FK
- name (e.g. Size)
- sort_order

#### product_variant_values (new)
- id
- axis_id FK
- value (e.g. XL)
- sort_order

#### product_skus (new)
- id
- product_id FK
- sku_code unique
- price_amount integer (minor units)
- compare_at_amount nullable integer
- currency char(3)
- stock_on_hand integer unsigned
- stock_reserved integer unsigned default 0
- is_active boolean default true
- attributes json (resolved axis/value map snapshot)
- timestamps

#### inventory_reservations (new)
- id
- product_sku_id FK
- order_id FK nullable (created with pending order)
- quantity integer unsigned
- status enum(`active`,`consumed`,`expired`,`released`)
- expires_at timestamp
- consumed_at nullable timestamp
- released_at nullable timestamp
- release_reason nullable string
- timestamps
- index(status, expires_at)

#### orders (existing, extend)
- keep current totals and stripe ids
- keep `order_creation_key` unique
- add `checkout_expires_at` nullable timestamp
- add index(status, paid_at)

#### order_items (existing, extend)
- add `product_sku_id` FK nullable -> backfill then enforce not null in later migration
- keep product/slug/name snapshot fields
- keep price snapshots (`unit_amount`, `line_total_amount`)
- add `sku_code_snapshot` string
- add `attributes_snapshot` json

### 0.4 Route Map

#### Web routes
- `GET /shop` catalogue
- `GET /shop/product/{slug}` product detail
- `POST /shop/checkout` create reservation + order + stripe session
- `GET /shop/success/{order}`
- `GET /shop/cancel/{order}`

#### API/Webhook routes
- `POST /api/webhooks/stripe` (preferred API grouping)

#### Admin routes
- Product sale controls
- SKU CRUD and stock adjustments
- Sales/orders listing

### 0.5 Stripe Event Mapping
- `checkout.session.completed` -> mark order paid, consume reservation, decrement available stock via reservation accounting
- `checkout.session.expired` -> mark order failed, release reservation
- `checkout.session.async_payment_failed` -> mark order failed, release reservation

Idempotency:
- add `stripe_events` table (event_id unique, type, processed_at, payload hash)
- ignore duplicate webhook events

### 0.6 Notifications and Emails
- customer: payment confirmation email on order paid
- internal: sale notification email (later optional Slack)

### 0.7 Sales Visibility (MVP)
- admin order list: status, date, total, customer, stripe refs
- order detail with items and SKU snapshots
- summary cards: today paid count, today revenue, month revenue

---

## Phase 1 (next): Migration and model implementation checklist

- [ ] Add product sellability columns
- [ ] Create variant axis/value tables
- [ ] Create sku table
- [ ] Create reservation table
- [ ] Add webhook events table
- [ ] Extend orders/order_items for SKU-level snapshots
- [ ] Add model relationships and casts
- [ ] Add migration tests + factory updates

---

## Decisions Required Before Phase 1 Build

1. **Variant model depth for v1**
	- Option A: full axis/value tables now (recommended)
	- Option B: SKU attributes json only now, normalize later

2. **Stock policy**
	- Allow oversell: no
	- Reservation TTL: default proposed `15 minutes`

3. **Pricing policy**
	- Currency fixed to GBP for v1: yes/no
	- Per-SKU price allowed: yes (recommended)

4. **Checkout quantity policy**
	- v1 max quantity per checkout: `1` or configurable (default recommendation: `1` for first release)

5. **Webhook route location**
	- Keep in web.php with CSRF bypass, or move to api.php (recommended)

6. **Notification target**
	- Internal sale notifications via email only for v1, Slack later: yes/no

7. **Backoffice scope for first release**
	- minimum: view sales only
	- or include manual status updates/refund notes in v1
