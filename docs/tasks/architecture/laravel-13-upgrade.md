# Upgrade ke Laravel 13 + PHP 8.5 (skeleton fresh, code dipindah per batch)

**Status:** in progress · **Branch:** `upgrade/laravel-13` (dari `main` + merge `refactor/solid`) · **Created:** 2026-11-02

## Tujuan

Bangun ulang aplikasi di atas skeleton `laravel/laravel` ^13 dan pindahkan code SOLID apa adanya, satu commit per
batch, mengikuti upgrade HRIS (`/home/nikko/Development/laravel/hris`, branch `upgrade/laravel-13`, ADR 0015).
Runtime lokal dan production: **PHP 8.5**.

Perilaku tidak boleh berubah. Frontend BEJO dan verify sudah di production, jadi:
- tidak ada return API atau logic yang berubah;
- quirk lama tetap dipertahankan (`test_known_quirk_*`, `exit()` di `auth/register`, `dd()` di `PUT receive-orders/{id}`, DO return 200 berisi teks exception);
- tanpa fitur baru, tanpa bug fix.

## Pemetaan struktur L10 → L13

| L10 (dihapus) | L13 |
|---|---|
| `app/Http/Kernel.php`: global TrustProxies, HandleCors, PreventRequestsDuringMaintenance, ValidatePostSize, TrimStrings, ConvertEmptyStringsToNull; group `api` = Sanctum stateful + `throttle:api` + SubstituteBindings; alias loyalty, Spatie, Sanctum | `bootstrap/app.php` `withMiddleware()` |
| Middleware default (TrustProxies hanya header, VerifyCsrfToken `/api/*`, Authenticate `redirectTo` null, RedirectIfAuthenticated `/home`, dll.) | konfigurasi di `withMiddleware()`; class dihapus |
| `RouteServiceProvider`: prefix `api`, group `api` + `web`; rate limiter `api` 200/menit dan `loyalty-claims` 5/hari | `withRouting(using:)` + rate limiter di `AppServiceProvider` |
| `Console/Kernel` (schedule kosong, `Commands/` kosong) | `routes/console.php` |
| `Exceptions/Handler`: JSON ModelNotFound 404 "Data tidak ditemukan!", HttpException 403 `{"message"}`, `unauthenticated()` selalu JSON 401, `dontReport` ApiResponseException | `withExceptions()` |
| `AuthServiceProvider` (`Gate::before` admin) | `AppServiceProvider::boot()` |
| `EventServiceProvider` (6 listener app + `Registered`, discovery off) | `withEvents(discover: false)` + `AppServiceProvider::boot()` |
| providers di `config/app.php` | `bootstrap/providers.php` |
| `tests/CreatesApplication.php` | `tests/TestCase.php` |

## Hotspot

| Area | Dampak |
|---|---|
| Controller `$this->middleware()` di constructor | base `Controller` tetap extend `Illuminate\Routing\Controller` (masih ada di L13), sehingga quirk `per_page` dan DocumentCounter di constructor tetap sama |
| `float()` di migration (`voucher_categories.discount_amount` 11,2; `sales_orders.auto_discount`) | L11 mengubah DDL float/double |
| `unsignedDecimal()` (user_discounts, payments) | dihapus di L11 |
| 5 migration `->change()` | L11 men-drop atribut yang tidak disebut ulang |
| `getDoctrineConnection()` di `reshape_redemptions_table_for_phase4` | dihapus di L11 (dibungkus `try/catch`) |
| Sanctum 3 → 4 | config middleware, custom `PersonalAccessToken` + `User::createToken` |
| permission 5.11 → 6 | namespace middleware `Middlewares` → `Middleware`, config v6 |
| query-builder 5.8 → 7 | API variadic, 18 file |
| Excel 3.1 / PhpSpreadsheet 1.29 → Excel 4 / PhpSpreadsheet 5 | phpspreadsheet 1.x memblokir PHP 8.5 |
| laravel-dompdf 2.1 / dompdf 2.0 → 3 | isi PDF print SO/DO/invoice |

## Matriks dependency

| Package | Sebelum (lock) | Target |
|---|---|---|
| laravel/framework | 10.48.8 | ^13 |
| laravel/sanctum | 3.3.3 | ^4 |
| spatie/laravel-permission | 5.11.1 | ^6 |
| spatie/laravel-query-builder | 5.8.0 | ^7 |
| maatwebsite/excel | 3.1.55 | ^4 |
| barryvdh/laravel-dompdf | 2.1.1 | ^3 |
| spatie/laravel-medialibrary | 11.4.6 | ^11 (terbaru) |
| laravel/socialite / tinker | 5.13 / 2.x | ^5 terbaru / ^3 |
| league/flysystem-aws-s3-v3 | 3.0 (pin) | ^3 |
| doctrine/dbal | 3.x | dihapus |
| nesbot/carbon | 2.72.3 | 3 |
| phpunit / collision / ignition | 10 / 7 / 2 | ^12 / ^8 / ^2 (versi yang mendukung L13) |

## Checklist (satu commit per batch)

- [x] 0. Ledger + baseline L10 / PHP 8.3
- [ ] 1. Skeleton L13 + `bootstrap/app.php` + dependency target
- [ ] 2. Config: skeleton L13 + kustomisasi + fallback env lama
- [ ] 3. Migration: DDL tetap L10, skema test identik
- [ ] 4. Sanctum 4 + permission 6
- [ ] 5. spatie/laravel-query-builder 7
- [ ] 6. Excel 4 / PhpSpreadsheet 5 + dompdf 3
- [ ] 7. Test suite: PHPUnit 12, full suite PHP 8.5 = baseline
- [ ] 8. Golden master L10 vs L13 (normal + fuzz)
- [ ] 9. Dokumentasi + langkah deploy

## Baseline L10 / PHP 8.3 (2026-11-02, `refactor/solid` `c9bcb4e`)

- Suite: **234 test, 220 lulus, 14 gagal**. Failure lama per nama:
  - `InvoiceScanTest` ×2 (`test_scanned_leaf_stock_creates_single_non_parent_sales_order_item`, `test_scanned_parent_stock_is_grouped_with_children_sales_order_items`)
  - `Public\StockVerificationTest` ×2 (`test_response_does_not_leak_price_cost_or_other_internal_fields`, `test_returns_serial_product_name_and_expiry_when_stock_has_expired_date`)
  - `Feature\ExampleTest::test_the_application_returns_a_successful_response`
  - `Loyalty\AdminApprovalTest` ×2 (`test_approve_writes_points_transaction_and_total_and_emails`, `test_cannot_approve_twice`)
  - `Loyalty\Admin\ProductUnitPointsTest` ×5
  - `Unit\Pipes\CalculateAutoDiscountTest` ×2
- Snapshot aplikasi (`ENABLE_DOCUMENT_COUNTER=true`):
  - 285 route;
  - 6 middleware global;
  - group `web` / `api`;
  - 7 listener (6 app + `Registered`);
  - 2 `Gate::before` (app + Spatie);
  - limiter `api` 200/60 dtk, `loyalty-claims` 5/86400 dtk;
  - 122 command.
  - `route:list` dengan `ENABLE_DOCUMENT_COUNTER=false` tetap gagal (quirk).
- Skema `platinum_event_test` (MySQL 8.4): 59 tabel, 533 kolom, 185 baris index, 75 baris FK, 85 migration.
- Golden master (harness di luar repo, DB `platinum_event_snapshot`): 1.698 request. Ada 3 record yang memang tidak deterministik, jadi tidak dihitung sebagai regresi: nama file export dengan timestamp menit, dan satu test loyalty yang memakai faker tanpa seed.
