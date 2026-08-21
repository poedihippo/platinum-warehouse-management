# PRD.md — Product Requirements Document: BEJO Warehouse Management

> **Product:** BEJO — Warehouse Management System
> **Company:** Platinum Adi Sentosa
> **Version:** 1.0 (with Loyalty Program v1)
> **Last Updated:** 2026-08-20

---

## 1. Product Overview

### 1.1 Vision

BEJO is a backend REST API system that manages the complete warehouse operations of Platinum Adi Sentosa, a feed/pakan distributor. It handles inventory tracking via QR-coded serial numbers, sales and delivery workflows, physical stock audits, and a customer loyalty program.

### 1.2 Purpose

| Capability | Description |
|------------|-------------|
| **Stock Management** | Track physical inventory per warehouse using QR-coded serial numbers or quantity-based tracking |
| **Sales Orders** | Process customer purchases with pricing, discounts, vouchers, and tax |
| **Delivery Orders** | Manage partial shipments against sales orders with stock assignment |
| **Receive Orders** | Record inbound stock from suppliers and create stock records |
| **Stock Opname** | Perform physical inventory audits via QR scanning |
| **Stock Adjustments** | Approve manual stock increments/decrements |
| **Invoice & Payment** | Generate invoices, track payments, export PDFs |
| **Voucher System** | Create and distribute discount vouchers |
| **Loyalty Program** | Customer-facing points-and-rewards program |

### 1.3 System Boundaries

```
┌─────────────────────────────────────────────────────────┐
│                    BEJO Backend API                      │
│                     (This Repo)                          │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │  Warehouse   │  │   Loyalty    │  │   Invoice    │  │
│  │  Management  │  │   Program    │  │   & Payment  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└───────────────────────┬─────────────────────────────────┘
                        │ REST API
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│   BEJO CMS   │ │  Customer   │ │  QR Verify   │
│  (Admin FE)  │ │  Loyalty FE │ │  Public FE   │
│  Vercel      │ │  Vercel     │ │  Vercel      │
└──────────────┘ └──────────────┘ └──────────────┘
```

---

## 2. User Roles & Permissions

### 2.1 User Types

| Role | Description | Auth Guard | Token Ability |
|------|-------------|------------|---------------|
| `admin` | Full system access, bypasses all permissions | `sanctum` | `['*']` |
| `reseller` | Can place orders, view own data, gets auto-discounts per brand | `sanctum` | `['*']` |
| `spg` | Field sales staff, creates orders on behalf of customers | `sanctum` | `['*']` |
| `customer` | Basic customer user type (not loyalty) | `sanctum` | `['*']` |
| `dealer` | Dealer-type customer | `sanctum` | `['*']` |
| `customer_event` | Event-specific customer | `sanctum` | `['*']` |
| `loyalty manager` | Manages loyalty program | `sanctum` | `['loyalty']` |
| `loyalty reviewer` | Reviews and approves/rejects claims | `sanctum` | `['loyalty']` |
| `loyalty prize manager` | Manages prize catalog | `sanctum` | `['loyalty']` |
| `loyalty fulfillment` | Handles redemption shipping | `sanctum` | `['loyalty']` |

### 2.2 Permission Structure

19 top-level permission groups, each with `create`, `read`, `update`, `delete` sub-permissions:

```
user_access          role_access           permission_access
user_discount_access product_access        product_category_access
product_brand_access product_unit_access   supplier_access
warehouse_access     uom_access            receive_order_access
sales_order_access   invoice_access        order_access
delivery_order_access stock_access         stock_opname_access
stock_history_access adjustment_request_access
product_unit_blacklist_access              setting_access
payment_access       voucher_access
```

### 2.3 Related Permissions

Granting certain permissions auto-includes related permissions:

| Granted | Also Grants |
|---------|-------------|
| `sales_order_access` | `user_access`, `payment_access`, `invoice_access`, `order_access` + all sub-permissions |

### 2.4 Loyalty System Separate Auth

| System | Model | Guard | PK Type | Notes |
|--------|-------|-------|---------|-------|
| Warehouse Staff | `App\Models\User` | `sanctum` | bigint | Staff/admin accounts |
| Loyalty Customer | `App\Models\Loyalty\LoyaltyUser` | `loyalty` | ULID | End-customer accounts |

---

## 3. Core Modules

### 3.1 Product Management

#### 3.1.1 Product Categories

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| name | string(100) | Required |
| timestamps | | created_at, updated_at |
| soft_deletes | | deleted_at |

#### 3.1.2 Product Brands

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| name | string(100) | Required |
| logo_path | string(255) | Nullable, S3 path |
| timestamps | | |
| soft_deletes | | |

#### 3.1.3 Products

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| product_category_id | FK → product_categories | Required |
| product_brand_id | FK → product_brands | Required |
| company | string(10) | Default: `CompanyEnum::PAS`, values: `pas` or `pa` |
| name | string(100) | Required |
| timestamps | | |
| soft_deletes | | |

**Side Effects:**
- On User creation (type=Reseller): auto-creates `UserDiscount` for every `ProductBrand`

#### 3.1.4 Product Units

The core sellable item. A Product can have multiple ProductUnits (different sizes, variants).

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| product_id | FK → products | ON DELETE CASCADE |
| refer_id | unsigned int | Nullable, self-referential to product_units.id (for packaging references) |
| uom_id | FK → uoms | Required |
| name | string(100) | Required |
| price | integer | Default: 0 |
| refer_qty | unsigned small int | Nullable, quantity reference |
| code | string(50) | Required, unique identifier |
| is_generate_qr | boolean | Default: true — determines stock tracking method |
| is_ppn | boolean | Default: false — VAT eligibility |
| points_per_unit | integer | Default: 0 — loyalty points value |
| loyalty_eligible | boolean | Default: false, indexed — eligible for loyalty claims |
| timestamps | | |
| soft_deletes | | |

**Side Effects:**
- `boot('created')`: If `refer_id` is null, dispatches `ProductUnitCreated` event
- `boot('deleting')`: Appends `'-deleted'` to `code` field before deletion
- `ProductUnitCreated` → `CreateStockProductUnit` listener (queued): Creates `StockProductUnit` for EVERY warehouse (including soft-deleted)

**Scopes:**
- `search($query)` — search by name or code
- `whereProductBrandId($id)`
- `whereProductCategoryId($id)`
- `whereCompany($company)`

#### 3.1.5 UOMs (Units of Measure)

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| name | string(100) | Required |
| timestamps | | |

#### 3.1.6 Product Unit Relations

Links product units for packaging/variant relationships.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| product_unit_id | FK → product_units | ON DELETE CASCADE |
| related_product_unit_id | FK → product_units | Required |
| qty | unsigned small int | Quantity multiplier |
| timestamps | | |

#### 3.1.7 Product Unit Blacklist

Blocks certain product units from being used.

| Field | Type | Notes |
|-------|------|-------|
| product_unit_id | FK → product_units | Required |
| timestamps | | |

---

### 3.2 Stock Management

#### 3.2.1 Stock Product Units (Junction Table)

The bridge between `product_units` and `warehouses`. Every product unit gets a slot in every warehouse automatically.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| product_unit_id | FK → product_units | Required |
| warehouse_id | FK → warehouses | Required |
| qty | integer | Default: 0 — quantity for non-QR items |
| timestamps | | |
| soft_deletes | | |

**Traits:** `Tenanted` (warehouse-scoped queries)

**Auto-Creation:**
1. When `ProductUnit` is created (refer_id = null) → creates `StockProductUnit` for every warehouse
2. When `Warehouse` is created → creates `StockProductUnit` for every `ProductUnit`

#### 3.2.2 Stocks (Individual Serial/QR Records)

The core tracking record for QR-generated items. Each physical unit gets its own row.

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** (26-char) | PRIMARY KEY — this IS the QR code payload |
| printed_at | timestamp | Nullable, when QR was printed |
| in_printing_queue | boolean | Default: false |
| printer_id | tiny int | Nullable |
| parent_id | ULID FK → stocks | Nullable, self-referential for grouping |
| batch_number | string(255) | Nullable |
| batch_number_jp | string(255) | Nullable |
| stock_product_unit_id | FK → stock_product_units | Required |
| adjustment_request_id | FK → adjustment_requests | Nullable, indexed |
| receive_order_id | FK → receive_orders | Nullable |
| receive_order_detail_id | FK → receive_order_details | Nullable |
| description | string(255) | Nullable |
| qr_code | text | Nullable, S3 path (currently unused) |
| scanned_count | integer | Default: 0 |
| scanned_datetime | datetime | Nullable |
| is_tempel | boolean | Default: true |
| expired_date | date | Nullable (out-of-band migration) |
| timestamps | | |
| soft_deletes | | |

**Key Relationships:**
- `belongsTo` StockProductUnit
- `belongsTo` self (parent)
- `hasMany` self (children)
- `hasMany` SalesOrderItem
- `belongsTo` ReceiveOrder
- `belongsTo` ReceiveOrderDetail

**Available Stock Logic:**
A stock is "available" if it has no non-returned `SalesOrderItem` linked to it.

#### 3.2.3 Stock Histories

Polymorphic audit trail for all stock changes.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| model_type | string(255) | Morph type |
| model_id | unsigned big int | Morph ID |
| user_id | unsigned int | Indexed |
| stock_product_unit_id | FK → stock_product_units | Required |
| value | unsigned int | Default: 0 — quantity changed |
| is_increment | boolean | Default: true |
| description | text | Nullable |
| ip | string(30) | Nullable |
| agent | text | Nullable |
| timestamps | | |

**Possible parent models (model_type):**
- `StockProductUnit`
- `ReceiveOrderDetail`
- `AdjustmentRequest`
- `SalesOrderDetail`
- `StockOpnameDetail`

#### 3.2.4 Temporary Stocks

Staging area for stock operations.

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| created_by_id | integer | Nullable |
| timestamps | | |

#### 3.2.5 Stock Tracking Dual Model

| Type | Flag | How Stock is Tracked |
|------|------|---------------------|
| **QR Items** | `is_generate_qr = true` | Individual `Stock` rows with ULID PKs. Count available stocks. |
| **Non-QR Items** | `is_generate_qr = false` | `StockProductUnit.qty` integer field. |

---

### 3.3 Warehouses

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| code | string(100) | Required |
| name | string(100) | Required |
| company_name | string(100) | Required |
| timestamps | | |
| soft_deletes | | |

**Side Effects:**
- `boot('created')`: Creates `StockProductUnit` for every `ProductUnit` (including soft-deleted)

**Tenancy:**
- Admin sees all warehouses
- Non-admin users see only warehouses assigned via `user_warehouses` pivot

---

### 3.4 Receive Order (Inbound Stock)

#### 3.4.1 Receive Orders

Records goods received from suppliers.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| user_id | FK → users | Creator |
| supplier_id | FK → suppliers | Nullable |
| warehouse_id | FK → warehouses | Nullable |
| invoice_no | string(255) | Nullable, UNIQUE |
| invoice_date | date | Nullable |
| invoice_amount | integer | Default: 0 |
| purchase_order_no | string(255) | Nullable |
| warehouse_string_id | string(255) | Nullable |
| vendor_id | string(255) | Nullable |
| sequence_no | string(255) | Nullable |
| receive_datetime | datetime | Required |
| is_done | boolean | Default: false |
| done_at | timestamp | Nullable |
| timestamps | | |

**Traits:** `Tenanted`, `FilterStartEndDate`

#### 3.4.2 Receive Order Details

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| receive_order_id | FK → receive_orders | ON DELETE CASCADE |
| product_unit_id | FK → product_units | Required |
| qty | integer | Default: 0 — received quantity |
| item_unit | string(50) | Required |
| bruto_unit_price | integer | Default: 0 |
| adjust_qty | integer | Default: 0 — verified quantity (may differ from qty) |
| is_verified | tiny int | Default: 0 |
| timestamps | | |

#### 3.4.3 Receive Order Verification Flow

```
1. Admin creates ReceiveOrder with ReceiveOrderDetails
2. Admin verifies each detail (is_verified = 1)
3. Admin calls PUT /receive-orders/{id}/done
4. boot('updated') detects is_done changed to true
5. Fires VerifiedROEvent
6. CreateStockROListener (queued) processes:
   ├─ QR products (is_generate_qr = true):
   │   → Creates N individual Stock records (N = adjust_qty)
   │   → Each Stock has ULID as ID (QR payload)
   │   → Linked to StockProductUnit
   └─ Non-QR products:
       → Increments StockProductUnit.qty by adjust_qty
7. StockHistory record created (is_increment = true)
```

**Unverify Flow:**
```
1. Admin sets is_done = false
2. UnverifiedROEvent fires
3. DeleteStockROListener (queued):
   → Force-deletes all associated Stock records
   → Decrements StockProductUnit.qty for non-QR items
   → Deletes QR code files from S3 storage
   → Creates StockHistory (is_increment = false)
```

---

### 3.5 Sales Order

#### 3.5.1 Sales Orders

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| voucher_id | FK → vouchers | Nullable |
| is_invoice | boolean | Default: false — if true, reserves stock |
| user_id | FK → users | Creator |
| reseller_id | FK → users | The reseller placing the order |
| spg_id | FK → users | Nullable, SPG field sales |
| warehouse_id | FK → warehouses | Nullable |
| invoice_no | string(50) | Auto-generated: `PAS/SO/MM/YY/NN` |
| type | string(10) | Default: `SalesOrderType::DEFAULT` |
| raw_source | json | Original request data from frontend |
| records | json | Snapshot of reseller data |
| shipment_fee | integer | Default: 0 |
| additional_discount | integer | Default: 0 |
| auto_discount | float | Default: 0 |
| price | integer | Default: 0 — calculated total |
| transaction_date | datetime | Required |
| shipment_estimation_datetime | datetime | Required |
| description | text | Default: return-policy disclaimer |
| timestamps | | |
| soft_deletes | | |

**Traits:** `Tenanted`, `FilterStartEndDate`

**Computed Attributes:**
- `payment_amount` — sum of related Payment.amount values
- `payment_status` — `none` / `paid` / `down_payment`
- `auto_discount_nominal` — calculated from auto_discount percentage
- `voucher_*` — voucher fields from relationship

**Auto-Numbering:**
- Format: `PAS/SO/MM/YY/NN` (e.g., `PAS/SO/05/26/01`)
- Uses `settings` table with `lockForUpdate` for race condition prevention
- Resets monthly

#### 3.5.2 Sales Order Details

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| sales_order_id | FK → sales_orders | ON DELETE CASCADE |
| product_unit_id | FK → product_units | Required |
| warehouse_id | FK → warehouses | Nullable |
| qty | integer | Default: 0 |
| fulfilled_qty | integer | Default: 0 — shipped quantity |
| unit_price | integer | Default: 0 |
| discount | integer | Default: 0 |
| tax | integer | Default: 0 — 11% PPN when is_ppn=true |
| total_price | integer | Default: 0 |
| timestamps | | |

**Computed:**
- `scheduled_qty` — total qty across all delivery order details
- `remaining_qty` — `qty - fulfilled_qty`

#### 3.5.3 Sales Order Items

Links specific stock serials to SO details. Created when `is_invoice = true`.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| is_returned | boolean | Default: false |
| parent_id | FK → sales_order_items | Nullable, for return items |
| is_parent | boolean | Default: false |
| stock_id | ULID FK → stocks | Nullable |
| sales_order_detail_id | FK → sales_order_details | ON DELETE CASCADE |
| delivery_order_detail_id | FK → delivery_order_details | Nullable, ON DELETE CASCADE |
| timestamps | | |

#### 3.5.4 Order Processing Pipeline

```
SalesOrderService::createOrder($data)
  → Pipeline with 9 steps:

Step 1: FillOrderAttributes
  Maps raw_source fields to SalesOrder model
  Fields: company, reseller_id, spg_id, warehouse_id, invoice_no,
          transaction_date, shipment_estimation_datetime, shipment_fee,
          additional_discount, description, type

Step 2: FillOrderRecords
  Snapshots reseller User data into records JSON column

Step 3: MakeOrderDetails
  Builds SalesOrderDetail collection from items array
  Resolves ProductUnits (with soft-deletes)
  Calculates: qty, unit_price, discount, tax (11% PPN if is_ppn), total_price
  Computes initial price = sum of detail total_prices

Step 4: CalculateAutoDiscount
  If order price exceeds configured threshold (config('app.min_trx_auto_discount'))
  → applies percentage discount
  → price = max(price - discountNominal, 0)

Step 5: CalculateVoucher
  Looks up voucher by code
  NOMINAL: fixed amount deducted
  PERCENTAGE: percentage of current price deducted
  → price = max(price - discountNominal, 0)

Step 6: CalculateAdditionalDiscount
  If raw_source.is_additional_discount_percentage = true (default):
    discount = price × (additional_discount / 100)
  Else:
    discount = additional_discount (nominal)
  → price = max(price - discount, 0)

Step 7: CalculateAdditionalFees
  price += shipment_fee

Step 8: CheckExpectedOrderPrice
  If expected_price is provided, validates price == expected_price
  Throws "Harga tidak cocok" on mismatch

Step 9: SaveOrder
  Persists order + details in DB transaction
  If customer_name provided → creates customer reseller
  If is_invoice = true → reserves Stock items via SalesOrderItem
  Auto-generates invoice_no
```

#### 3.5.5 Order Types

| Type | Value | Description |
|------|-------|-------------|
| Default | `default` | Standard order |
| Delivery | `delivery` | Delivery order |
| Pickup | `pickup` | Customer pickup |
| Free | `free` | Free/discounted order |

---

### 3.6 Delivery Order

#### 3.6.1 Delivery Orders

Manages partial shipments from a Sales Order.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| user_id | FK → users | Creator |
| warehouse_id | FK → warehouses | Required |
| reseller_id | FK → users | The reseller |
| invoice_no | string(50) | Auto-generated: `PAS/DO/MM/YY/NN` |
| transaction_date | datetime | Required |
| shipment_estimation_datetime | datetime | Required |
| description | text | Default: return-policy disclaimer |
| is_done | boolean | Default: false |
| done_at | timestamp | Nullable |
| timestamps | | |
| soft_deletes | | |

**Traits:** `Tenanted`, `FilterStartEndDate`

**Auto-Numbering:**
- Format: `PAS/DO/MM/YY/NN` (e.g., `PAS/DO/05/26/01`)
- Same `lockForUpdate` pattern as SalesOrder

#### 3.6.2 Delivery Order Details

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| delivery_order_id | FK → delivery_orders | ON DELETE CASCADE |
| sales_order_detail_id | FK → sales_order_details | Required |
| qty | unsigned int | Default: 0 — quantity to deliver |
| timestamps | | |

#### 3.6.3 Delivery Flow

```
1. Admin creates DeliveryOrder linked to SalesOrder
2. Admin adds DeliveryOrderDetails (which SO detail + qty)
3. Admin verifies: attaches specific Stock items to SalesOrderItem
   via POST /delivery-orders/{do}/verification/{doDetail}
4. SalesOrderDetail.fulfilled_qty updated
5. One SO can have multiple DOs (partial shipments):
   Example: SO has qty=100 for Product A
   → DO_A ships 50 units
   → DO_B ships 50 units
6. Admin marks DO as done (is_done = true)
```

**Return Flow:**
- POST `/delivery-orders/{do}/return` — returns delivered items back to stock

---

### 3.7 Invoice & Payment

#### 3.7.1 Invoice Generation

- When `SalesOrder.is_invoice = true`, stock items are reserved via `SalesOrderItem`
- Invoice number auto-generated on SO creation
- PDF generation via `barryvdh/laravel-dompdf` (landscape A4)
- Number-to-words conversion for total price

**Invoice Endpoints:**
| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/invoices` | List invoices (SalesOrders with is_invoice=true) |
| GET | `/invoices/{salesOrder}` | Show invoice |
| GET | `/invoices/{salesOrder}/bill` | Render invoice HTML/PDF |
| GET | `/invoices/{salesOrder}/export-xml` | Export as XML |
| GET | `/invoices/export` | Export all invoices |

#### 3.7.2 Payments

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| sales_order_id | FK → sales_orders | Required |
| user_id | FK → users | Creator |
| amount | unsigned float(11,2) | Default: 0 |
| type | string(20) | Default: `PaymentType::CASH` |
| note | text | Nullable |
| deleted_by | unsigned int | Nullable (CustomSoftDeletes) |
| timestamps | | |
| soft_deletes | | |

**Payment Types:**
| Type | Value |
|------|-------|
| Cash | `cash` |
| Transfer | `transfer` |
| Credit Card | `credit_card` |
| QRIS | `qris` |

**Payment Status (computed on SalesOrder):**
- `none` — no payments made
- `paid` — total payments >= order price
- `down_payment` — partial payment

**File Attachments:**
- Uses Spatie Media Library (collection: `'payments'`)
- Stored on S3 with temporary URLs

#### 3.7.3 Price Calculation Summary

```
1. base_price = Σ(unit_price × qty) for all details
2. auto_discount = percentage if threshold exceeded
3. after_auto = max(base_price - auto_discount, 0)
4. voucher_discount = NOMINAL (fixed) or PERCENTAGE
5. after_voucher = max(after_auto - voucher_discount, 0)
6. additional_discount = percentage or nominal
7. after_additional = max(after_voucher - additional_discount, 0)
8. final_price = after_additional + shipment_fee
```

**Tax (PPN):** 11% per-item when `product_unit.is_ppn = true`

---

### 3.8 Stock Adjustment

#### 3.8.1 Adjustment Requests

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| user_id | FK → users | Creator |
| stock_product_unit_id | FK → stock_product_units | Required |
| is_increment | boolean | Default: true |
| value | unsigned int | Default: 0 — quantity to adjust |
| description | text | Nullable |
| is_approved | boolean | Nullable (null=pending, true=approved, false=rejected) |
| approved_by | integer | Nullable |
| approved_datetime | timestamp | Nullable |
| reason | text | Nullable |
| timestamps | | |
| soft_deletes | | |

#### 3.8.2 Adjustment Flow

```
1. Admin creates AdjustmentRequest
   - is_increment: true (add stock) or false (remove stock)
   - value: quantity
   - stock_product_unit_id: which product in which warehouse
2. Admin approves (PUT /adjustment-requests/{id}/approve)
   - If is_increment: creates Stock records
   - If !is_increment: deletes Stock records
3. StockHistory record created
```

---

### 3.9 Stock Opname (Physical Count)

#### 3.9.1 Stock Opnames

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| user_id | FK → users | Creator |
| warehouse_id | FK → warehouses | Required |
| description | text | Nullable |
| is_done | boolean | Default: false |
| done_at | timestamp | Nullable |
| timestamps | | |

**Traits:** `Tenanted`

#### 3.9.2 Stock Opname Details

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| stock_opname_id | FK → stock_opnames | ON DELETE CASCADE |
| stock_product_unit_id | FK → stock_product_units | Required |
| qty | integer | Default: 0 — system quantity |
| scanned_qty | integer | Default: 0 |
| description | text | Nullable |
| is_done | boolean | Default: false |
| done_at | timestamp | Nullable |
| timestamps | | |

#### 3.9.3 Stock Opname Items

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| stock_opname_detail_id | FK → stock_opname_details | Required |
| stock_id | ULID FK → stocks | Required |
| is_scanned | boolean | Default: false |
| is_new | boolean | Default: false |
| timestamps | | |

#### 3.9.4 Stock Opname Flow

```
1. Admin creates StockOpname for a warehouse
2. boot('created') fires StockOpnameCreated
3. CreateStockOpnameDetail (queued) creates detail for every StockProductUnit
4. CreateStockOpnameItems (queued) creates items for every available Stock
5. Staff scans QR codes → is_scanned = true
6. Parent→child propagation: scanning parent scans all children
7. When done (is_done = true):
   → Un-scanned Stock records get SOFT-DELETED
   → StockHistory records created (decrement)
8. Can be reverted (is_done = false):
   → Soft-deleted Stock records get RESTORED
   → StockHistory records created (increment)
```

---

### 3.10 Voucher System

#### 3.10.1 Voucher Categories

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| name | string(255) | Required |
| discount_type | enum | `nominal` or `percentage` |
| discount_amount | float(11,2) | Default: 0 |
| description | string(255) | Nullable |
| deleted_by | unsigned int | Nullable (CustomSoftDeletes) |
| timestamps | | |
| soft_deletes | | |

#### 3.10.2 Voucher Generate Batches

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| user_id | FK → users | Creator |
| source | string(10) | Default: `BatchSource::UPLOAD` (`upload` or `import`) |
| description | string(255) | Nullable |
| timestamps | | |

#### 3.10.3 Vouchers

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| voucher_generate_batch_id | FK → voucher_generate_batches | ON DELETE CASCADE |
| voucher_category_id | FK → voucher_categories | ON DELETE CASCADE |
| code | string(255) | UNIQUE |
| description | string(255) | Nullable |
| deleted_by | unsigned int | Nullable (CustomSoftDeletes) |
| timestamps | | |
| soft_deletes | | |

**Business Rules:**
- One voucher can only be used once (hasOne SalesOrder)
- Generated in batches via upload or import

---

### 3.11 Loyalty Program

> **Full specification:** See `LOYALTY_SPEC.md` for detailed user stories, state machines, and phasing.

#### 3.11.1 Summary

A points-and-rewards loyalty program for end customers. Customers upload receipts + product photos after purchase. Admin reviews each submission and awards points. Customers spend points on physical prizes.

#### 3.11.2 Loyalty Users

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| email | string(255) | UNIQUE |
| name | string(255) | Required |
| password | string(255) | Required, bcrypt |
| email_verified_at | timestamp | Nullable |
| phone | string(255) | Nullable |
| address | text | Nullable |
| is_active | boolean | Default: true, indexed |
| timestamps | | |

**Note:** No soft deletes — account closure is hard delete.

#### 3.11.3 Claims

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| loyalty_user_id | ULID FK → loyalty_users | RESTRICT ON DELETE |
| invoice_number | string(100) | Required |
| invoice_photo_path | string(255) | Required |
| status | enum | `pending`, `approved`, `rejected` |
| submitted_at | timestamp | Required |
| reviewed_at | timestamp | Nullable |
| reviewed_by | FK → users | Nullable, RESTRICT ON DELETE |
| rejection_reason | text | Nullable |
| total_points | integer | Default: 0 |
| timestamps | | |

**Indexes:** UNIQUE on (loyalty_user_id, invoice_number)

**State Machine:**
```
pending ──→ approved (terminal)
  └──→ rejected (terminal)
```

#### 3.11.4 Claim Line Items

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| claim_id | ULID FK → claims | ON DELETE CASCADE |
| product_unit_id | FK → product_units | RESTRICT ON DELETE |
| quantity | integer | Required |
| points_awarded | integer | Default: 0 |
| timestamps | | |

#### 3.11.5 Claim Photos

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| claim_id | ULID FK → claims | ON DELETE CASCADE |
| photo_path | string(255) | Required |
| position | integer | Required |
| created_at | timestamp | Immutable |

#### 3.11.6 Points Transactions

Append-only ledger. Balance is ALWAYS computed, never stored.

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| loyalty_user_id | ULID FK → loyalty_users | RESTRICT ON DELETE |
| direction | enum | `earn` or `spend` |
| amount | integer | Required |
| source_type | string(255) | App-level polymorphic: `claim`, `redemption`, `manual_adjustment` |
| source_id | ULID | App-level polymorphic |
| description | string(255) | Required |
| adjusted_by | FK → users | Nullable, for manual adjustments |
| reason | text | Nullable, for manual adjustments |
| created_at | timestamp | Required |

**Note:** No `updated_at` — append-only ledger.

**Constants:**
- `DIRECTION_EARN = 'earn'`
- `DIRECTION_SPEND = 'spend'`
- `SOURCE_CLAIM = 'claim'`
- `SOURCE_REDEMPTION = 'redemption'`
- `SOURCE_MANUAL_ADJUSTMENT = 'manual_adjustment'`

#### 3.11.7 Prizes

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| prize_category_id | FK → prize_categories | Nullable, NULL ON DELETE |
| name | string(255) | Required, indexed |
| description | text | Nullable |
| photo_path | string(255) | Nullable |
| product_url | string(2048) | Nullable |
| points_cost | integer | Required, indexed |
| stock | integer | Default: 0 |
| is_active | boolean | Default: true, indexed |
| timestamps | | |

**Note:** No soft deletes — admins use `is_active` to hide prizes.

#### 3.11.8 Prize Categories

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | Auto-increment |
| name | string(255) | Required |
| is_active | boolean | Default: true |
| timestamps | | |
| soft_deletes | | |

#### 3.11.9 Redemptions

| Field | Type | Notes |
|-------|------|-------|
| id | **ULID** | PRIMARY KEY |
| loyalty_user_id | ULID FK → loyalty_users | RESTRICT ON DELETE |
| prize_id | ULID FK → prizes | RESTRICT ON DELETE |
| points_spent | integer | Required |
| quantity | integer | Default: 1 |
| status | string(255) | Default: `pending` |
| recipient_name | string(255) | Required |
| recipient_phone | string(255) | Required |
| recipient_address | text | Required |
| recipient_notes | text | Nullable |
| rejection_reason | text | Nullable |
| tracking_number | string(255) | Nullable |
| shipping_carrier | string(255) | Nullable |
| shipped_at | timestamp | Nullable |
| delivered_at | timestamp | Nullable |
| submitted_at | timestamp | Nullable |
| reviewed_at | timestamp | Nullable |
| reviewed_by | FK → users | Nullable, RESTRICT ON DELETE |
| timestamps | | |

**State Machine:**
```
pending ──→ approved ──→ shipped ──→ delivered (terminal)
  └──→ rejected (terminal)
```

#### 3.11.10 Loyalty Flow

```
1. Customer signs up via POST /loyalty/auth/register
2. Email verification (signed URL, 24h expiry)
3. Customer submits claim (invoice photo + product photos + invoice number)
4. Rate limited: 5 claims/day per user
5. Admin reviews claim, adds line items (product_unit × quantity)
6. Points calculated: Σ(quantity × product_units.points_per_unit)
7. Approve → PointsTransaction(earn) created, Claim status = approved
8. Customer accumulates points
9. Customer browses Prize catalog
10. Submits redemption (recipient_name, phone, address)
11. Points deducted → PointsTransaction(spend) created
12. Admin approves → ships → customer confirms delivery
```

---

## 4. Data Model Relationships

### 4.1 Core Entity Relationship Diagram

```
┌─────────────────┐
│product_categories│◀──┐
└─────────────────┘   │
                      │    ┌───────────────┐
┌─────────────────┐   ├───▶│   products    │
│ product_brands  │◀──┤    └───────┬───────┘
└─────────────────┘   │            │
                      │            ▼
                      │    ┌───────────────┐    ┌─────────┐
                      │    │ product_units  │◀───│  uoms   │
                      │    └───────┬───────┘    └─────────┘
                      │            │
                      │            ├──────────────────────┐
                      │            ▼                      ▼
                      │    ┌──────────────────┐  ┌─────────────────────┐
                      │    │stock_product_units│  │product_unit_relations│
                      │    └────────┬─────────┘  └─────────────────────┘
                      │             │
                      │             ├──────────────┐
                      │             ▼              ▼
                      │    ┌──────────────┐  ┌───────────────┐
                      │    │    stocks    │  │stock_histories │
                      │    │  (ULID PK)  │  └───────────────┘
                      │    └──────┬───────┘
                      │           │
                      │           │    ┌──────────────────┐
                      │           ├───▶│sales_order_items  │
                      │           │    └────────┬─────────┘
                      │           │             │
                      │           │             ▼
                      │           │    ┌───────────────────┐
                      │           │    │sales_order_details │
                      │           │    └────────┬──────────┘
                      │           │             │
                      │           │             ▼
                      │           │    ┌───────────────────┐
                      │           │    │  sales_orders     │
                      │           │    └────────┬──────────┘
                      │           │             │
                      │           │             ├──▶ payments
                      │           │             └──▶ vouchers
                      │           │
                      │           │    ┌───────────────────┐
                      │           ├───▶│stock_opname_items  │
                      │           │    └────────┬──────────┘
                      │           │             │
                      │           │             ▼
                      │           │    ┌───────────────────┐
                      │           │    │stock_opname_details│
                      │           │    └──────────────────┘
                      │           │
┌──────────┐         │           │
│warehouses│◀────────┘           │
└─────┬────┘                     │
      │                          │
      │    ┌──────────────┐      │
      ├───▶│receive_orders│      │
      │    └──────┬───────┘      │
      │           │              │
      │           ▼              │
      │    ┌────────────────────┐│
      │    │receive_order_details│
      │    └────────────────────┘│
      │                          │
      │    ┌──────────────────┐  │
      ├───▶│ delivery_orders  │  │
      │    └────────┬─────────┘  │
      │             │            │
      │             ▼            │
      │    ┌────────────────────┐│
      │    │delivery_order_details│
      │    └────────────────────┘│
      │                          │
      │    ┌──────────────┐      │
      └───▶│stock_opnames │      │
           └──────────────┘      │
                                 │
┌──────────┐                     │
│  users   │◀────────────────────┘
└──────────┘
```

### 4.2 Loyalty System Relationships

```
┌───────────────┐
│ loyalty_users  │
│  (ULID PK)    │
└───────┬───────┘
        │
        ├──▶ claims (ULID PK)
        │       ├──▶ claim_photos (ULID PK)
        │       └──▶ claim_line_items (ULID PK)
        │
        ├──▶ redemptions (ULID PK)
        │       └──▶ prize_id → prizes
        │
        └──▶ points_transactions (ULID PK)
                └──▶ source_type/source_id (app-level polymorphic)

┌───────────────┐     ┌───────────────────┐
│prize_categories│◀────│     prizes        │
└───────────────┘     │    (ULID PK)      │
                      └───────────────────┘
```

---

## 5. Business Rules

### 5.1 Auto-Numbering

| Document | Format | Example | Mechanism |
|----------|--------|---------|-----------|
| Sales Order | `PAS/SO/MM/YY/NN` | `PAS/SO/05/26/01` | `settings` table + `lockForUpdate` |
| Delivery Order | `PAS/DO/MM/YY/NN` | `PAS/DO/05/26/01` | `settings` table + `lockForUpdate` |

- `NN` is zero-padded sequential (01, 02, ... 99)
- Counter resets monthly
- `DB::transaction` + `lockForUpdate` prevents race conditions

### 5.2 Stock Tracking Rules

| Condition | Tracking Method |
|-----------|----------------|
| `is_generate_qr = true` | Individual `Stock` rows (ULID PK). Count by querying available stocks. |
| `is_generate_qr = false` | `StockProductUnit.qty` integer. Increment/decrement directly. |

**Available Stock Definition:**
A Stock record is "available" if it has no non-returned `SalesOrderItem` linked to it.

### 5.3 Tenancy Rules

| Rule | Detail |
|------|--------|
| Admin bypass | `hasRole('admin')` sees all data |
| Warehouse scoping | Non-admin users see only data from warehouses in `user_warehouses` pivot |
| SPG override | SPG users see orders where `spg_id = user_id` (not warehouse-based) |
| Hardcoded IDs | NEVER hardcode warehouse IDs in queries |

### 5.4 Price Calculation Rules

1. **Base price** = Σ(unit_price × qty)
2. **Auto discount** = percentage if threshold exceeded → `max(price - discount, 0)`
3. **Voucher** = NOMINAL (fixed) or PERCENTAGE → `max(price - discount, 0)`
4. **Additional discount** = percentage or nominal → `max(price - discount, 0)`
5. **Shipment fee** = added last → `price += shipment_fee`
6. **Tax** = 11% PPN per-item when `is_ppn = true`
7. **Price floor** = all discounts use `max(..., 0)` to prevent negatives
8. **Total validation** = BE recalculates and compares to FE-submitted total

### 5.5 Default Values

| Field | Default | Context |
|-------|---------|---------|
| description | `"#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih"` | All orders (SO, DO, Order) |
| type | `SalesOrderType::DEFAULT` | SalesOrder, Order |
| type | `SalesOrderType::PICKUP` | Order (on saving) |

### 5.6 Reseller Auto-Discount

When a User of type `Reseller` is created:
- `UserDiscount` records are automatically created for every existing `ProductBrand`
- Default value: 0, is_percentage: true

### 5.7 Payment Status Logic

```php
$paymentAmount = $this->payments->sum('amount');
if ($paymentAmount == 0) return 'none';
if ($paymentAmount >= $this->price) return 'paid';
return 'down_payment';
```

---

## 6. API Architecture

### 6.1 Route Groups & Middleware

| Group | Middleware | Prefix | Auth |
|-------|-----------|--------|------|
| Public | `throttle:api` | `/api` | None |
| Loyalty Auth | `throttle:api` | `/api/loyalty` | None |
| Loyalty Customer | `auth:loyalty`, `loyalty.active` | `/api/loyalty` | Bearer token (loyalty guard) |
| Loyalty Admin | `auth:sanctum` | `/api/admin/loyalty` | Bearer token (sanctum, loyalty ability) |
| Warehouse | `auth:sanctum`, `ability:warehouse` | `/api` | Bearer token (sanctum, * ability) |

### 6.2 Rate Limiting

| Limiter | Limit | Applied To |
|---------|-------|------------|
| `api` | 200 requests/minute | All API routes |
| `loyalty-claims` | 5 requests/day | `POST /loyalty/claims` |

### 6.3 Key Endpoints

#### Public
| Method | URI | Description |
|--------|-----|-------------|
| POST | `/auth/token` | Login (throttled) |
| POST | `/auth/register` | Staff registration |
| GET | `/public/stocks/{ulid}` | QR code verification (throttled) |

#### Warehouse Management
| Method | URI | Description |
|--------|-----|-------------|
| CRUD | `/warehouses` | Warehouse management |
| CRUD | `/products` | Product management |
| CRUD | `/product-units` | Product unit management |
| POST | `/product-units/import` | Import products |
| CRUD | `/receive-orders` | Receive order management |
| PUT | `/receive-orders/{id}/done` | Mark RO as done |
| CRUD | `/sales-orders` | Sales order management |
| CRUD | `/delivery-orders` | Delivery order management |
| POST | `/delivery-orders/{id}/verification/{detailId}` | Verify DO detail |
| CRUD | `/stocks` | Stock management |
| POST | `/stocks/grouping` | Group stocks |
| POST | `/stocks/record` | Record stock movement |
| CRUD | `/stock-opnames` | Stock opname management |
| PUT | `/stock-opnames/{id}/done` | Mark opname as done |
| CRUD | `/adjustment-requests` | Adjustment requests |
| PUT | `/adjustment-requests/{id}/approve` | Approve adjustment |
| CRUD | `/invoices` | Invoice management |
| GET | `/invoices/{id}/bill` | Generate invoice PDF |
| CRUD | `/payments` | Payment management |
| CRUD | `/roles` | Role management |
| CRUD | `/permissions` | Permission management |
| CRUD | `/users` | User management |

#### Loyalty Customer
| Method | URI | Description |
|--------|-----|-------------|
| POST | `/loyalty/auth/register` | Customer signup |
| POST | `/loyalty/auth/login` | Customer login |
| POST | `/loyalty/claims` | Submit claim (throttled 5/day) |
| GET | `/loyalty/claims` | List claims |
| GET | `/loyalty/points/balance` | View points balance |
| GET | `/loyalty/prizes` | Browse prize catalog |
| POST | `/loyalty/redemptions` | Redeem a prize |
| POST | `/loyalty/redemptions/{id}/deliver` | Confirm delivery |

#### Loyalty Admin
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/admin/loyalty/claims` | Claim review queue |
| POST | `/admin/loyalty/claims/{id}/approve` | Approve claim |
| POST | `/admin/loyalty/claims/{id}/reject` | Reject claim |
| CRUD | `/admin/loyalty/prizes` | Prize management |
| CRUD | `/admin/loyalty/prize-categories` | Prize category management |
| GET | `/admin/loyalty/redemptions` | Redemption queue |
| POST | `/admin/loyalty/redemptions/{id}/ship` | Ship redemption |
| PATCH | `/admin/loyalty/points/{productUnit}` | Update loyalty points per unit |

---

## 7. External Integrations

### 7.1 AWS S3

- File storage via `league/flysystem-aws-s3-v3`
- Used by: Payment media, Brand logos, Loyalty claim photos, Prize photos
- Config: `FILESYSTEM_DISK=s3`

### 7.2 Brevo (Email Service)

- Email delivery via `symfony/brevo-mailer`
- Used by: Loyalty verification emails, notifications
- 7 Mailable classes with Indonesian blade views

### 7.3 OAuth/Socialite

- Google OAuth login for warehouse staff
- `laravel/socialite` integration
- `SocialAccount` model stores provider accounts

### 7.4 Frontend Applications

| App | Domain | Purpose |
|-----|--------|---------|
| BEJO CMS | `bejo.platinumadisentosa.com` | Admin dashboard |
| Customer FE | `platinum-warehouse.vercel.app` | Customer-facing |
| Beta FE | `platinum-warehouse-beta.vercel.app` | Testing/staging |
| QR Verify | `bejo-platinum-product-verify.vercel.app` | Public QR code verification |

---

## 8. Known Issues & Technical Debt

### 8.1 Missing Migration

- `stocks.expired_date` column exists in production but has no corresponding migration in `database/migrations/`
- Application code assumes it exists; `migrate:fresh` on clean DB will throw `Column not found`
- See `BACKEND_AUDIT.md` Section 2.3

### 8.2 Dual Order Systems

Two order systems coexist:
1. **Original:** `sales_orders` → `sales_order_details` → `sales_order_items`
2. **New:** `orders` → `order_details` (introduced May 2024)

The newer `orders` system is used for SPG pickup orders and is converted to `sales_orders` via the `ConvertToSO` pipe.

### 8.3 Security Findings

See `SECURITY_FINDINGS.md` for detailed findings:
- Permission bypasses on ~30 endpoints
- Middleware name mismatches
- Plaintext token storage (`plain_text_token` in `personal_access_tokens`)
- Unscoped OAuth tokens

### 8.4 QR Code PNG Generation

- QR code PNG generation to S3 is currently **commented out** in the codebase
- QR images appear to be rendered client-side using the ULID as payload
- `qr_code` column in `stocks` table is unused

---

## 9. References

| Document | Description | Lines |
|----------|-------------|-------|
| `BACKEND_AUDIT.md` | Comprehensive technical audit — tech stack, database schema, API surface, QR/serial format, operational status | 782 |
| `LOYALTY_SPEC.md` | Loyalty program specification — user stories, data model, state machines, API endpoints, fraud rules, phasing | 597 |
| `SECURITY_FINDINGS.md` | Security findings — permission bypasses, middleware mismatches, plaintext tokens, unscoped OAuth | 111 |
| `AGENTS.md` | AI agent guide — architecture, conventions, file locations, do's/don'ts | — |
