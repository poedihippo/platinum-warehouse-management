# Backend Audit — Platinum Adi Sentosa Warehouse Management

**Scope:** Read-only audit of the existing backend, performed in preparation for a new public-facing customer QR-scan page.
**Method:** Static review of source under `/Users/alba/Documents/platinum-warehouse-management`. No DB queries were executed (no `.env` / live credentials available); all schema/sample-row claims are derived from migration files and code paths. Items that require live DB access are listed in **Open Questions**.

---

## 1. Tech stack & architecture

| Layer | Detail |
|---|---|
| Language / framework | PHP — Laravel 10 (`laravel/framework: ^10.0` in `composer.json`) |
| Runtime | PHP `^8.0.2` |
| Auth library | Laravel Sanctum 3.2 (Bearer personal access tokens) |
| RBAC | `spatie/laravel-permission` 5.10 |
| Query builder | `spatie/laravel-query-builder` 5.2 (filters/sorts/includes from URL) |
| QR library | `simplesoftwareio/simple-qrcode` ~4 (image rendering only — see §4) |
| File storage | AWS S3 (`league/flysystem-aws-s3-v3`); `FILESYSTEM_DISK=s3` per `.env.example` |
| Excel/CSV import-export | `maatwebsite/excel` 3.1 |
| PDF | `barryvdh/laravel-dompdf` 2.0 |
| Media library | `spatie/laravel-medialibrary` 11 |
| DB engine | MySQL (`DB_CONNECTION=mysql` in `.env.example`, port 3306) |
| Cache | file (`CACHE_DRIVER=file`) |
| Sessions | file (`SESSION_DRIVER=file`) |
| Queue | Redis (`QUEUE_CONNECTION=redis`) — used by `CreateStockROListener`, `GenerateStockQrcode` jobs |
| Front-ends (from CORS allowlist) | `bejo.platinumadisentosa.com`, `platinum-warehouse.vercel.app`, `platinum-warehouse-beta.vercel.app`, **`bejo-platinum-product-verify.vercel.app`** |
| Hosting / deployment | **Not determined from repo** — no Dockerfile, no `Procfile`, no `deploy.sh`, no `ecosystem.config.js`, no GitHub Actions. Likely shared host or VPS with Laravel + nginx + queue worker (see Open Questions). |

**Background workers / scheduled jobs:**
- `App\Jobs\GenerateStockQrcode` — `ShouldQueue`, dispatched from `StockController::repack()` (note: dispatched with `dispatchSync` there — the queue version is wired but called synchronously).
- `App\Listeners\CreateStockROListener` — `ShouldQueue`, fires on `VerifiedROEvent` (when a Receive Order is verified, generates `stocks` rows for each unit).
- No `app/Console/Kernel.php` `schedule()` entries reviewed (file exists but not inspected — see Open Questions).

---

## 2. Database — products, QR / serials, expiry

### 2.1 Schema map (from `database/migrations/`)

```
products  (id, product_category_id FK, product_brand_id FK, company enum 'pas'|'pa', name, timestamps, soft_deletes)
   │
   └── product_units  (id, product_id FK, refer_id, uom_id, name, price, refer_qty,
                        code, is_generate_qr bool, is_ppn bool, timestamps, soft_deletes)
           │
           └── stock_product_units  (id, product_unit_id FK, warehouse_id FK,
                                      qty int, timestamps, soft_deletes)
                   │
                   └── stocks  ← per-unit serial / QR row; ULID PK
```

### 2.2 `stocks` table — the per-serial / per-QR record

`database/migrations/2023_05_03_105437_create_stocks_table.php`:

```php
Schema::create('stocks', function (Blueprint $table) {
    $table->ulid('id')->primary();              // ← THIS IS THE QR PAYLOAD / SERIAL
    $table->tinyInteger('printer_id')->unsigned()->nullable();
    $table->ulid('parent_id')->nullable()->constrained('stocks', 'id'); // grouping
    $table->foreignId('stock_product_unit_id')->constrained();           // → product_units / warehouse
    $table->foreignId('adjustment_request_id')->nullable()->index();
    $table->foreignId('receive_order_id')->nullable()->constrained();
    $table->foreignId('receive_order_detail_id')->nullable()->constrained();
    $table->string('description')->nullable();
    $table->text('qr_code')->nullable();        // path to PNG on S3 — currently UNUSED
    $table->integer('scanned_count')->default(0);
    $table->dateTime('scanned_datetime')->nullable();
    $table->boolean('is_tempel')->default(1);
    $table->timestamps();
    $table->softDeletes();
});
```

Plus a later migration (`2025_02_19_114922_add_print_status_to_stocks_table.php`):

```php
$table->timestamp('printed_at')->nullable()->after('id');
$table->boolean('in_printing_queue')->default(0)->after('printed_at');
```

### 2.3 Expiry — where does it live?

**On the `stocks` row, in a column called `expired_date`.** Per-serial, NOT per-product or per-batch.

Code references (read/write):

| File | Line | Purpose |
|---|---|---|
| `app/Http/Controllers/Api/StockController.php` | 217–230 | `groupingByScan` writes `expired_date` to parent + child stock rows |
| `app/Http/Controllers/Api/StockController.php` | 340–342 | `grouping` writes `expired_date` to child stocks |
| `app/Http/Controllers/Api/StockController.php` | 566–568 | `setToPrintingQueue` writes `expired_date` |
| `app/Http/Controllers/Api/AdjustmentRequestController.php` | 106 | propagates `expired_date` from adjustment to created stocks |
| `app/Http/Controllers/Api/ProductUnitController.php` | 212, 224 | reads `expired_date` from stock and tacks it onto product unit response |
| `app/Http/Requests/Api/AdjustmentRequestStoreRequest.php` | 31 | `'expired_date' => 'required|date'` |
| `app/Http/Requests/Api/Stock/GroupingByScanRequest.php` | 21 | nullable date |
| `app/Http/Requests/Api/Stock/GroupingRequest.php` | 17 | nullable date |
| `app/Http/Requests/Api/Stock/SetToPrintingQueueRequest.php` | 17 | nullable date |

> ⚠️ **No migration in `database/migrations/` adds the `expired_date` column** — `grep -rn "expired_date" database/` returns zero hits. The column was added directly to the production DB out-of-band. The application code assumes it exists; if you `migrate:fresh` on a clean DB, every code path that writes `expired_date` will throw `SQLSTATE[42S22]: Column not found`. Flagged in Open Questions.

### 2.4 QR / serial format

- The serial = `stocks.id` = a **ULID** (e.g. `01HF6Y9R2X9Q7K5W3V1A8C0M2D`, 26 chars, base32 Crockford). Generated by Laravel `HasUlids` trait on the `Stock` model.
- The `qr_code` column stores an S3 path string for a rendered PNG, but **all writes to it are commented out** in current code (see §4). In production it is almost certainly always `NULL`. The `qrCode` accessor on `Stock` (`app/Models/Stock.php:76-87`) hard-returns `null`.

### 2.5 Uniqueness / indexing

- `stocks.id` (ULID) is `PRIMARY KEY` → unique + indexed.
- `stocks.adjustment_request_id` has an explicit `->index()`.
- All FKs (`stock_product_unit_id`, `receive_order_id`, `receive_order_detail_id`, `parent_id`) get implicit indexes from `constrained()`.
- No unique constraint on `qr_code` (which is fine — it's effectively unused).

### 2.6 Sample row counts and example rows

**Not retrieved** — read-only audit, no live DB connection. See Open Questions §7. From code patterns, expected order of magnitude per row in `stocks` is one per physical unit received-and-tracked, so likely tens-to-hundreds-of-thousands.

A fabricated example of what a row should look like, based on schema:

```json
// stocks
{
  "id": "01HF6Y9R2X9Q7K5W3V1A8C0M2D",
  "printer_id": null,
  "parent_id": null,
  "stock_product_unit_id": 1234,
  "receive_order_id": 412,
  "receive_order_detail_id": 1903,
  "description": null,
  "qr_code": null,
  "scanned_count": 2,
  "scanned_datetime": "2026-03-14 09:22:11",
  "is_tempel": true,
  "printed_at": "2026-03-10 11:00:00",
  "in_printing_queue": false,
  "is_stock": true,
  "expired_date": "2027-08-31",          // out-of-band column — production-only
  "created_at": "2026-03-09 14:55:02",
  "updated_at": "2026-03-14 09:22:11",
  "deleted_at": null
}
```

---

## 3. API surface

Two route files: `routes/api.php` (mounted at `/api`, Sanctum-guarded except where noted) and `routes/web.php` (mounted at `/`, web middleware: cookies, session, CSRF for non-GET).

### 3.1 Public (no auth) endpoints

| Method | Path | Controller | Notes |
|---|---|---|---|
| GET | `/` | inline | 302-redirects to `https://platinumadisentosa.com/` |
| GET | `/clear-config` | inline | **DANGER — production exposure**: runs `artisan optimize:clear`, `config:cache`, etc. without any auth |
| GET | `/migrate` | inline | **DANGER**: runs `artisan migrate` |
| GET | `/migrate-fresh` | inline | **DANGER**: runs `artisan migrate:fresh --seed` (will wipe data) |
| GET | `/test` | inline | Generates a sample QR PNG (gradient demo) |
| GET | `/invoices/{salesOrder}/print` | `InvoiceController@print` | Public invoice print |
| **GET** | **`/product-units/{stock}`** | **`ProductUnitController@showProductUnitByStock`** | **The public-ish stock-lookup endpoint — see §3.4** |
| GET | `/api/phpinfo` | `TestController@phpinfo` | **DANGER**: exposes server config |
| GET | `/api/test` | `TestController@index` | Sample QR image |
| GET | `/api/stocks/export` | `StockController@export` | Excel export — public! |
| POST | `/api/stocks/import` | `StockController@import` | **DANGER**: public Excel import |
| POST | `/api/auth/token` | `AuthController@token` | Login. Throttled `30/min` |
| POST | `/api/auth/register` | `AuthController@register` | Has `die('mantullll')` short-circuit + odd cleanup logic — effectively dead/dangerous |
| GET | `/api/auth/{provider}` | `SocialiteController@redirectToProvider` | OAuth (Google/Facebook) redirect |
| GET | `/api/auth/{provider}/callback` | `SocialiteController@handleProvideCallback` | OAuth callback |

### 3.2 Sanctum-guarded API (`auth:sanctum` middleware in `routes/api.php:64-233`)

Bearer token auth. Permissions checked per-controller via `spatie/laravel-permission` `permission:*` middleware. RESTful `apiResource` style.

```
GET|POST|PUT|DELETE  /api/roles, /api/permissions, /api/users[/me|/restore|/force-delete], /api/users/{user}/discounts
GET|POST|PUT|DELETE  /api/warehouses, /api/suppliers
GET|POST|PUT|DELETE  /api/product-categories, /api/product-brands
GET|POST|PUT|DELETE  /api/products[/sample-import|/import]
GET|POST|PUT|DELETE  /api/product-units[/sample-import|/import|/{id}/{create|update}-relations|/change-product|/user-price/{user}]
GET|POST|PUT|DELETE  /api/product-unit-blacklists  (index/store/destroy only)
GET|POST|PUT|DELETE  /api/uoms

GET|POST|PUT|DELETE  /api/receive-orders[/{id}/done][/{id}/details[/{id}[/verify]]]
GET|POST|PUT|DELETE  /api/sales-orders[/product-units|/product-units-new|/{id}/export-xml|/{id}/print][/details/...]
GET|POST|PUT|DELETE  /api/invoices[/export|/get-invoice-no|/{id}/export-xml|/{id}/bill]
GET|POST|DELETE      /api/sales-order-items/{detail}
GET|POST|PUT|DELETE  /api/delivery-orders[/get-by-so-detail/{id}|/{id}/return|/{id}/attach|/{id}/verification/{detailId}|/{id}/print|/{id}/done|/{id}/export-xml][/details/...]
GET|POST|PUT|DELETE  /api/adjustment-requests[/{id}/approve]

GET    /api/stocks/details
GET    /api/stocks/print-all
PUT    /api/stocks/verification-tempel
POST   /api/stocks/add-to-stock
POST   /api/stocks/set-to-printed
POST   /api/stocks/set-to-printing-queue
POST   /api/stocks/print-verification
POST   /api/stocks/record
POST   /api/stocks/export-txt
POST   /api/stocks/grouping
POST   /api/stocks/grouping-by-scan
POST   /api/stocks/{id}/ungrouping
POST   /api/stocks/{id}/repack
DELETE /api/stocks/bulk
GET|POST|PUT|DELETE  /api/stocks   (apiResource)

GET|POST|PUT|DELETE  /api/stock-opnames[/{id}/done|/{id}/set-done][/details/...]
GET                  /api/stock-histories[/export]
GET|PUT              /api/settings  (index/update only)
GET|POST|PUT|DELETE  /api/payments[/{id}/restore|/force-delete]
GET|POST|PUT|DELETE  /api/voucher-categories[/{id}/restore|/force-delete]
GET|POST|PUT|DELETE  /api/vouchers[/import][/{id}/restore|/force-delete][/generate-batches]
GET                  /api/exports/sample/{type}
GET|POST|PUT|DELETE  /api/orders[/{id}/convert-so][/details/...]
GET|POST             /api/temporary-stocks
```

### 3.3 Auth model

- **Sanctum personal access tokens** stored in `personal_access_tokens` (with a custom `plain_text_token` column on `App\Models\PersonalAccessToken`, used in `AuthController@token:33,60`).
- `POST /api/auth/token` accepts email+password, returns the latest existing token or creates a new one named `'default'`.
- A backdoor: if `password === env('ROOT_PASSWORD')`, validation passes regardless of stored hash (`AuthController.php:37`). `ROOT_PASSWORD=alba123` is in `.env.example` — should be rotated and the backdoor removed.
- API throttle: global `throttle:api` group middleware (Laravel default `60/min` per token/IP). Auth/token endpoint: `throttle:30,1`.

### 3.4 Existing serial-lookup endpoint (the one that matters for the new feature)

**`GET /product-units/{stock}` → `App\Http\Controllers\Api\ProductUnitController@showProductUnitByStock`**

Defined in `routes/web.php:54`. Despite the controller namespace, it is registered in `web.php` (so it's served on the bare domain, not `/api/...`), and it has **no auth middleware** (no `auth:sanctum`, no permission checks).

Code (`app/Http/Controllers/Api/ProductUnitController.php:210-227`):

```php
public function showProductUnitByStock(string $stockId)
{
    $stock = Stock::select('id', 'stock_product_unit_id', 'expired_date')->find($stockId);
    if (!$stock) return response()->json(['message' => 'Product tidak ditemukan.'], 400);

    $productUnit = ProductUnit::select('id', 'product_id', 'uom_id', 'name', 'price', 'code')
        ->with([
            'uom' => fn($q) => $q->select('id', 'name'),
            'product' => fn($q) => $q->select('id', 'product_category_id', 'product_brand_id', 'name', 'article_url')
                ->with('productCategory', fn($q) => $q->select('id', 'name'))
                ->with('productBrand', fn($q) => $q->select('id', 'name')),
        ])
        ->whereHas('stockProductUnit', fn($q) => $q->where('id', $stock->stock_product_unit_id))
        ->first();

    if (!$productUnit) return response()->json(['message' => 'Product tidak ditemukan.'], 400);
    $productUnit->expired_date = $stock->expired_date;

    return new DefaultResource($productUnit);
}
```

Request:

```
GET /product-units/01HF6Y9R2X9Q7K5W3V1A8C0M2D
```

Response (200):

```json
{
  "data": {
    "id": 1234,
    "product_id": 88,
    "uom_id": 2,
    "name": "Granit Platinum 60x60 Glossy",
    "price": 175000,
    "code": "GP-60-GL",
    "expired_date": "2027-08-31",
    "uom": { "id": 2, "name": "PCS" },
    "product": {
      "id": 88,
      "product_category_id": 5,
      "product_brand_id": 3,
      "name": "Granit Platinum 60x60",
      "article_url": "https://platinumadisentosa.com/products/granit-60",
      "product_category": { "id": 5, "name": "Granit" },
      "product_brand": { "id": 3, "name": "Platinum" }
    }
  }
}
```

Error responses (both **HTTP 400**, not 404 — note the unusual choice):

```json
{ "message": "Product tidak ditemukan." }
```

**Behavioral notes / caveats:**
- Uses `web` middleware group: starts a session, sets a cookie, runs CSRF (CSRF is a no-op on GET — fine).
- No CORS — `config/cors.php` only applies CORS headers to `paths: ['api/*', 'sanctum/csrf-cookie']`. Calling this endpoint cross-origin from a browser **will fail** the preflight unless the request comes from the same origin.
- Returns 400 (not 404) on missing stock — slightly off but workable.
- Does **not** include the serial itself in the payload. The caller already knows it (it's the URL path), but worth surfacing on a customer page.
- Returns `expired_date` from the stock row directly — `null` if not set, ISO date string otherwise.
- Returns `price` to anonymous callers — possibly an information-disclosure concern for a public scan page.

### 3.5 Public exposure surface

CORS allowlist (`config/cors.php`) already includes `https://bejo-platinum-product-verify.vercel.app` — strong signal a verify front-end is in development. But CORS only applies to `/api/*`, not to the existing `/product-units/{stock}` route on the web side.

---

## 4. QR code format

**What's encoded:** the bare `stocks.id` ULID string, e.g. `01HF6Y9R2X9Q7K5W3V1A8C0M2D`. No URL prefix, no JSON, no signature, no checksum.

Evidence:
- `app/Http/Controllers/Api/TestController.php:12` — `QrCode::...->generate('5gs0peom2635dy781ka0peorux009384')` (sample/test).
- All real generation calls (in `StockController@grouping`, `Listeners/CreateStockROListener`, `Jobs/GenerateStockQrcode`, `Imports/StockImport`, `AdjustmentRequestController`) follow the same pattern: `QrCode::...->generate($stock->id)` — and **all of them are currently commented out**.

  ```php
  // app/Listeners/CreateStockROListener.php:51-60 (commented out)
  // $data = QrCode::size(350)
  //     ->format('png')
  //     ->generate($stock->id);
  // $fileName = $receiveOrderDetail->id . '/' . $stock->id . '.png';
  // $fullPath = $folder . $fileName;
  // Storage::put($fullPath, $data);
  // $stock->update(['qr_code' => $fullPath]);
  ```

**Implication:** The PNG-on-S3 generation path is currently disabled. Either (a) QR images are rendered client-side in the printing UI from the ULID, or (b) printing is done from an external printer that consumes the ULID directly. Either way, what gets printed and stuck on a physical unit **is just the ULID string**, encoded as a Code-128 / QR with no envelope.

**Tamper protection:** None. ULIDs are partially time-ordered (timestamp prefix) but otherwise random — not enumerable in practice (2^80 randomness in the last 16 chars), but if an attacker observes any valid serial they can request the API for it without challenge.

**Trigger points where a `stocks` row (and thus a serial/QR) is created:**
1. `Listeners\CreateStockROListener::handle()` — on `VerifiedROEvent`, one row per `adjust_qty` of a Receive Order detail.
2. `Jobs\GenerateStockQrcode::handle()` — bulk creation, dispatched (sync) from `StockController::repack`.
3. `StockController::grouping`, `groupingByScan` — creates "parent" group stocks.
4. `AdjustmentRequestController::approve` (line 106 area) — propagates expiry to newly created stocks.
5. `Imports\StockImport` — bulk Excel import.

---

## 5. Operational status

| Signal | Finding |
|---|---|
| Last commit | `b9fcd61` — `[FIX] SO DO import` — 2026-04-23 (2 days before this audit). Active development. |
| Recent commits | All last ~20 commits are bug fixes around Sales Order / Delivery Order export-xml, return flow, stock import. No QR or product-lookup churn. |
| Logs | `storage/logs/` only contains `.gitignore` (logs are gitignored) — cannot inspect from repo. |
| Healthcheck endpoint | None defined. |
| Open TODOs/FIXMEs in QR/product code | None (`grep TODO|FIXME` in app/ returns nothing relevant). |
| Dead/dangerous code | `AuthController@register` has `die('mantullll')` after running a soft-delete sweep; `web.php` exposes `/migrate`, `/migrate-fresh`, `/clear-config`, `/api/phpinfo` with no auth; `ROOT_PASSWORD` backdoor in `AuthController@token`. |
| Traffic estimate | Not derivable from repo. |

---

## 6. Gaps for the new public scan page

**Goal recap:** subdomain off `platinumadisentosa.com`, no login, scans a QR (which encodes a ULID), shows: product name, expired date (blank if none), serial (the ULID itself).

### 6.1 Can we reuse `GET /product-units/{stock}`?

Functionally **yes** — it returns name + expired_date already, and the caller knows the serial from the scanned URL. But before reusing it, three things to address:

| Issue | Severity | Recommendation |
|---|---|---|
| Endpoint is on the `web` middleware group, not under `/api/*`, so the existing CORS allowlist does not apply | Medium | Add a parallel `/api/public/product-units/{stock}` route under the api group (so CORS works for the Vercel verify front-end), or extend CORS `paths` to include the web route's path. |
| Returns **`price`** to anonymous callers | Medium — info disclosure | New endpoint should return only `{ serial, product_name, expired_date }` (and optionally brand/category if marketing-useful). Strip price, code, uom internals. |
| 400 on not-found instead of 404 | Low | New endpoint should return 404. |
| No rate limiting beyond the global `throttle:api` (60/min/IP) | Medium | For a public unauthenticated lookup, tighten to e.g. `throttle:30,1` per IP and add a captcha on too-many-misses if scraping is a concern. |
| Tenanted scoping | Low — the method does NOT call `tenanted()`, so a missing auth user is not a problem here | Keep it that way; the public lookup must work without auth. |
| `expired_date` column source-of-truth | High — see §2.3, no migration | Before exposing this publicly, write a migration to formalize the column (`Schema::table('stocks', fn... )`) so a fresh-deploy environment doesn't break. |

### 6.2 Recommended minimal changes

1. **Add a migration** to formalize `stocks.expired_date date NULL` (idempotent — check `Schema::hasColumn` first so it's safe against the prod DB that already has it).
2. **Add a new route** in `routes/api.php`, outside the `auth:sanctum` group:
   ```php
   Route::get('public/stocks/{stock}', [PublicStockLookupController::class, 'show'])
       ->middleware('throttle:30,1');
   ```
   Mounted at `/api/public/stocks/{ulid}`. This puts it under existing CORS rules.
3. **Add a slim controller** that returns only `{ serial, product_name, expired_date }`, returns 404 (not 400) when missing, and respects soft-deletes.
4. **Add the new front-end origin** (e.g. `https://verify.platinumadisentosa.com` or the Vercel preview) to `config/cors.php` `allowed_origins`. The Vercel verify domain is already listed.
5. (Optional but recommended) Have the QR encode a full URL: `https://verify.platinumadisentosa.com/{ulid}` instead of just the ULID. Users scanning with a phone camera then go directly to the page. Existing already-printed QRs can't be changed retroactively, but new ones can; the lookup endpoint can accept either form.
6. (Optional) **Remove the dangerous public web routes** (`/migrate`, `/migrate-fresh`, `/clear-config`, `/api/phpinfo`) before adding more public surface — they make the host much more attackable once a public scan domain is announced.

### 6.3 Security concerns specific to public exposure

| Concern | Mitigation |
|---|---|
| **Enumeration / scraping** — once any ULID is observed, attackers can scrape the public endpoint to harvest your product catalog with serial coverage | Per-IP rate limit + return only the minimum fields (no price, no warehouse). ULIDs are not enumerable from scratch (random 80-bit suffix), so attackers must observe real codes — keep it that way. |
| **Counterfeit detection** — the QR contains no signature, so a counterfeiter can mint a fake QR encoding an arbitrary ULID and have the page show "not found" (which a customer may not notice) | Consider HMAC-signing the URL (`?sig=...`) and verifying server-side. Out of scope for v1 but worth roadmapping. |
| **Duplicate scans / re-use** — every `record` call increments `scanned_count`, but the public page should not call `record` (it's a write op gated by `permission:stock_*`) | Don't call `/api/stocks/record` from the public page. |
| **Information disclosure via existing route** — `GET /product-units/{stock}` already leaks price publicly today | Either lock it down (add auth) or replace with the new minimal public endpoint and remove the old web route. |
| `/api/phpinfo`, `/migrate*`, `/clear-config` | Delete or gate behind `signed` URLs / IP allowlist before public launch. |
| `ROOT_PASSWORD` backdoor in auth | Remove. Rotate any production root password. |

---

## 7. Open Questions

These could not be resolved from a static, read-only inspection of the repo:

1. **`stocks.expired_date` schema** — is it actually present in the production MySQL schema? The code reads/writes it but no migration creates it. Run on prod (read-only):
   ```sql
   SHOW COLUMNS FROM stocks LIKE 'expired_date';
   ```
2. **Row counts** — needed for capacity planning of the public endpoint:
   ```sql
   SELECT COUNT(*) FROM products;
   SELECT COUNT(*) FROM product_units;
   SELECT COUNT(*) FROM stock_product_units;
   SELECT COUNT(*) FROM stocks;
   SELECT COUNT(*) FROM stocks WHERE expired_date IS NOT NULL;
   ```
3. **Sample rows** — three rows from `products`, `product_units`, `stocks` with sensitive fields redacted. Couldn't pull without DB access.
4. **Hosting / deployment topology** — no Dockerfile, no CI config, no PM2/systemd files in the repo. Where does this run? How is the queue worker started? Is there a load balancer?
5. **Scheduled tasks** — `app/Console/Kernel.php` was not opened in this audit; verify whether anything is scheduled (cleanup jobs, expiry notifications, etc.).
6. **Recent error log volume** — `storage/logs/laravel.log` would tell us if anything is currently throwing. Tail it on prod.
7. **Where is QR rendering actually happening today?** All server-side QR generation is commented out, yet the system is in active use. Confirm whether printing UI renders QR client-side (likely) or whether there's an external print service consuming ULIDs.
8. **Are physical labels already deployed in the field with bare ULIDs as the QR payload?** Affects whether new QRs can switch to a URL-encoding format.
9. **Is `bejo-platinum-product-verify.vercel.app` already shipped?** It's in CORS but no backend code references it specifically — possibly a parallel effort already in progress that this audit should be aligned with.

---

## Step 0 Confirmation

Read-only queries run against the production MySQL database on 2026-04-26. Nothing here contradicted the static audit; the new endpoint can proceed on the assumed join path.

### Q1 — `SHOW COLUMNS FROM stocks LIKE 'expired_date'`

| Field | Type | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `expired_date` | `date` | YES | (empty) | `NULL` | (empty) |

Column exists in prod as `DATE NULL` with no default. The defensive migration (Step 1) is still correct — `Schema::hasColumn` will be `true` and the migration becomes a no-op against prod, while still creating the column on a fresh environment.

### Q2 — Stock row counts

| `stocks_total` | `with_expiry` |
|---|---|
| 263,521 | 202,129 |

~77 % of stock rows have an `expired_date`; ~23 % are `NULL`. The public endpoint must return JSON `null` (not omitted, not `""`) for those.

### Q3 — Product count

99 rows in `products`. Small catalog.

### Q4 — Sample rows confirming join path

| `serial` (`stocks.id`) | `expired_date` | `stock_product_unit_id` | `product_unit_id` | `product_id` | `product_name` |
|---|---|---|---|---|---|
| `01kq217ybqkyx86092nkyf45p6` | `2028-06-01` | 1075 | 359 | 66 | Mizuho Wheatgerm |
| `01kq217pfqp6by80js6qz4bjtz` | `2028-06-01` | 1075 | 359 | 66 | Mizuho Wheatgerm |
| `01kq217fagzxncggvp9rt0t7eg` | `2028-06-01` | 1075 | 359 | 66 | Mizuho Wheatgerm |

Confirmed three-join lookup path:

```
stocks.stock_product_unit_id
  → stock_product_units.id
stock_product_units.product_unit_id
  → product_units.id
product_units.product_id
  → products.id
products.name  ← exposed publicly as `product_name`
```

`stocks.id` is a 26-char ULID (Crockford base32). The frontend's `^[0-9A-HJKMNP-TV-Z]{26}$` (case-insensitive) regex is the right shape.
`stocks.deleted_at` exists; soft deletes are real. A scan for a soft-deleted row must return the same 404 shape as a not-found row.

### Implications for Step 2

- The controller resolves `serial → product name` in **one** SQL via Eloquent eager loading (`stockProductUnit.productUnit.product`), not four separate queries.
- FK indexes on `stocks.stock_product_unit_id`, `stock_product_units.product_unit_id`, `product_units.product_id` are assumed (Laravel's `constrained()` creates them). Not blocking; verify with `SHOW INDEX FROM stocks` if 263k-row P50 latency on this endpoint becomes a concern.
- `expired_date` lives on `stocks` only. `null` is a real, frequent value (~23 %).
- Soft-deleted stocks → 404 (uniform shape, no leaking the difference).

---

## Public Verification Endpoint

Public, unauthenticated endpoint added for the customer-facing scan page at `cek.platinumadisentosa.com`. Lookup by ULID (the QR payload), returns the bare minimum needed to render a verification page.

### Route

```
GET /api/public/stocks/{ulid}
```

- File: `routes/api.php`
- Controller: `App\Http\Controllers\Api\Public\StockVerificationController@show`
- Resource: `App\Http\Resources\StockVerificationResource`
- Middleware: `throttle:30,1` (30 requests per minute per IP, on top of the global `throttle:api`)
- Auth: **none** — public
- CORS: covered by the existing `paths: ['api/*']` rule. Allowed origins now include `env('CORS_VERIFY_ORIGIN_1')` (default `https://cek.platinumadisentosa.com`) and `env('CORS_VERIFY_ORIGIN_2')`.

### Request

```
GET /api/public/stocks/01HF6Y9R2X9Q7K5W3V1A8C0M2D
Accept: application/json
```

`{ulid}` must match `^[0-9A-HJKMNP-TV-Z]{26}$` (Crockford base32, 26 chars, case-insensitive). Anything else is rejected with the same 404 used for "not found" — see "All failures look the same" below.

### Response — found (200)

```json
{
  "verified": true,
  "data": {
    "serial_number": "01HF6Y9R2X9Q7K5W3V1A8C0M2D",
    "product_name": "Champion Dog Food 5kg",
    "expired_date": "2026-12-31"
  }
}
```

- `expired_date` is a `YYYY-MM-DD` string when set, JSON `null` otherwise (~23 % of stocks have no expiry — the frontend must handle null).
- Date only — no time component, no timezone.
- The resource (`StockVerificationResource`) is an explicit allowlist; new fields cannot leak in by accident.

### Response — not found / invalid (404)

```json
{ "verified": false, "message": "Product not found" }
```

### Response — rate limited (429)

Default Laravel throttle response. Includes `Retry-After` header.

### Design note: all failures look the same

The endpoint deliberately returns the **same 404 body** for:

1. ULID format invalid (e.g. `abc`, SQL injection probe, lowercase `i`/`l`/`o`/`u`)
2. ULID well-formed but no matching row in `stocks`
3. Stock row exists but is soft-deleted (`deleted_at IS NOT NULL`)
4. Stock row exists but its `stock_product_unit → product_unit → product` chain is broken (missing parent row)

Differentiating these cases helps attackers confirm "this serial format is valid" or "this serial used to exist," which makes catalog scraping easier. Future contributors: **do not** add detail-leaking error messages here. If you need richer errors for the admin UI, build a separate authenticated endpoint.

### Migration

`database/migrations/2026_04_26_120000_add_expired_date_to_stocks_table.php` — guards with `Schema::hasColumn` so it is a no-op against the production DB (where `stocks.expired_date` was added out-of-band) and a real `ALTER TABLE` against fresh environments. `down()` is intentionally empty so a rollback never destroys the ~200k production rows.

### Tests

`tests/Feature/Api/Public/StockVerificationTest.php` covers:

1. Valid ULID with `expired_date` set → 200 with formatted date
2. Valid ULID with `expired_date = null` → 200 with JSON `null` (asserted as present-but-null)
3. Valid-shape ULID not in DB → 404
4. Invalid ULID shapes (too short, non-Crockford chars, SQL injection, UUID) → 404
5. Whitelist assertion: response contains only `serial_number`, `product_name`, `expired_date` — no price, no warehouse, no internal FKs
6. Soft-deleted stock → 404 (same shape as missing)
7. Rate limit triggers on the 31st request within a minute

The test suite uses `RefreshDatabase` and seven minimal new factories (`Product`, `ProductBrand`, `ProductCategory`, `ProductUnit`, `StockProductUnit`, `Stock`, `Uom`, `Warehouse`). Side-effecting model `booted` hooks (Warehouse and ProductUnit auto-create cross-product `stock_product_units`) are bypassed in test setup with `Model::withoutEvents`.

---

## Operational Notes

**`stock_product_units` is auto-populated by model side-effects, not by explicit application code.** Two listeners enforce a "every product unit has a `stock_product_units` row in every warehouse" invariant: `App\Models\Warehouse::booted()` (created hook) iterates every existing `ProductUnit` and creates a `stock_product_units` row when a new warehouse is added, and `App\Listeners\ProductUnits\CreateStockProductUnit` (queued, listening to `ProductUnitCreated`) does the inverse — iterates every existing warehouse (including soft-deleted ones, via `withTrashed()`) when a new product unit is created. This means: (a) tests that create `Warehouse` or `ProductUnit` rows will get unexpected `stock_product_units` cascades unless wrapped in `Model::withoutEvents(...)`; (b) querying "how many stock_product_units exist?" will not match "warehouses × product_units" if either listener has ever failed silently in the queue; (c) bulk operations on warehouses or product units have N×M write amplification that is invisible from the call site. If you're debugging missing or duplicate `stock_product_units` rows, check the queue worker's log and these two hooks before assuming a data corruption.

### Rollout checklist

1. Set `CORS_VERIFY_ORIGIN_1=https://cek.platinumadisentosa.com` in the production `.env`.
2. Deploy. The migration will run as a no-op against prod.
3. Verify with: `curl https://api-host/api/public/stocks/01kq217ybqkyx86092nkyf45p6` (use a known-good production serial).
4. Point `cek.platinumadisentosa.com` DNS at the scan-page front-end once the endpoint is confirmed reachable.


