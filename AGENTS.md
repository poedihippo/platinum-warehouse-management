# AGENTS.md — AI Agent Guide for BEJO Warehouse Management

> **Purpose:** Shared guide for AI agents (OpenCode reads it directly; Claude Code imports it from `CLAUDE.md`). Read it before making changes. It describes the code **as it is**, including known quirks — when the code and this file disagree, trust the code and update this file.

---

## 1. Project Identity

| Field | Value |
|-------|-------|
| **App Name** | BEJO |
| **Company** | Platinum Adi Sentosa (two legal entities: `pas`, `pa`) |
| **Industry** | Feed/pakan distributor |
| **Type** | Warehouse Management System (WMS) + customer Loyalty program |
| **Frontend** | Separate repos (Vercel deployments): BEJO admin app, and the customer "verify" app (QR check + loyalty) |
| **Backend** | This repo — Laravel 13 REST API (`routes/api.php`, prefix `/api`) |
| **Language** | User-facing messages (validation, errors) are in Indonesian |

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 (`laravel/framework: ^13.17`), Laravel 13 app structure (`bootstrap/app.php`) — see §17 |
| PHP | Runtime **8.5** locally and in production (`composer.json` `^8.4`); the default CLI on the dev machine is still 8.3, so call `php8.5` explicitly |
| Database | MySQL (migrations contain MySQL-only SQL — no SQLite) |
| Cache / Session | File-based |
| Queue | Redis (`QUEUE_CONNECTION=redis`); `sync` in tests |
| Auth | Laravel Sanctum 4 (Bearer personal access tokens) |
| RBAC | `spatie/laravel-permission` 6 |
| Query Builder | `spatie/laravel-query-builder` 7 (variadic `allowed*()`) |
| Media Library | `spatie/laravel-medialibrary` 11 |
| Excel/CSV | `maatwebsite/excel` 4 (PhpSpreadsheet 5) |
| PDF | `barryvdh/laravel-dompdf` 3 |
| QR Code | `simplesoftwareio/simple-qrcode` ~4 |
| Enums | Native PHP backed enums + `App\Enums\BaseEnum` trait (BenSampo removed) |
| OAuth | `laravel/socialite` 5 |
| Dates | Carbon 3 (no `diffIn*` in the code base — if you add one, pass `true` and cast, see §17) |
| File Storage | AWS S3 (`league/flysystem-aws-s3-v3`) |
| Email | Brevo (`symfony/brevo-mailer`) |
| Timezone | `Asia/Jakarta` |

---

## 3. Architecture Overview

### 3.1 High-Level Pattern (Controller → Service → Repository, HRIS layout)

```
routes/api.php ──▶ Api\XController extends Api\BaseController
                      │   (permission middleware in __construct, Form Request, wraps API Resource / status code)
                      ▼
                   XServiceInterface ──▶ Http\Services\X\XService extends BaseService
                      │   (business rules, transactions, guards → ApiResponseException, pipelines)
                      ▼
                   XRepositoryInterface ──▶ Http\Repositories\X\XRepository extends BaseRepository
                      │   (Eloquent / QueryBuilder only)
                      ▼
                   Eloquent Model ──▶ booted() hooks ──▶ Events ──▶ (queued) Listeners
```

| Layer | Location |
|-------|----------|
| Interfaces | `app/Interfaces/{Repositories,Services}/{Module}/{Name}{Repository,Service}Interface.php` (+ `BaseRepositoryInterface`, `BaseServiceInterface`); loyalty adds one level: `.../Loyalty/{Claim,Redemption,Prize,...}/` |
| Implementations | `app/Http/{Repositories,Services}/{Module}/...` (+ `BaseRepository`, `BaseService`) |
| Bindings | `AppServiceProvider::$singletons` (interface → implementation). Every new interface must be added there; `tests/Unit/ContainerBindingsTest` resolves them all |
| Controllers | Flat in `app/Http/Controllers/Api/` (+ `Admin/`, `Admin/Loyalty/`, `Loyalty/`, `Loyalty/Auth/`, `Public/`), all extend `Api\BaseController` |
| Requests | `app/Http/Requests/Api/{Module}/StoreRequest.php`, `UpdateRequest.php`, `MergeRequest.php`, ... |
| Resources | `app/Http/Resources/{Module}/XResource.php` (`DefaultResource` stays at the root) |

Models, Events, Listeners, Jobs, Imports, Exports and Pipes keep their namespaces (class names are stored in `model_type` columns and queued jobs). There is no `app/Services/` anymore — all services are instance classes resolved from the container.

### 3.2 Key Architectural Patterns

| Pattern | Location | Purpose |
|---------|----------|---------|
| **Service / Repository** | `app/Http/Services`, `app/Http/Repositories` | All business logic and data access, behind interfaces |
| **Pipeline** | `app/Pipes/Order/`, `app/Pipes/Order/Spg/` | Sales order create/update (10 steps), SPG order flow; pipes get dependencies by constructor injection |
| **Event-Driven** | `app/Events/`, `app/Listeners/` | Stock slot creation, RO stock in/out, opname details/items |
| **Model hooks** | `booted()` in models | Auto-numbering, defaults, firing events, scan propagation |
| **Tenancy** | `app/Traits/Tenanted.php` + per-model overrides | Warehouse-scoped data access (see §6 — not uniform!) |
| **Dual Auth** | `config/auth.php` | `users` (staff, Sanctum) + `loyalty_users` (customers, `loyalty` guard) |
| **Custom Soft Deletes** | `app/Traits/CustomSoftDeletes.php` | Soft delete + `deleted_by` (used by `Payment` only) |
| **Document numbering** | `NumberingServiceInterface` (`Http/Services/DocumentCounter/NumberingService`) | SO/DO numbers via `document_counters` + `lockForUpdate` (feature-flagged) |
| **API error exception** | `App\Exceptions\ApiResponseException($message, $status, $extra)` | Thrown by services for business-rule errors; renders exactly `{"message": ..., ...$extra}` with `$status` |

---

## 4. Database Schema Reference

### 4.1 Tables

~53 tables from `database/migrations/` (85 migration files) plus the Spatie permission tables. Inspect live schema with the MySQL MCP or read migrations; key groups:

| Group | Key Tables |
|-------|------------|
| Auth & RBAC | `users`, `roles`, `permissions`, `model_has_*`, `personal_access_tokens` (has extra `plain_text_token`) |
| Tenant & Supply Chain | `warehouses`, `suppliers`, `user_warehouses` |
| Product | `products`, `product_units`, `product_unit_relations`, `product_unit_blacklists`, `product_brands`, `product_categories`, `uoms` |
| Stock | `stocks` (ULID), `stock_product_units`, `stock_histories`, `stock_transfers`, `temporary_stocks` |
| Receive Orders | `receive_orders`, `receive_order_details` |
| Sales Orders | `sales_orders`, `sales_order_details`, `sales_order_items`, `sales_order_vouchers` (pivot) |
| Delivery Orders | `delivery_orders` (has `sales_order_id`), `delivery_order_details` |
| Stock Opname | `stock_opnames`, `stock_opname_details`, `stock_opname_items` |
| Adjustments | `adjustment_requests` |
| Payments / Vouchers | `payments`, `vouchers`, `voucher_categories`, `voucher_generate_batches` |
| Misc | `user_discounts`, `settings`, `document_counters`, `media`, `jobs` |
| Loyalty | `loyalty_users`, `claims`, `claim_line_items`, `claim_photos`, `points_transactions`, `prizes`, `prize_categories`, `redemptions` |
| Legacy / unused | `orders`, `order_details` (see §5.9), `packages`, `package_details`, `tenants` |

Several columns exist in production but were added retroactively by guarded migrations (`2026_10_23_000000_add_production_only_columns_...`). New alter migrations should use `Schema::hasColumn` guards the same way.

### 4.2 Core Entity Relationships

```
ProductCategory ──┐
                   ├──▶ Product ──▶ ProductUnit ──┬──▶ StockProductUnit (ProductUnit × Warehouse, qty) ──▶ Stock (ULID)
ProductBrand ─────┘                               │                                                        ├──▶ parent_id (grouping)
                                                  │                                                        ├──▶ SalesOrderItem
                                                  │                                                        └──▶ StockOpnameItem
                                                  ├──▶ refer_id / refer_qty (bundle unit → base unit)
                                                  ├──▶ ProductUnitRelation (bundle → non-QR components, qty)
                                                  └──▶ Uom

User ──▶ SalesOrder (user_id creator, reseller_id, spg_id)
      ──▶ user_warehouses (pivot) ──▶ Warehouse

ReceiveOrder ──▶ ReceiveOrderDetail ──(RO done)──▶ Stock rows / SPU.qty

SalesOrder ──▶ SalesOrderDetail ──▶ SalesOrderItem (scanned Stock) ──▶ delivery_order_detail_id
           ──▶ sales_order_vouchers ──▶ Voucher
           ──▶ merged_into_sales_order_id (marketplace merge)

DeliveryOrder (sales_order_id) ──▶ DeliveryOrderDetail ──▶ SalesOrderDetail
```

### 4.3 Key ID Strategies

| Table | PK Type | Reason |
|-------|---------|--------|
| `stocks` | ULID (26-char) | QR code payload / serial number |
| `temporary_stocks` | stock ULID | Scan log keyed by stock |
| `loyalty_users`, `claims`, `redemptions`, `prizes`, `points_transactions`, ... | ULID | Loyalty system |
| All others | bigint auto-increment | Standard Laravel |

---

## 5. Core Business Flows

### 5.1 ProductUnit Creation → Stock Slot Auto-Creation

```
1. ProductUnit created with refer_id = null
2. booted() created → ProductUnitCreated::dispatchIf(is_null(refer_id))
3. CreateStockProductUnit listener (queued)
4. For every Warehouse::withTrashed() → StockProductUnit (qty = 0)
```
Bundle units (`refer_id` set, created via `POST product-units/{id}/create-relations`) get **no** SPU from this event.

### 5.2 Warehouse Creation → Stock Slot Auto-Creation

```
1. Warehouse created
2. booted() created runs SYNCHRONOUSLY: ProductUnit::get() (excludes soft-deleted units,
   INCLUDES bundle units with refer_id) → StockProductUnit (qty = 0) for each
```

### 5.3 Receive Order (Inbound Stock)

```
1. POST receive-orders uploads an Accurate-style XML file (TRANSACTIONS.RECIEVEITEM);
   details are parsed from it; blacklisted product units (product_unit_blacklists) are skipped
2. Staff verifies each detail (is_verified) and sets adjust_qty
3. PUT receive-orders/{id}/done — requires every detail verified and adjust_qty > 0
4. booted() updated + isDirty('is_done') → VerifiedROEvent (captures auth user in constructor)
5. CreateStockROListener (queued), per detail using adjust_qty:
   ├─ is_generate_qr = true  → creates adjust_qty Stock rows (ULID)
   └─ is_generate_qr = false → increments StockProductUnit.qty
   + StockHistory (increment) on the ReceiveOrderDetail
```
Undo: `is_done` → false, or RO delete → `UnverifiedROEvent` → `DeleteStockROListener` (queued). See §13 for known bugs in this listener.

### 5.4 Sales Order Processing

`SalesOrderController@store` → `SalesOrderServiceInterface::storeOrder($validated, (bool) $request->is_preview)`, which runs `DB::transaction(fn () => $this->createOrder(SalesOrder::make(['raw_source' => $validated]), $isPreview), 3)`.

```
Create pipeline (SalesOrderService::createOrder):
  1. FillOrderAttributes          raw_source → model attributes
  2. FillOrderRecords             snapshot reseller etc. into `records` JSON
  3. NormalizeLockedOrderItems    keep lines that already have scanned stock locked
  4. MakeOrderDetails             build SalesOrderDetail collection, resolve ProductUnits, prices
                                  (does NOT check or reserve stock)
  5. CalculateAutoDiscount        tiers in config('app.min_trx_auto_discount') — currently all
                                  commented out (effectively 0); skipped if is_auto_discount_enabled=false
  6. CalculateVoucher             voucher_codes[] applied in sequence, each capped at current price;
                                  stored in sales_order_vouchers pivot with discount_amount
  7. CalculateAdditionalDiscount  % or nominal
  8. CalculateAdditionalFees      shipment_fee
  9. CheckExpectedOrderPrice      throws Exception('Harga tidak cocok') on mismatch with FE total
 10. SaveOrder                    only when NOT preview; persists order + details.
                                  Stock reservation code is commented out — stock is attached
                                  later by scanning (DO verification / invoice verification).

Update pipeline (SalesOrderService::updateOrder): same steps 1–9, ending in UpdateOrder.
  Marketplace SO: edits lines in place; lines with scanned stock are locked.
  Other SO: fails if DO lines exist, otherwise deletes + recreates lines.
```

### 5.5 Delivery Order (Partial Shipment)

```
1. Create DeliveryOrder (has sales_order_id)
2. POST delivery-orders/{id}/attach — DeliveryOrderService::attachDetails links SO detail lines
   with a remaining-qty check
3. POST delivery-orders/{id}/verification/{deliveryOrderDetail} — scan Stock ULIDs:
   matches warehouse + product unit (or its refer_id for bundle units); a group parent creates a
   parent item plus child items; incomplete groupings are rejected (Stock::hasIncompleteGrouping)
4. PUT delivery-orders/{id}/done — StockHistory decrement (value = fulfilled_qty); for bundle units
   also decrements related non-QR SPUs by relation.qty × floor(qty / refer_qty). Undone reverses.
5. POST delivery-orders/{id}/return — sets is_returned; deletes stocks or creates an auto-approved
   AdjustmentRequest
```
`fulfilled_qty` = count of non-parent, non-returned SalesOrderItems. One SO can have many DOs.
Shared scan logic: `SalesOrderService::verifyStockItem()`.

### 5.6 Stock Opname (Physical Count)

```
1. StockOpname created → StockOpnameCreated → CreateStockOpnameDetail (queued):
   a StockOpnameDetail per StockProductUnit in the warehouse
2. StockOpnameDetail created → StockOpnameDetailCreated → CreateStockOpnameItems (queued):
   a StockOpnameItem per available Stock
3. Scan (StockOpnameDetailController::scan) → is_scanned; parent→child propagation
   (both in the scan action and in StockOpnameItem updated hook)
4. PUT stock-opnames/{id}/done requires every detail is_done:
   un-scanned Stock rows are SOFT-DELETED + StockHistory decrement per detail
5. Revert (is_done = false): restores them + StockHistory increment
```
Only QR stock is counted; non-QR `qty` is untouched.

### 5.7 Stock Adjustment

`AdjustmentRequestController::approve` takes `is_approved` true/false (also used to reject).
- Increment, QR: creates N `Stock` rows directly (`is_stock = productUnit.is_auto_stock`)
- Decrement, QR: force-deletes stocks created by this adjustment + soft-deletes `value` more available stocks
- Non-QR: adjusts `StockProductUnit.qty`
- StockHistory written. DO return and stock repack also create AdjustmentRequests.

### 5.8 Loyalty Program

- **Signup**: `POST loyalty/auth/register` → LoyaltyUser → `LoyaltyUserRegistered` → verification mail (sent **synchronously**); link built from `config('loyalty.frontend_url')` (`LOYALTY_FRONTEND_URL`), signed via `loyalty.signed` middleware.
- **Claims**: customer uploads invoice + product photos; `pending → approved | rejected` (both final; deciding twice → 409). Rate limit 5/day (`throttle:loyalty-claims`). Approve locks the row, recomputes `sum(qty × product_units.points_per_unit)`, writes a PointsTransaction `earn`, sends mail.
- **Redemptions**: `pending → approved → shipped → delivered`, or `pending → rejected`. Redeem locks the prize, checks stock + balance, decrements prize stock, writes `spend`. Reject restocks and refunds with `earn` (source `redemption`). Second pending redemption of the same prize → 409.
- **Balance** is never stored: `sum(earn) − sum(spend)` from `points_transactions` (computed in `PointsController::balance` and private `balanceFor` copies). Sources: `claim`, `redemption`, `manual_adjustment`.
- Statuses are plain string constants on the models, not enums. No loyalty service class — logic lives in controllers.

### 5.9 SPG Orders, Invoices, Marketplace

- **`/orders` (SPG quotations)** — `OrderController` → `OrderService` works on **`SalesOrder`** rows with `is_invoice = true` and `invoice_no = ''`, using its own pipeline ending in `Pipes\Order\Spg\SaveOrder`. `PUT orders/{order}/convert-so` runs `Spg\ConvertToSO` (assigns `getSoNumber()`, writes StockHistory decrements). The `orders`/`order_details` tables and `Order`/`OrderDetail` models + `OrderDetailController` are legacy and broken — don't build on them.
- **`/invoices` (IIPE)** — `InvoiceController` on `SalesOrder::whereInvoice()` (`is_invoice = true`). Store pre-checks available stock but reserves nothing; scanning via `verification` → `verifyStockItem`. Numbers from `InvoiceNumberServiceInterface::getSoNumber()` (see §11.2); logic in `InvoiceService`. `GET invoices/{id}/print` is a **web** route.
- **Marketplace** (`Marketplace` enum: blibli/shopee/tokopedia) — `POST sales-orders/merge` (`SalesOrderService::mergeMarketplaceOrders`) merges orders into one SO (`merged_into_sales_order_id`) and auto-creates its DO via `DeliveryOrderServiceInterface`.

### 5.10 Other Stock Features

- **Grouping**: a group parent is itself a `Stock` row with `description = "{code} GR-{mmyy}{NNN}"`; children point to it with `parent_id`. Groups are excluded from counts by `description IS NULL`. Endpoints: `stocks/grouping`, `stocks/grouping-by-scan`, `stocks/{stock}/ungrouping` (force-deletes the parent). Most stock operations cascade to children.
- **Transfer**: `POST stocks/transfer` (`StockTransferController` + `StockTransfer\TransferRequest`) — QR moves `stock_product_unit_id` of the stock and its children; non-QR moves `qty`; one `StockTransfer` row + two histories. Group children are rejected. Spec: `docs/tasks/stock/stock-transfer/backend-flow-v3.md`.
- **Bundle units**: `refer_id`/`refer_qty` + `ProductUnitRelation` (components must be non-QR). Selling one scans the referenced unit's stock.
- **Other**: repack (`stocks/{stock}/repack`), printing queue / tempel / `add-to-stock` (`stocks.is_stock`), `stocks/record`, `stocks/export-txt`, `stocks/import` (`StockImport` → `GenerateStockQrcode` job), `temporary-stocks` scan log.
- **Public QR verification**: `GET api/public/stocks/{ulid}` (no auth, `throttle:30,1`) — `Public\StockVerificationController`.
- Stock scopes on `Stock`: `whereAvailableStock` (no unreturned SalesOrderItem), `whereCountableStock` (available + `is_stock` + `description IS NULL`).

---

## 6. Multi-Tenancy Rules

### 6.1 The Tenanted Trait

`app/Traits/Tenanted.php`:
- `scopeTenanted(Builder $q, ?User $user = null)` — user defaults to `auth('sanctum')->user()`; Spatie role `admin` → unfiltered; otherwise `whereIn('warehouse_id', $user->warehouses()->pluck('warehouse_id'))`.
- `scopeFindTenanted($q, $id, $columns = ['*'], $fail = true)` → `firstOrFail()` / `first()`.

Used by `ReceiveOrder`, `DeliveryOrder`, `StockProductUnit`, `StockOpname`, and `SalesOrder` (overridden — below).

### 6.2 Overrides and Relation-Based Scopes

| Model | Behavior |
|-------|----------|
| `SalesOrder` | **Overrides** `scopeTenanted`: SPG users (`UserType::Spg`) → `where('spg_id', $user->id)`; everyone else → **no filter** (warehouse scoping commented out) |
| `Warehouse` | Own `scopeTenanted` that returns the query unfiltered |
| `Payment` | Scoped via `whereHas('salesOrder', tenanted)` |
| `ReceiveOrderDetail` | Via `receiveOrder` |
| `Stock`, `StockHistory` | Via `stockProductUnit` |

SPG rules also live in `OrderController` (SPG can't convert to SO; can only delete own orders) and `OrderStoreRequest` (forces `spg_id`).

### 6.3 Rules

- Query warehouse-scoped models through `::tenanted()` / `::findTenanted($id)` (not route-model binding alone).
- Validate incoming warehouse/related IDs with `App\Rules\TenantedRule` (checks the ID exists within `Model::tenanted()`, default `Warehouse`).
- Warehouse assignment is the `user_warehouses` pivot (`User::warehouses()`). Never hardcode warehouse IDs.

---

## 7. Auth & RBAC

### 7.1 Dual Auth System

| System | Model | Guard | PK |
|--------|-------|-------|----|
| Staff/Admin | `App\Models\User` | `sanctum` (Sanctum's built-in guard, `provider = null`; `config/sanctum.php` guard `['web']`) | bigint |
| Customer | `App\Models\Loyalty\LoyaltyUser` | `loyalty` (driver `sanctum`, provider `loyalty_users`) | ULID |

Login: `POST api/auth/token` → `createToken($device, $user->tokenAbilities())`, response `{"data":{"token": ...}}`. `User::createToken` is overridden and also stores `plain_text_token` (custom `App\Models\PersonalAccessToken`). Socialite login mints tokens the same way.

### 7.2 Token Abilities & Route Groups (`routes/api.php`)

| Group | Middleware | Who |
|-------|-----------|-----|
| Public | none / `throttle` | `auth/token`, `public/stocks/{ulid}`, loyalty `auth/*` |
| `users/me` | `auth:sanctum` | Any staff token (shared by BEJO + verify) |
| Loyalty customer (`loyalty/*`) | `auth:loyalty`, `loyalty.active` | LoyaltyUser tokens (`['loyalty']`) |
| Loyalty admin (`admin/loyalty/*`) | `auth:sanctum` only — **no ability check** | Any staff token; each controller enforces its permission |
| Warehouse (everything else) | `auth:sanctum`, `ability:warehouse` | Tokens with `['*']` |

`User::tokenAbilities()`: `['loyalty']` if every role the user holds is in `User::LOYALTY_ONLY_ROLES` (loyalty manager / reviewer / prize manager / fulfillment), else `['*']`. No token is ever minted with a literal `warehouse` ability — `['*']` satisfies `ability:warehouse`.

Custom middleware aliases: `loyalty.signed` (`ValidateLoyaltySignature`), `loyalty.active` (`EnsureLoyaltyAccountActive`, 403 "Akun dinonaktifkan."). Rate limiters (`AppServiceProvider::boot()`): `api` 200/min, `loyalty-claims` 5/day. Guests always get JSON 401 `{"message":"Unauthenticated."}` (`redirectGuestsTo(null)` in `bootstrap/app.php` — there is no `login` route).

### 7.3 Warehouse Permissions

Defined in `PermissionsHelper::adminPermissions()` as `parent_access => [children]` — **25 groups**:

```
user_access, user_discount_access, role_access, permission_access,
product_access, product_category_access, product_brand_access, product_unit_access,
supplier_access, warehouse_access, uom_access,
receive_order_access, sales_order_access, invoice_access, order_access,
delivery_order_access, stock_access, stock_opname_access, stock_history_access,
adjustment_request_access, product_unit_blacklist_access, setting_access,
payment_access, voucher_access, document_counter_access
```

Children use **underscores**: `{x}_read`, `{x}_create`, `{x}_edit`, `{x}_delete`, plus extras such as `receive_order_done`, `sales_order_print`, `sales_order_export_xml`, `stock_grouping`, `stock_print`, `stock_transfer`, `adjustment_request_approve`, `voucher_import`, `invoice_export_xml`. All use guard `web`. Seeded by `PermissionSeeder` (`firstOrCreate`, `parent_id` tree).

`getMyPermissions()` returns a nested boolean map exposed on `GET users/me` (`UserResource`).

**Adding a permission**: add it to `adminPermissions()`, run `php artisan db:seed --class=PermissionSeeder`, then reference it in the controller middleware.

### 7.4 Enforcement

Warehouse controllers declare Spatie middleware in `__construct()` (after `parent::__construct()`):

```php
$this->middleware('permission:receive_order_read', ['only' => ['index', 'show']]);
$this->middleware('permission:receive_order_create', ['only' => 'store']);
```

There are no policies, no `$this->authorize()`, no `can:` route middleware. `abort_if(!tokenCan(...))` calls that remain are no-ops (all warehouse tokens are `['*']`).

Gaps (protected only by `ability:warehouse`): `SalesOrderController` (all middleware commented out), `SalesOrderItemController`, `StockOpnameItemController`, `TemporaryStockController`, `ExportController`, `StockHistoryController`, `OrderDetailController`; several controllers guard only writes, not `index`/`show`. See `SECURITY_FINDINGS.md`.

### 7.5 Related Permissions

When a role is saved, `RoleController` → `PermissionService::getPermissionNames($ids)` adds `PermissionsHelper::getRelatedPermissions()` (e.g. `sales_order_access` → `product_unit_read`, `warehouse_read`, `user_access`, payment/invoice/order groups; `delivery_order_access` → `sales_order_read` + payment; `user_access` → `role_read`) then `syncPermissions()` and `cache()->flush()`.

### 7.6 Admin Override

```php
// AppServiceProvider::boot()
Gate::before(fn ($user, $ability) => $user->hasRole('admin') ? true : null);
```
Admin = Spatie role `admin` (seeded by `AdminSeeder`), which also bypasses `Tenanted`. The `UserType::Admin` type alone does not grant this.

### 7.7 Loyalty Admin Permissions

Separate, space-separated names from `LoyaltyPermissionSeeder`: `manage loyalty points`, `manage prizes`, `review redemptions`, `manage brands`, `manage customers`, `review claims`; roles `loyalty manager`, `loyalty reviewer`, `loyalty prize manager`, `loyalty fulfillment`. Controllers under `Api/Admin/Loyalty/` use:

```php
use DeniesUnlessCan;   // App\Traits\Controllers\DeniesUnlessCan

// first line of every action (after validation, so 422 still wins over 403):
if ($denied = $this->denyUnlessCan($request, self::PERMISSION, '...')) return $denied;
```
Customer list endpoints clamp `per_page` with `ClampsLoyaltyPerPage::loyaltyPerPage()`.

---

## 8. Enums (native PHP backed enums, `app/Enums/`)

**Always use these enums, never hardcode string values.** Every enum is `enum X: string { use BaseEnum; case ... }`. Case names are PascalCase or UPPER (e.g. `UserType::Spg`, `CompanyEnum::PAS`) — a case is an object, so use `UserType::Spg->value` wherever a string is needed (`create([...])`, `where()`, `in:` rules, string comparisons). Model casts (`'type' => UserType::class`) still serialize to the value.

`App\Enums\BaseEnum` (trait) keeps the BenSampo-style helpers: `is()`, `isNot()`, `in()`, `getValues()`, `getKeys()`, `hasValue()`, `coerce()`, `getValue()`, `all()`, `getDescription()`. Validate with `new App\Rules\EnumValue(X::class)` (same message as BenSampo: "The value you have entered is invalid.").

| Enum | Values | Notes |
|------|--------|-------|
| `CompanyEnum` | `pas`, `pa` | `invoiceNumberFormat()` |
| `DocumentType` | `so`, `do` | Numbering scope |
| `UserType` | `admin`, `reseller`, `customer`, `dealer`, `customer_event`, `spg` | Resellers get a `UserDiscount` per brand on create |
| `SalesOrderType` | `default`, `delivery`, `pickup`, `free` | |
| `Marketplace` | `blibli`, `shopee`, `tokopedia` | Marketplace SOs / merge |
| `PaymentType` | `cash`, `transfer`, `credit_card`, `qris` | |
| `DiscountType` | `nominal`, `percentage` | |
| `SettingEnum` | `so_number`, `do_number`, `tax_value`, `bank_name`, `bank_holder`, `bank_account` | `getValueType()` |
| `BatchSource` | `upload`, `import` | VoucherGenerateBatch |
| `ImportType` | `voucher` | `getImporter()`, `getExporter()`, `getModel()` |

---

## 9. Coding Conventions

### 9.1 Models

```php
class SalesOrder extends Model
{
    use FilterStartEndDate, Tenanted;          // SalesOrder/DeliveryOrder/ReceiveOrder have no SoftDeletes

    protected $casts = [
        'type' => SalesOrderType::class,
        'raw_source' => 'array',
    ];

    protected static function booted(): void   // hooks live in booted(), not boot()
    {
        static::creating(function (self $model) { /* defaults, user_id, numbering */ });
    }
}
```
- Models hold relations, casts, scopes and hooks only — no query logic for endpoints (that belongs in a repository). Hooks that need a service resolve it with `app(XServiceInterface::class)` (e.g. numbering in `SalesOrder`/`DeliveryOrder` `creating`, `StockOpname` `saved` → `StockHistoryRepositoryInterface`).
- Only `User`, `SocialAccount`, `TemporaryStock` and `Loyalty/*` models use `HasFactory`; factories for other models exist and are used via `XFactory::new()`.
- Event discovery is off (`withEvents(discover: false)`) — register new listeners with `Event::listen()` in `AppServiceProvider::boot()`.

### 9.2 Controllers

- Extend `App\Http\Controllers\Api\BaseController`, inject the service interface, call `parent::__construct()`:
  ```php
  public function __construct(private UomServiceInterface $service)
  {
      parent::__construct();
      $this->middleware('permission:uom_read', ['only' => ['index', 'show']]);
  }
  ```
  `BaseController` provides `$this->per_page` (`(int) ?per_page`, 15 when ≤ 0, **no upper cap**) and `createdResponse()`, `updatedResponse()`, `deletedResponse()`, `errorResponse($msg, $data, $code = 500)`. The root `App\Http\Controllers\Controller` only carries the framework traits.
- Controllers stay thin: validate (Form Request), call the service, wrap the result in a Resource / set the status code. No `QueryBuilder::for`, `DB::`, `Model::create/where` in controllers (exceptions: legacy `OrderDetailController`, `AuthController::register`, route-less `SocialiteController`).
- Services return the model / paginator / collection unchanged (never `fresh()`, so `wasRecentlyCreated` still gives the implicit 201). Services may return PDF/XML/Excel responses. Business-rule errors are thrown as `ApiResponseException` (never `abort()` — debug traces would change the JSON). Inside a transaction, keep the original return-vs-throw semantics.
- Index endpoints: `$this->service->findAllPaginate($this->per_page, fn ($q) => $q->tenanted(), allowedFilters: [...], allowedIncludes: [...], allowedSorts: [...])` → `XResource::collection(...)`. Any chain that differs (`->through()`, `withCount`, raw `response()->json($paginator)`, different `allowed*` order) gets a named repository method copying it verbatim. Date range via `AllowedFilter::scope('start_date')` (`FilterStartEndDate`).
- Responses: API Resources (`app/Http/Resources/`, generic passthrough `DefaultResource`) or `response()->json(...)`. Business-rule errors: `response()->json(['message' => '...'], 400)` in Indonesian.
- Routes: `apiResource` for CRUD, nested `{parent}/details` groups, custom actions as separate routes (`done`, `verify`, `verification`, `attach`, `print`, `export-xml`, `restore`, `force-delete`). Custom routes are declared **before** the `apiResource` line when they share a prefix.
- Exceptions (`withExceptions()` in `bootstrap/app.php`, JSON requests): `ModelNotFoundException` → 404 "Data tidak ditemukan!", 403 → `{"message": ...}`, 401 "Unauthenticated.", validation → default 422.

### 9.3 Request Validation

- Form Requests in `app/Http/Requests/Api/{Module}/` with generic names: `StoreRequest`, `UpdateRequest`, plus action names (`SalesOrder/MergeRequest`, `SalesOrder/ItemStoreRequest`, `StockTransfer/TransferRequest`). When a file imports two modules' `StoreRequest`, alias them (`use ...\SalesOrder\StoreRequest as SalesOrderStoreRequest`). A few legacy flat files remain (`ImportByFileRequest`, `UomUpdateRequest`, `UserDiscountUpdateRequest`). Loyalty: `Loyalty/Loyalty*Request`, `Loyalty/Admin/{Verb}{Noun}Request`.
- `authorize()` returns `true` or is omitted — authorization is the controller middleware's job.
- Booleans: `use RequestToBoolean` (`app/Traits/Requests/RequestToBoolean.php`) and normalize in `prepareForValidation()` with `$this->merge([... => $this->toBoolean(...)])`.
- Custom rules in `app/Rules/`: `TenantedRule`, `UniqueInvoiceNumber` (ignores soft-deleted, supports ignore id), `PhoneRule`, `StringRule`. Enum validation via `App\Rules\EnumValue`.

### 9.4 Events / Listeners

```php
class VerifiedROEvent { public function __construct(public ReceiveOrder $receiveOrder) { /* also captures auth user */ } }
class CreateStockROListener implements ShouldQueue { ... }
```
Queued listeners run without an HTTP request — don't rely on `auth()` / `request()` inside them; capture what you need in the event.

### 9.5 Services & Repositories

| Base | Notes (deliberate differences from HRIS — keep them) |
|------|------------------------------------------------------|
| `BaseRepository::query()` | `newQuery()` — tenancy is **explicit** (`tenanted()`, `findTenanted()`, or a closure), never automatic |
| `findAllPaginate()` | plain `paginate()` — **no** `withQueryString()` (would change pagination links) |
| `findByIdOrFail()` | `findOrFail` → `ModelNotFoundException` → 404 "Data tidak ditemukan!" |
| `update(Model $model, array $data)` | updates the **instance** so model hooks fire |
| `delete/forceDelete/restore(Model $model)` | act on the instance the caller already has (`CustomSoftDeletes` hooks) |

Services are stateless singletons: never read `request()`/`auth()` in a constructor (read them inside the method). Parameters of service/repository methods that can receive request input are typed `mixed` on purpose, and `findById`/`findByIdOrFail`/`findTenanted` return `mixed`: the old controllers were untyped, so loose input (`null`, `status[]=x`, `"abc"`) must keep behaving as before instead of turning into a `TypeError` 500. Tighten types only together with request validation, as a deliberate behaviour change (`tests/Feature/Api/LooseInputTest` pins the current behaviour). Methods ported from the old static services keep their original signatures. Cross-module calls go through interfaces — break cycles by moving shared pieces down (e.g. `countFulfilledQty` lives on `SalesOrderRepository`, IIPE numbers on `InvoiceNumberService`). Every stock change is written through `StockHistoryRepositoryInterface::record($subject, [...])`.

Notable services (`app/Http/Services/`):

| Service | Purpose |
|---------|---------|
| `SalesOrder\SalesOrderService` | `storeOrder`/`createOrder`/`updateOrder` pipelines, `index`/`show`, `verifyStockItem`, `releaseScannedStock`, `print`, `exportXml`, marketplace merge, product-unit pickers |
| `Invoice\InvoiceService`, `Invoice\InvoiceNumberService` | IIPE invoices; `getSoNumber`/`getDefaultInvoiceNo` |
| `Order\OrderService` | SPG quotations + convert-to-SO |
| `DeliveryOrder\DeliveryOrderService` | DO CRUD, marketplace DO creation, `attachDetails`, verification, done/return |
| `DocumentCounter\NumberingService` | `enabled()`, `next(CompanyEnum, DocumentType)`, `format()` |
| `Stock\StockService`, `StockGroupingService`, `StockRepackService`, `StockTransfer\StockTransferService` | Stock actions |
| `Permission\PermissionService` | Permission IDs → names incl. related permissions |
| `Setting\SettingService` | Settings + `bankTransferInfo()` |
| `Loyalty\...` | Claims, redemptions, prizes, points (`PointsTransactionRepository::balanceFor`) |

---------|---------|
| `SalesOrderService` | `createOrder`/`updateOrder` pipelines, `index`/`show`, `verifyStockItem`, `releaseScannedStock`, `countFulfilledQty`, `print`, `exportXml`, `getSoNumber` (IIPE), marketplace merge |
| `DeliveryOrderService` | Marketplace DO creation, `attachDetails`, locking lines with scanned stock, DO delete with items |
| `NumberingService` | `enabled()`, `next(CompanyEnum, DocumentType)`, `format()` |
| `PermissionService` | Permission IDs → names incl. related permissions |
| `SettingService` | `bankTransferInfo()` |

---

## 10. Key File Locations

### 10.1 Models

`app/Models/` (warehouse) and `app/Models/Loyalty/` (LoyaltyUser, Claim, ClaimLineItem, ClaimPhoto, PointsTransaction, Prize, PrizeCategory, Redemption).

### 10.2 Controllers

| Folder | Contents |
|--------|----------|
| `app/Http/Controllers/Api/` | Warehouse controllers |
| `Api/Admin/` | `ProductUnitPointsController` |
| `Api/Admin/Loyalty/` | Loyalty admin: `ClaimReview`, `RedemptionReview`, `PrizeManagement`, `PrizeCategoryManagement`, `CustomerManagement`, `BrandManagement`, `ProductUnitSearch`, `Me` |
| `Api/Loyalty/`, `Api/Loyalty/Auth/` | Loyalty customer endpoints (several single-action `__invoke`) |
| `Api/Public/` | `StockVerificationController` |

Notable action owners:
- `SalesOrderController` — CRUD, `merge`, `productUnits`/`productUnitsNew`, `items`, `verification`, `print`, `exportXml`
- `DeliveryOrderController` — CRUD, `attach`, `verification`, `done`, `return`, `print`, `exportXml`, `getDoBySoDetail`
- `ReceiveOrderController` (`done`), `ReceiveOrderDetailController` (`verify`)
- `StockController` — 15+ stock actions; `StockTransferController` — `transfer`
- `StockOpnameController` (`done`, `setDone`), `StockOpnameDetailController` (`scan`)
- `AdjustmentRequestController` (`approve`), `InvoiceController`, `OrderController` (`convertSalesOrder`), `PaymentController` (`restore`, `forceDelete`), `DocumentCounterController`

### 10.3 Pipes

`app/Pipes/Order/`: `FillOrderAttributes`, `FillOrderRecords`, `NormalizeLockedOrderItems`, `MakeOrderDetails`, `CalculateAutoDiscount`, `CalculateVoucher`, `CalculateAdditionalDiscount`, `CalculateAdditionalFees`, `CheckExpectedOrderPrice`, `SaveOrder`, `UpdateOrder`. `app/Pipes/Order/Spg/`: `SaveOrder`, `ConvertToSO`.

### 10.4 Events & Listeners (`Event::listen()` in `AppServiceProvider::boot()`)

| Event | Listener | Queued | Trigger |
|-------|----------|--------|---------|
| `VerifiedROEvent` | `CreateStockROListener` | yes | ReceiveOrder `is_done` → true |
| `UnverifiedROEvent` | `DeleteStockROListener` | yes | ReceiveOrder `is_done` → false / deleting |
| `ProductUnitCreated` | `ProductUnits\CreateStockProductUnit` | yes | ProductUnit created (`refer_id` null) |
| `StockOpnameCreated` | `Stocks\CreateStockOpnameDetail` | yes | StockOpname created |
| `StockOpnameDetailCreated` | `Stocks\CreateStockOpnameItems` | yes | StockOpnameDetail created |
| `LoyaltyUserRegistered` | `Loyalty\SendLoyaltyVerificationEmail` | **no** | LoyaltyUser registered |

No observers. Loyalty mails (`app/Mail/Loyalty/`) are sent synchronously.

### 10.5 Other

| What | Where |
|------|-------|
| Traits | `app/Traits/` — `Tenanted`, `CustomSoftDeletes`, `FilterStartEndDate`, `Requests/RequestToBoolean`, `Controllers/DeniesUnlessCan`, `Controllers/ClampsLoyaltyPerPage` |
| Helpers | `app/Helpers/` — `Helper::rupiah()`, `PermissionsHelper`, `ContentDispositionHelper` (export filenames) |
| Rules | `app/Rules/` |
| Jobs | `app/Jobs/GenerateStockQrcode` (creates Stock rows; QR image generation commented out) |
| Exports / Imports | `app/Exports/`, `app/Imports/` (`StockImport`, `VoucherImport`, seeder imports) |
| Loyalty support | `app/Support/Loyalty/LoyaltySignedUrl`, `config/loyalty.php` |
| Console | No custom artisan commands; no schedule (`routes/console.php` only has `inspire`) |

---

## 11. Important Technical Details

### 11.1 QR/ULID System

- `stocks.id` is a ULID — it is the QR payload / serial number. QR images are rendered client-side (S3 QR generation is commented out).
- Grouping via `stocks.parent_id` (see §5.10).

### 11.2 Document Numbering

Gated by `config('app.enable_document_counter')` (`ENABLE_DOCUMENT_COUNTER`, default `false`), read through `app(NumberingServiceInterface::class)->enabled()`. Baked into config cache — run `php artisan config:cache` after changing it.

| Company | SO | DO |
|---------|----|----|
| `pas` | `PAS/SO/MM/YY/NN` | `PAS/DO/MM/YY/NN` |
| `pa` | `SO/PA/MM/YY/NN` (type first) | `DO/PA/MM/YY/NN` |

- Counter row in `document_counters`, scoped per company + document type + period `Y-m` (resets monthly), upsert + `lockForUpdate` inside a transaction; sequence padded to ≥ 2 digits.
- **Flag on**: reserved in `SalesOrder` (only when `!is_invoice`, `invoice_no` empty, `company` set) and `DeliveryOrder` `creating` hooks.
- **Flag off**: `invoice_no` is entered manually and **required** on store (422 when missing); `GET`/`PUT /document-counters` return 404.
- Marketplace merge takes both numbers manually when the flag is off (`invoice_no` + `delivery_order_invoice_no`, must differ) and ignores them when on.
- **IIPE** (`/invoices`, SPG convert-so): `InvoiceNumberService::getSoNumber()` — `FORMAT_INVOICE_NO` (`IIPE-%s/%s/%s/%s/%s` = Y/m/d/NNNN/warehouse code), daily per warehouse, starting at `START_INVOICE_NO`. **No lock** (race-prone).

### 11.3 Stock Tracking Dual Model

| Type | Flag | Tracking |
|------|------|----------|
| QR items | `is_generate_qr = true` | Individual `Stock` rows |
| Non-QR items | `is_generate_qr = false` | `StockProductUnit.qty` |

Every stock change writes a polymorphic `StockHistory` explicitly via `$model->histories()->create([...])` (`model_type`/`model_id` = ReceiveOrderDetail, SalesOrderDetail, StockOpnameDetail, AdjustmentRequest, StockTransfer, StockProductUnit).

### 11.4 File Storage

S3 (`FILESYSTEM_DISK=s3`) for payment media (Spatie Media Library), brand logos, prize photos, and loyalty claim photos (default disk, under `loyalty/claims/{ulid}/`). Tests fake these with `Storage::fake('s3')` / `Storage::fake('local')`.

### 11.5 Price Calculation

1. Base price = sum of FE-submitted line totals (`MakeOrderDetails`)
2. Auto discount (tiers currently empty)
3. Vouchers (`voucher_codes[]`, sequential, NOMINAL or PERCENTAGE, each capped at current price)
4. Additional discount (% or nominal); all discounts floor at 0
5. Shipment fee added last
6. `tax` stored per line (11 or 0 when `is_ppn`) but **not** added to `price` by the pipeline
7. `CheckExpectedOrderPrice` compares against the FE total when provided

---

## 12. Do's and Don'ts

### Do's

- **DO** follow Controller → Service → Repository: new logic goes in a service behind an interface, new queries in a repository, and every new interface is bound in `AppServiceProvider::$singletons`.
- **DO** keep API responses byte-compatible — the BEJO and verify frontends are in production. Pin behaviour with a feature test (`tests/Support/InteractsWithWarehouse`) before refactoring an endpoint.
- **DO** read nearby code for the flow you're touching.
- **DO** query warehouse-scoped data through `tenanted()` / `findTenanted()` and validate IDs with `TenantedRule`.
- **DO** use enum classes instead of hardcoded strings.
- **DO** use `NumberingService` (or `lockForUpdate`) for any sequential number.
- **DO** branch on `is_generate_qr` for every stock operation and write a `StockHistory` row for every stock change.
- **DO** add permissions to `PermissionsHelper::adminPermissions()` and guard new controller actions with `permission:` middleware.
- **DO** create Form Request classes for validation and Spatie Query Builder for index endpoints.
- **DO** use `Schema::hasColumn` / `hasTable` guards in alter migrations.
- **DO** write user-facing messages in Indonesian, matching existing ones.

### Don'ts

- **DON'T** hardcode warehouse IDs or enum string values.
- **DON'T** add static service classes or put `QueryBuilder`/`DB::`/`Model::create` in controllers.
- **DON'T** invent a new stock-mutation path — reuse the existing one for the flow (RO listeners, adjustment approve, DO verification/done, transfer, opname).
- **DON'T** store loyalty points balance — compute from `points_transactions`.
- **DON'T** mix `loyalty_users` and `users`.
- **DON'T** build on `Order`/`OrderDetail` models or the `orders` table.
- **DON'T** run `migrate:fresh` / `db:wipe` against the dev database, or enable lines in `DatabaseSeeder` (intentionally empty — seed with `--class`).
- **DON'T** commit secrets or API keys.
- **DON'T** add comments unless explicitly asked.

---

## 13. Known Code Issues (don't rely on these; fix only when asked)

- `POST api/auth/register` (`AuthController::register`) is unauthenticated and currently soft-deletes every ProductUnit without stock, then `exit()`s.
- `AuthController::token` accepts `config('app.bypass_password')` as a master password.
- `DeleteStockROListener`: decrements `qty` only when `is_generate_qr` is true (inverted), deletes QR dir without the `qrcode/` prefix; the RO `deleting` hook queues an event for a model that is about to disappear.
- `StockTransfer\TransferRequest`: `stock_ids` is `required`, which blocks the non-QR transfer path.
- `DeliveryOrderDetailController` middleware lists `'reset'` but the method is `resetVerifiedStock`; `PaymentController`/`VoucherCategoryController` `['only' => 'destroy', 'forceDelete', 'restore']` protects only `destroy`.
- `getSoNumber()` (IIPE) has no lock.
- `Permission\UpdateRequest` unique rule is missing a comma → `PUT permissions/{id}` answers 500.
- `PUT receive-orders/{id}/details/{detail}` → 500 (request calls `details()` on a route string); `PUT receive-orders/{id}` still contains `dd()`; `PUT receive-orders/{id}/done` with `is_done=false` → 500 (`DeleteStockROListener`).
- `POST stocks/grouping` with `receive_order_detail_id` → 500 before writing.
- `PUT stock-opnames/{id}`, `PUT stock-opnames/{id}/details/{detail}` → 500; deleting an opname that has details → 500 (FK).
- `POST delivery-orders/{id}/return` answers **200** with the exception text when it fails.
- `per_page` is read in the controller constructor; a controller instance is reused within one app instance (matters for tests / Octane).
- `SalesOrderController` has all permission middleware commented out; `SocialiteController` routes are commented out.
- `php8.5 artisan route:list` crashes unless `ENABLE_DOCUMENT_COUNTER=true` (DocumentCounterController aborts in its constructor).
- These are pinned by `test_known_quirk_*` tests — fixing one means updating its test on purpose.
- `Feature/ExampleTest` fails (`/` redirects to the company site).
- Full list of security items: `SECURITY_FINDINGS.md`.

---

## 14. Running the Application

PHP 8.5 is required (`composer.json` needs ≥ 8.4). The dev machine's default `php` is still 8.3, so call `php8.5` (and `php8.5 $(which composer)`) explicitly.

```bash
php8.5 $(which composer) install
cp .env.example .env && php8.5 artisan key:generate
php8.5 artisan migrate
php8.5 artisan db:seed --class=PermissionSeeder        # DatabaseSeeder is intentionally empty
php8.5 artisan db:seed --class=AdminSeeder
php8.5 artisan db:seed --class=LoyaltyPermissionSeeder

php8.5 artisan serve
php8.5 artisan queue:work            # Redis; listeners for RO/opname/product units are queued

php8.5 artisan test                                   # needs MySQL DB `platinum_event_test` (forced in phpunit.xml)
php8.5 artisan test --filter=NumberingTest            # single class
php8.5 artisan test --filter='NumberingTest::test_x'  # single method
php8.5 artisan test tests/Feature/Loyalty             # directory

php8.5 vendor/bin/pint path/to/File.php   # format only files you touched (some files are not Pint-clean)
php8.5 vendor/bin/pint --dirty            # or all uncommitted changes
```

---

## 15. Frontend CORS Allowlist (`config/cors.php`)

Hardcoded: `bejo.platinumadisentosa.com`, `platinum-warehouse.vercel.app`, `platinum-warehouse-beta.vercel.app`, `bejo-platinum-product-verify.vercel.app`, `platinum-verify-frontend.vercel.app`, `verify.platinumadisentosa.com`, `localhost`/`127.0.0.1` on ports 8000 and 3000. Env-driven: `CORS_VERIFY_ORIGIN_1`, `CORS_VERIFY_ORIGIN_2` (e.g. `cek.platinumadisentosa.com`). Exposes `Content-Disposition`.

---

## 16. Existing Documentation

| File | Content |
|------|---------|
| `PRD.md` | Full product requirements (WMS + Loyalty v1) |
| `BACKEND_AUDIT.md` | Technical audit — stack, schema, API surface, QR/serial format, public verify endpoint |
| `LOYALTY_SPEC.md` | Loyalty spec — user stories, data model, state machines, endpoints, fraud rules |
| `SECURITY_FINDINGS.md` | Logged-but-unfixed security findings |
| `docs/tasks/invoices/**` | Invoice QR scan PRD, auto-discount, voucher flow/PRDs (multiple vouchers) |
| `docs/tasks/stock/stock-transfer/**` | Stock transfer FE PRD + backend flow v1–v3 (v3 current) |
| `task.md` | Original project brief (Indonesian) |
| `docs/tasks/architecture/laravel-13-upgrade.md` | Laravel 10 → 13 upgrade ledger (batches, baseline) |
| `docs/documentation/architecture/laravel-13-upgrade.md` | What changed in the upgrade, intended behaviour differences, deploy steps |

---

## 17. Laravel 13 Structure and Compatibility Rules

The app was rebuilt on a fresh Laravel 13 skeleton (Laravel 10 → 13, PHP 8.3 → 8.5). Behaviour was kept identical to Laravel 10; these rules keep it that way.

**Structure.** Everything that used to live in `Http/Kernel`, `Console/Kernel`, `Exceptions/Handler` and the Route/Auth/Event/Broadcast service providers is in `bootstrap/app.php` (`withRouting(using:)` with the `api` prefix and no `/up` route, `withMiddleware()`, `withExceptions()`, `withEvents(discover: false)`) and `AppServiceProvider::boot()` (rate limiters, `Gate::before`, `Event::listen`, paginator / validator compat). Providers: `bootstrap/providers.php` (`AppServiceProvider`, `SchemaServiceProvider`). Don't add those classes back. Controllers still extend `Illuminate\Routing\Controller`, so `$this->middleware()` in constructors keeps working.

**Config.** Laravel 13 defaults that would change behaviour are locked to the Laravel 10 values (mysql, file cache/session, sync queue default, smtp default mailer, underscore cache/redis/cookie prefixes, `session.serialization=php`, `cache.serializable_classes=null`, `local` disk at `storage/app`, `hashing.rehash_on_login=false`, `excel.imports.csv.input_encoding=UTF-8`, `dompdf.enable_remote=true`, Sanctum stateful stack without `AuthenticateSession`). Old env names keep working as fallbacks (`CACHE_DRIVER`, `DATABASE_URL`, `BROADCAST_DRIVER`, `POSTMARK_TOKEN`). Changing any of these is a separate decision.

**Migrations.** Old migrations keep the Laravel 10 DDL: `legacyDouble($column, $total = 8, $places = 2)` (macro in `SchemaServiceProvider`) replaces `float(T,P)` / `unsignedFloat()`. New migrations use `decimal()` for money and the normal Laravel 13 API. The test schema must stay identical to production.

**Response compat layers (don't remove without a frontend change):**
- `App\Pagination\LengthAwarePaginator` drops the `page` key Laravel 12 added to paginator `links`.
- `App\Validation\Validator` keeps the Laravel 10 `image` rule (SVG allowed, no avif/heic/heif).
- `RoleService` / `UserService` call `load('permissions')` / `load('roles')` after `syncPermissions()` / `syncRoles()` — permission 6 no longer loads the relation.
- `App\Models\Permission::bootHasRoles()` keeps the permission 5 delete hook (permissions created through the API have guard `sanctum`, which has no user provider).
- Sales order / delivery order PDFs are returned with the dompdf 2 headers (`attachment; filename="..."`, document numbers contain `/`), not `$pdf->download()`.
- The import sample CSV is sent with an explicit `text/csv` content type.

**Package APIs.**
- query-builder 7: `allowedFilters/Sorts/Includes/Fields(...)` are variadic — pass items, spread dynamic arrays (`...$filters`). `findAllPaginate(..., allowedFilters: [...])` still takes arrays (the repository spreads them).
- Excel 4: concern methods have native return types (`collection(): Enumerable`, `model(array $row): Model|array|null`, `collection(Collection $rows): void`). A `model()` that can skip a row must end with `return null;` — falling off the end of a nullable-typed method is a `TypeError`.
- Carbon 3: `diffIn*()` returns signed floats. For the Carbon 2 result use `(int) $a->diffInDays($b, true)`.
- PHPUnit 12: use attributes (`#[DataProvider('...')]`), not docblock annotations.

**Intended differences from Laravel 10** (documented in `docs/documentation/architecture/laravel-13-upgrade.md`): `charset=utf-8` in lower case, debug-only error texts (`QueryException` now names host/port/database), framework HTML error pages, two new global middleware (`ValidatePathEncoding`, `InvokeDeferredCallbacks`), body input wins over an uploaded file with the same key, PDF metadata (`Producer: dompdf 3`).
