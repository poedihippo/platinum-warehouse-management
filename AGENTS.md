# AGENTS.md — AI Agent Guide for BEJO Warehouse Management

> **Purpose:** This file instructs AI agents (OpenCode) on how to understand, navigate, and contribute to this codebase. Read this file before making any changes.

---

## 1. Project Identity

| Field | Value |
|-------|-------|
| **App Name** | BEJO |
| **Company** | Platinum Adi Sentosa |
| **Industry** | Feed/pakan distributor |
| **Type** | Warehouse Management System (WMS) |
| **Frontend** | Separate repos (Vercel deployments) |
| **Backend** | This repo — Laravel 10 REST API |

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 10 (`laravel/framework: ^10.0`) |
| PHP | `^8.0.2` |
| Database | MySQL |
| Cache | File-based (`CACHE_DRIVER=file`) |
| Session | File-based (`SESSION_DRIVER=file`) |
| Queue | Redis (`QUEUE_CONNECTION=redis`) |
| Auth | Laravel Sanctum 3.2 (Bearer personal access tokens) |
| RBAC | `spatie/laravel-permission` 5.10 |
| Query Builder | `spatie/laravel-query-builder` 5.2 |
| Media Library | `spatie/laravel-medialibrary` 11 |
| Excel/CSV | `maatwebsite/excel` 3.1 |
| PDF | `barryvdh/laravel-dompdf` 2.0 |
| QR Code | `simplesoftwareio/simple-qrcode` ~4 |
| Enums | `bensampo/laravel-enum` 6.3 |
| OAuth | `laravel/socialite` 5.6 |
| File Storage | AWS S3 (`league/flysystem-aws-s3-v3`) |
| Number to Words | `kwn/number-to-words` 2.7 |
| Email | Brevo (`symfony/brevo-mailer`) |

---

## 3. Architecture Overview

### 3.1 High-Level Pattern

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│   Routes     │────▶│  Controllers │────▶│   Services   │
│  (api.php)   │     │              │     │ (Pipeline)   │
└─────────────┘     └──────────────┘     └──────────────┘
                           │                     │
                           ▼                     ▼
                    ┌──────────────┐     ┌──────────────┐
                    │   Models     │     │   Pipes      │
                    │ (Eloquent)   │     │ (Order flow) │
                    └──────────────┘     └──────────────┘
                           │
                           ▼
                    ┌──────────────┐     ┌──────────────┐
                    │  Events &    │     │   Traits     │
                    │  Listeners   │     │ (Tenanted,   │
                    │              │     │  SoftDeletes)│
                    └──────────────┘     └──────────────┘
```

### 3.2 Key Architectural Patterns

| Pattern | Location | Purpose |
|---------|----------|---------|
| **Pipeline** | `app/Pipes/Order/` | Sales order processing (9 sequential steps) |
| **Event-Driven** | `app/Events/`, `app/Listeners/` | Stock creation, opname, RO verification |
| **Tenancy** | `app/Traits/Tenanted.php` | Warehouse-scoped data access |
| **Dual Auth** | `config/auth.php` | `users` (staff) + `loyalty_users` (customers) |
| **Custom Soft Deletes** | `app/Traits/CustomSoftDeletes.php` | Tracks `deleted_by` user |
| **Auto-Numbering** | Model `boot()` methods | SO/DO sequential numbers with `lockForUpdate` |

---

## 4. Database Schema Reference

### 4.1 Table Count: 55 tables across 17 groups

| Group | Tables | Key Tables |
|-------|--------|------------|
| Auth & RBAC | 9 | `users`, `roles`, `permissions`, `personal_access_tokens` |
| Tenant & Supply Chain | 4 | `warehouses`, `suppliers`, `user_warehouses`, `tenants` |
| Product Management | 7 | `products`, `product_units`, `product_brands`, `product_categories`, `uoms` |
| Stock/Inventory | 6 | `stocks` (ULID), `stock_product_units`, `stock_histories`, `temporary_stocks` |
| Receive Orders | 2 | `receive_orders`, `receive_order_details` |
| Sales Orders | 3 | `sales_orders`, `sales_order_details`, `sales_order_items` |
| Delivery Orders | 2 | `delivery_orders`, `delivery_order_details` |
| Orders (New) | 2 | `orders`, `order_details` |
| Stock Opname | 3 | `stock_opnames`, `stock_opname_details`, `stock_opname_items` |
| Adjustments | 1 | `adjustment_requests` |
| Payments | 1 | `payments` |
| Vouchers | 3 | `vouchers`, `voucher_categories`, `voucher_generate_batches` |
| User Discounts | 1 | `user_discounts` |
| Settings | 1 | `settings` |
| Media | 1 | `media` (Spatie) |
| Queue | 1 | `jobs` |
| Loyalty | 8 | `loyalty_users`, `claims`, `points_transactions`, `prizes`, `redemptions` |

### 4.2 Core Entity Relationships

```
ProductCategory ──┐
                   ├──▶ Product ──▶ ProductUnit ──┬──▶ StockProductUnit ──▶ Stock (ULID)
ProductBrand ─────┘                               │         │                    │
                                                  │         │                    ├──▶ parent_id (grouping)
                                                  │         │                    ├──▶ SalesOrderItem
                                                  │         │                    └──▶ StockOpnameItem
                                                  │         │
                                                  │         └──▶ ProductUnitRelation (self-ref)
                                                  │
                                                  └──▶ UOM

Warehouse ──▶ StockProductUnit (junction: ProductUnit × Warehouse)
           ──▶ SalesOrder
           ──▶ DeliveryOrder
           ──▶ ReceiveOrder
           ──▶ StockOpname

User ──▶ SalesOrder (creator, reseller, spg)
      ──▶ DeliveryOrder
      ──▶ ReceiveOrder
      ──▶ user_warehouses (pivot)

ReceiveOrder ──▶ ReceiveOrderDetail ──▶ verified → creates Stock rows

SalesOrder ──▶ SalesOrderDetail ──▶ SalesOrderItem
                                   ──▶ DeliveryOrderDetail

DeliveryOrder ──▶ DeliveryOrderDetail ──▶ SalesOrderItem (stock assignment)
```

### 4.3 Key ID Strategies

| Table | PK Type | Reason |
|-------|---------|--------|
| `stocks` | ULID (26-char) | Used as QR code payload / serial number |
| `loyalty_users` | ULID | Separate auth system |
| `claims`, `redemptions`, `prizes`, `points_transactions` | ULID | Loyalty system consistency |
| All others | bigint auto-increment | Standard Laravel |

---

## 5. Core Business Flows

### 5.1 Product Creation → Stock Slot Auto-Creation

```
1. Admin creates ProductUnit (refer_id = null)
2. boot('created') dispatches ProductUnitCreated event
3. CreateStockProductUnit listener runs (queued)
4. For EVERY warehouse (including soft-deleted):
   → Creates StockProductUnit record (qty = 0)
5. Result: New product unit has a stock slot in every warehouse
```

### 5.2 Warehouse Creation → Stock Slot Auto-Creation

```
1. Admin creates Warehouse
2. boot('created') runs
3. For EVERY ProductUnit (including soft-deleted):
   → Creates StockProductUnit record (qty = 0)
4. Result: New warehouse has a stock slot for every product unit
```

### 5.3 Receive Order (Inbound Stock)

```
1. Admin creates ReceiveOrder → ReceiveOrderDetail(s)
2. Admin verifies each detail (is_verified = 1)
3. Admin marks RO as done (is_done = true)
4. boot('updated') fires VerifiedROEvent
5. CreateStockROListener (queued) processes:
   ├─ QR products (is_generate_qr = true):
   │   → Creates N individual Stock records (ULID PKs)
   │   → Each Stock linked to StockProductUnit
   └─ Non-QR products:
       → Increments StockProductUnit.qty
6. StockHistory record created (increment)
```

**Unverify/Delete flow:**
```
1. is_done changed to false → UnverifiedROEvent
2. DeleteStockROListener (queued):
   → Force-deletes Stock records
   → Decrements StockProductUnit.qty (non-QR)
   → Deletes QR code files from S3
   → Creates StockHistory (decrement)
```

### 5.4 Sales Order Processing

```
1. Request hits SalesOrderController@store
2. SalesOrderService::createOrder() runs Pipeline:
   ┌─ Step 1: FillOrderAttributes
   │    Maps raw_source fields to SalesOrder model
   ├─ Step 2: FillOrderRecords
   │    Snapshots reseller data into records JSON
   ├─ Step 3: MakeOrderDetails
   │    Builds SalesOrderDetail collection from items
   │    Resolves ProductUnits, calculates prices
   │    Checks available stock per item
   ├─ Step 4: CalculateAutoDiscount
   │    Applies threshold-based percentage discount
   ├─ Step 5: CalculateVoucher
   │    Looks up voucher, applies NOMINAL or PERCENTAGE
   ├─ Step 6: CalculateAdditionalDiscount
   │    Applies additional discount (% or nominal)
   ├─ Step 7: CalculateAdditionalFees
   │    Adds shipment_fee to price
   ├─ Step 8: CheckExpectedOrderPrice
   │    Validates price matches expected (if provided)
   └─ Step 9: SaveOrder
       Persists order + details in transaction
       If is_invoice=true: reserves Stock items via SalesOrderItem
       Auto-generates invoice_no: PAS/SO/MM/YY/NN
```

### 5.5 Delivery Order (Partial Shipment)

```
1. Admin creates DeliveryOrder linked to SalesOrder
2. DeliveryOrderDetail specifies which SalesOrderDetail + qty
3. Admin verifies: attaches specific Stock items to SalesOrderItem
4. SalesOrderDetail.fulfilled_qty tracks shipped quantity
5. One SO can have multiple DOs (partial shipments)
6. Auto-generates invoice_no: PAS/DO/MM/YY/NN
```

### 5.6 Stock Opname (Physical Count)

```
1. Admin creates StockOpname for a warehouse
2. boot('created') fires StockOpnameCreated
3. Creates StockOpnameDetail for every StockProductUnit in warehouse
4. Each detail auto-creates StockOpnameItem for every available Stock
5. Staff scans QR codes → is_scanned = true
6. Parent→child propagation: scanning parent scans all children
7. When done (is_done = true):
   → Un-scanned Stock records get SOFT-DELETED
   → StockHistory records created (decrement)
8. Can be reverted (is_done = false):
   → Soft-deleted Stock records get RESTORED
   → StockHistory records created (increment)
```

### 5.7 Stock Adjustment

```
1. Admin creates AdjustmentRequest (is_increment, value, stock_product_unit_id)
2. Admin approves → creates Stock records (increment) or deletes (decrement)
3. StockHistory record created
```

### 5.8 Loyalty Program Flow

```
Customer Signup:
1. POST /loyalty/auth/register → creates LoyaltyUser
2. Email verification (signed URL, 24h expiry)

Claim Submission:
1. Customer uploads invoice photo + product photos + invoice number
2. Claim created (status = pending, submitted_at = now)
3. Rate limited: 5 claims/day per user

Admin Review:
1. Admin views claim queue
2. Adds line items (product_unit × quantity)
3. Points calculated: sum(quantity × product_units.points_per_unit)
4. Approve → PointsTransaction(earn) created, Claim status = approved
5. Reject → Claim status = rejected, rejection_reason set

Redemption:
1. Customer browses Prize catalog
2. Submits redemption (recipient_name, phone, address)
3. Points deducted → PointsTransaction(spend) created
4. Admin approves → ships → customer confirms delivery
5. Status flow: pending → approved → shipped → delivered
```

---

## 6. Multi-Tenancy Rules

### 6.1 The Tenanted Trait

Located at `app/Traits/Tenanted.php`. Used by: `SalesOrder`, `DeliveryOrder`, `ReceiveOrder`, `StockProductUnit`, `StockOpname`.

```php
// How it works:
// 1. Non-admin users are scoped to their assigned warehouses
// 2. Admin users (hasRole('admin')) see ALL data
// 3. Queries use user->warehouses() pivot to filter

// Usage in controllers:
SalesOrder::tenanted()->get();        // Scoped to user's warehouses
SalesOrder::findTenanted($id);       // Find with tenancy check
```

### 6.2 SPG Override

SPG users have special tenancy: they see only orders where `spg_id` matches their user ID, NOT warehouse-based scoping.

### 6.3 Key Rules

- **Always use `Tenanted` trait** on new model queries that should be warehouse-scoped
- **Admin bypass**: Admin role sees all data regardless of warehouse assignment
- **Warehouse assignment**: Via `user_warehouses` pivot table
- **Never hardcode warehouse IDs** in queries or logic

---

## 7. Auth & RBAC

### 7.1 Dual Auth System

| System | Model | Guard | Table | PK |
|--------|-------|-------|-------|----|
| Staff/Admin | `App\Models\User` | `sanctum` | `users` | bigint |
| Customer | `App\Models\Loyalty\LoyaltyUser` | `loyalty` | `loyalty_users` | ULID |

### 7.2 Token Abilities

| Token Type | Abilities | Access |
|------------|-----------|--------|
| Warehouse staff | `['*']` | All warehouse endpoints |
| Loyalty-only staff | `['loyalty']` | Only loyalty admin endpoints |
| Loyalty customer | `['loyalty']` | Only loyalty customer endpoints |

### 7.3 Permission Structure

19 top-level permission groups (defined in `app/Helpers/PermissionsHelper.php`):

```
user_access, user_discount_access, role_access, permission_access
product_access, product_category_access, product_brand_access, product_unit_access
supplier_access, warehouse_access, uom_access
receive_order_access, sales_order_access, invoice_access, order_access
delivery_order_access, stock_access, stock_opname_access, stock_history_access
adjustment_request_access, product_unit_blacklist_access, setting_access
payment_access, voucher_access
```

Each group has sub-permissions: `create`, `read`, `update`, `delete` (e.g., `sales_order_access.create`).

### 7.4 Related Permissions

Granting certain permissions auto-includes others:
- `sales_order_access` → also grants `user_access`, `payment_access`, `invoice_access`, `order_access` and their sub-permissions
- See `PermissionsHelper::getRelatedPermissions()` for full mapping

### 7.5 Admin Override

In `AuthServiceProvider::boot()`:
```php
Gate::before(function ($user, $ability) {
    if ($user->hasRole('admin')) return true;
});
```

---

## 8. Enums (bensampo/laravel-enum)

**Always use these enums, never hardcode string values.**

| Enum | Values | Used In |
|------|--------|---------|
| `CompanyEnum` | `pas`, `pa` | Product, SalesOrder, DeliveryOrder |
| `UserType` | `admin`, `reseller`, `customer`, `dealer`, `customer_event`, `spg` | User |
| `SalesOrderType` | `default`, `delivery`, `pickup`, `free` | SalesOrder, Order |
| `PaymentType` | `cash`, `transfer`, `credit_card`, `qris` | Payment |
| `DiscountType` | `nominal`, `percentage` | VoucherCategory, SalesOrder |
| `SettingEnum` | `so_number`, `do_number`, `tax_value`, `bank_name`, `bank_holder`, `bank_account` | Setting |
| `BatchSource` | `upload`, `import` | VoucherGenerateBatch |
| `ImportType` | `voucher` | Import routing |

---

## 9. Coding Conventions

### 9.1 Model Patterns

```php
// Typical model structure:
class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, Tenanted, FilterStartEndDate;

    protected $casts = [
        'type' => SalesOrderType::class,  // Enum casting
        'raw_source' => 'array',           // JSON casting
    ];

    // boot() for side effects (auto-numbering, defaults)
    protected static function boot() { ... }

    // Relationships
    public function details() { return $this->hasMany(SalesOrderDetail::class); }

    // Scopes
    public function scopeTenanted($query) { ... }
}
```

### 9.2 Controller Patterns

```php
// apiResource for standard CRUD
Route::apiResource('sales-orders', SalesOrderController::class);

// Nested resources for related entities
Route::get('receive-orders/{receiveOrder}/details', ...);
Route::post('receive-orders/{receiveOrder}/details', ...);

// Custom actions as separate routes
Route::put('receive-orders/{receiveOrder}/done', ...);
Route::post('delivery-orders/{deliveryOrder}/verification/{deliveryOrderDetail}', ...);
```

### 9.3 Request Validation

- Use Form Request classes in `app/Http/Requests/Api/`
- Nested request classes for complex validation
- `RequestToBoolean` trait for boolean conversion

### 9.4 Event/Listener Pattern

```php
// Event (simple class with public properties)
class VerifiedROEvent { public function __construct(public ReceiveOrder $receiveOrder) {} }

// Listener (queued for heavy operations)
class CreateStockROListener implements ShouldQueue { ... }

// Registration in EventServiceProvider::$listen
```

### 9.5 Service Pattern

```php
// Services in app/Services/
class SalesOrderService
{
    public function createOrder(array $data): SalesOrder
    {
        return app(Pipeline::class)
            ->send(new SalesOrder)
            ->through([...pipes])
            ->thenReturn();
    }
}
```

---

## 10. Key File Locations

### 10.1 Models

| Model | Path |
|-------|------|
| User | `app/Models/User.php` |
| Warehouse | `app/Models/Warehouse.php` |
| Product | `app/Models/Product.php` |
| ProductUnit | `app/Models/ProductUnit.php` |
| StockProductUnit | `app/Models/StockProductUnit.php` |
| Stock | `app/Models/Stock.php` |
| StockHistory | `app/Models/StockHistory.php` |
| ReceiveOrder | `app/Models/ReceiveOrder.php` |
| ReceiveOrderDetail | `app/Models/ReceiveOrderDetail.php` |
| SalesOrder | `app/Models/SalesOrder.php` |
| SalesOrderDetail | `app/Models/SalesOrderDetail.php` |
| SalesOrderItem | `app/Models/SalesOrderItem.php` |
| DeliveryOrder | `app/Models/DeliveryOrder.php` |
| DeliveryOrderDetail | `app/Models/DeliveryOrderDetail.php` |
| StockOpname | `app/Models/StockOpname.php` |
| AdjustmentRequest | `app/Models/AdjustmentRequest.php` |
| Payment | `app/Models/Payment.php` |
| Voucher | `app/Models/Voucher.php` |
| LoyaltyUser | `app/Models/Loyalty/LoyaltyUser.php` |
| Claim | `app/Models/Loyalty/Claim.php` |
| Prize | `app/Models/Loyalty/Prize.php` |
| Redemption | `app/Models/Loyalty/Redemption.php` |
| PointsTransaction | `app/Models/Loyalty/PointsTransaction.php` |

### 10.2 Controllers

All API controllers: `app/Http/Controllers/Api/`

Key controllers:
- `SalesOrderController.php` — SO CRUD + print + export
- `DeliveryOrderController.php` — DO CRUD + verification + return + print
- `ReceiveOrderController.php` — RO CRUD + done
- `ReceiveOrderDetailController.php` — RO detail CRUD + verify
- `StockController.php` — Stock management (15+ custom actions)
- `StockOpnameController.php` — Opname CRUD + scan + done
- `AdjustmentRequestController.php` — Adjustment CRUD + approve
- `InvoiceController.php` — Invoice export + bill + PDF
- `PaymentController.php` — Payment CRUD

### 10.3 Services & Pipes

| File | Purpose |
|------|---------|
| `app/Services/SalesOrderService.php` | SO creation/update pipeline orchestration |
| `app/Services/PermissionService.php` | Permission resolution |
| `app/Services/SettingService.php` | Bank transfer info |
| `app/Pipes/Order/FillOrderAttributes.php` | Pipeline step 1 |
| `app/Pipes/Order/FillOrderRecords.php` | Pipeline step 2 |
| `app/Pipes/Order/MakeOrderDetails.php` | Pipeline step 3 |
| `app/Pipes/Order/CalculateAutoDiscount.php` | Pipeline step 4 |
| `app/Pipes/Order/CalculateVoucher.php` | Pipeline step 5 |
| `app/Pipes/Order/CalculateAdditionalDiscount.php` | Pipeline step 6 |
| `app/Pipes/Order/CalculateAdditionalFees.php` | Pipeline step 7 |
| `app/Pipes/Order/CheckExpectedOrderPrice.php` | Pipeline step 8 |
| `app/Pipes/Order/SaveOrder.php` | Pipeline step 9 |
| `app/Pipes/Order/UpdateOrder.php` | Update pipeline step 9 |

### 10.4 Events & Listeners

| Event | Listener | Trigger |
|-------|----------|---------|
| `VerifiedROEvent` | `CreateStockROListener` | ReceiveOrder is_done → true |
| `UnverifiedROEvent` | `DeleteStockROListener` | ReceiveOrder is_done → false / delete |
| `ProductUnitCreated` | `CreateStockProductUnit` | ProductUnit created (refer_id = null) |
| `StockOpnameCreated` | `CreateStockOpnameDetail` | StockOpname created |
| `StockOpnameDetailCreated` | `CreateStockOpnameItems` | StockOpnameDetail created |
| `LoyaltyUserRegistered` | `SendLoyaltyVerificationEmail` | LoyaltyUser created |

### 10.5 Enums

All enums: `app/Enums/`

### 10.6 Traits

| Trait | Path | Purpose |
|-------|------|---------|
| Tenanted | `app/Traits/Tenanted.php` | Warehouse-scoped queries |
| CustomSoftDeletes | `app/Traits/CustomSoftDeletes.php` | Soft deletes with `deleted_by` |
| FilterStartEndDate | `app/Traits/FilterStartEndDate.php` | Date range filtering |

### 10.7 Helpers

| Helper | Path | Purpose |
|--------|------|---------|
| Helper | `app/Helpers/Helper.php` | `rupiah()` formatting |
| PermissionsHelper | `app/Helpers/PermissionsHelper.php` | Permission tree, related permissions |

---

## 11. Important Technical Details

### 11.1 QR/ULID System

- `stocks.id` is a ULID (26-character Crockford base32)
- This ULID IS the QR code payload / serial number
- QR code PNG generation to S3 is currently commented out
- QR images appear to be rendered client-side
- Stock grouping: parent-child via `stocks.parent_id`

### 11.2 Auto-Numbering

| Document | Format | Example | Table |
|----------|--------|---------|-------|
| Sales Order | `PAS/SO/MM/YY/NN` | `PAS/SO/05/26/01` | `settings` (key: `so_number`) |
| Delivery Order | `PAS/DO/MM/YY/NN` | `PAS/DO/05/26/01` | `settings` (key: `do_number`) |

- Uses `DB::transaction` + `lockForUpdate` on `settings` table to prevent race conditions
- Sequential counter resets monthly

### 11.3 Stock Tracking Dual Model

| Type | Flag | Tracking Method |
|------|------|----------------|
| QR items | `is_generate_qr = true` | Individual `Stock` rows (count available stocks) |
| Non-QR items | `is_generate_qr = false` | `StockProductUnit.qty` integer |

### 11.4 File Storage

- AWS S3 for all file uploads
- Used by: Payment media, Brand logos, Loyalty claim photos, Prize photos
- Spatie Media Library for payment attachments

### 11.5 Price Calculation Rules

1. Base price = sum of (unit_price × qty) across all details
2. Auto discount: percentage applied if order exceeds threshold
3. Voucher discount: NOMINAL (fixed) or PERCENTAGE
4. Additional discount: percentage or nominal
5. Price floor: all discounts use `max(..., 0)` to prevent negatives
6. Shipment fee: added last
7. Tax: 11% PPN per-item when `is_ppn = true`
8. Total price validation: BE recalculates and compares to FE-submitted total

---

## 12. Do's and Don'ts

### Do's

- **DO** use the `Tenanted` trait on any new model that should be warehouse-scoped
- **DO** use enum classes (`CompanyEnum`, `UserType`, etc.) instead of hardcoded strings
- **DO** use `lockForUpdate` when generating sequential numbers
- **DO** check `is_generate_qr` before stock operations
- **DO** use SoftDeletes on new business entity models
- **DO** create Form Request classes for validation
- **DO** use Spatie Query Builder for filtering/sorting in index endpoints
- **DO** use events/listeners for side effects (stock creation, notifications)
- **DO** check existing patterns in nearby files before writing new code

### Don'ts

- **DON'T** hardcode warehouse IDs in queries or logic
- **DON'T** hardcode string values for enum fields
- **DON'T** skip the `Tenanted` trait on warehouse-scoped queries
- **DON'T** create Stock records directly — use events/listeners
- **DON'T** store points balance — always compute from `points_transactions`
- **DON'T** use `loyalty_users` table for warehouse staff or vice versa
- **DON'T** commit secrets or API keys
- **DON'T** add comments unless explicitly asked
- **DON'T** create new files without checking existing patterns first

---

## 13. Existing Documentation

| File | Content |
|------|---------|
| `BACKEND_AUDIT.md` | Comprehensive technical audit (782 lines) — tech stack, database schema, API surface, QR/serial format, operational status |
| `LOYALTY_SPEC.md` | Loyalty program specification (597 lines) — user stories, data model, state machines, API endpoints, fraud rules, phasing |
| `SECURITY_FINDINGS.md` | Security findings (111 lines) — permission bypasses, middleware mismatches, plaintext tokens, unscoped OAuth |
| `task.md` | Original project description (Indonesian) requesting this file's creation |

---

## 14. Running the Application

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Development
php artisan serve          # Start dev server
php artisan queue:work     # Process queued jobs (Redis)
php artisan queue:listen   # Alternative queue worker

# Testing
php artisan test           # Run PHPUnit tests

# Code style
./vendor/bin/pint          # Laravel Pint (code formatting)

# Build
npm run build              # Vite build
```

---

## 15. Frontend CORS Allowlist

The following frontend domains are allowed:
- `bejo.platinumadisentosa.com` (production)
- `platinum-warehouse.vercel.app`
- `platinum-warehouse-beta.vercel.app`
- `bejo-platinum-product-verify.vercel.app` (QR verification)
