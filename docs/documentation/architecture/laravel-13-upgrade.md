# Upgrade ke Laravel 13 + PHP 8.5

**Branch:** `upgrade/laravel-13` (dari `main` + merge `refactor/solid`) · **Ledger:** `docs/tasks/architecture/laravel-13-upgrade.md` · **Tanggal:** 2026-11-02

## Ringkasan

Aplikasi dibangun ulang di atas skeleton `laravel/laravel` ^13 (Laravel 10.48 → 13.39, PHP 8.3 → 8.5). Code SOLID dipindah apa adanya, satu commit per batch. Struktur mengikuti cara Laravel 13 (`bootstrap/app.php`, `bootstrap/providers.php`), dan semua dependency naik ke versi yang mendukung Laravel 13.

Perilaku API tidak berubah:
- **Full suite:** 246 test. Ada 14 failure, semuanya failure baseline L10 yang sama per nama test dengan penyebab yang sama. Total test bertambah 12 karena ada test regresi baru.
- **Golden master:** 1.698 request dan 5.746 variasi fuzz dibandingkan antara L10 dan L13. Setelah fix, semua perbedaan yang tersisa masuk ke daftar perbedaan terdokumentasi di bawah.
- **Skema DB test:** identik dengan L10.
- **Route:** 285 route dengan middleware final identik.

## Yang berubah

| Commit | Batch | Isi |
|---|---|---|
| `e379775` | 0 | Ledger dan baseline L10 / PHP 8.3 |
| `76fe35b` | 1 | Skeleton L13. `Http/Kernel`, `Console/Kernel`, `Exceptions/Handler`, Route/Auth/Event/BroadcastServiceProvider, 9 middleware default, dan `tests/CreatesApplication` dihapus lalu diterjemahkan ke `bootstrap/app.php` dan `AppServiceProvider` |
| `3967377` | 2 | Config dari skeleton L13 + kustomisasi BEJO; default lama dikunci, nama env lama tetap terbaca |
| `86efdb9` | 3 | Macro `legacyDouble()` untuk DDL L10; migration permission tanpa static property v5 |
| `332df8f` | 4 | Sanctum 4 dan laravel-permission 6 |
| `a2d5913` | 5 | spatie/laravel-query-builder 7 (API variadic) |
| `3686742` | 6 | Excel 4 / PhpSpreadsheet 5 dan laravel-dompdf 3 |
| `4931a5d` | 7 | PHPUnit 12 |
| `34a8409` | 8 | Fix hasil golden master: paginator, relasi permission, header PDF, rule `image`, CSV |
| `c463c9b` | 8 | Fix hasil smoke HTTP: guest tanpa `Accept: application/json` mendapat 401 JSON |

Versi utama:

| Package | Versi |
|---|---|
| framework | 13.39 |
| sanctum | 4.3 |
| laravel-permission | 6.25 |
| query-builder | 7.3.7 |
| excel / phpspreadsheet | 4.0.6 / 5.10 |
| laravel-dompdf / dompdf | 3.1.2 / 3.1.6 |
| medialibrary | 11.23 |
| socialite / tinker | 5.31 / 3.0 |
| flysystem-aws-s3-v3 | 3.35 (pin `3.0` dilepas) |
| brevo-mailer / http-client | 7.4 |
| Carbon | 3.15 |
| phpunit / collision / ignition | 12.5 / 8.9 / 2.12 |

- `doctrine/dbal` dihapus.
- Root `php: ^8.4`, karena lock butuh ≥ 8.4 (Symfony 8, query-builder 7.3).
- Dev baru dari skeleton: `laravel/pail`, `laravel/pao`.

## Cara kerjanya

- **`bootstrap/app.php`**
  - `withRouting(using:)`: prefix `api`, group `api` dan `web`, tanpa route `/up`.
  - `withMiddleware()`:
    - trusted proxy header tanpa X-Forwarded-Prefix;
    - CSRF `except /api/*`;
    - `statefulApi()` + `throttleApi()`, sehingga group `api` tetap Sanctum stateful → `throttle:api` → SubstituteBindings;
    - `redirectGuestsTo(null)` dan `redirectUsersTo('/home')`;
    - alias `loyalty.*`, Spatie v6, dan Sanctum `ability(ies)`.
  - `withExceptions()`:
    - `ApiResponseException` tidak di-report;
    - JSON ModelNotFound → 404 "Data tidak ditemukan!";
    - JSON 403 → `{"message"}` (default "Forbidden!");
    - `AuthenticationException` selalu JSON 401.
  - `withEvents(discover: false)`.
- **`AppServiceProvider`**
  - `boot()`: rate limiter `api` dan `loyalty-claims`, `Gate::before` admin, 6 `Event::listen`, resolver validator, Sanctum token model, transport Brevo.
  - `register()`: binding paginator.
- **Controller** tetap extend `Illuminate\Routing\Controller`, jadi `$this->middleware()` di constructor dan quirk `per_page` / DocumentCounter tetap sama.
- **Config:** lihat AGENTS.md §17. `.env` production tidak perlu diubah.

## Lapisan kompatibilitas (perilaku L10 yang dipertahankan)

| Perubahan upstream | Dampak tanpa fix | Fix |
|---|---|---|
| L13 memasang `redirectGuestsTo(route('login'))` secara default | Request tanpa header JSON ke route ber-auth → 500 `Route [login] not defined` | `redirectGuestsTo(null)` |
| L12 menambah key `page` di `links` paginator | 111 response index berubah bentuk | `App\Pagination\LengthAwarePaginator` |
| permission 6 tidak me-*load* relasi setelah `syncRoles` / `syncPermissions` | POST/PUT roles dan users kehilangan `permissions` / `roles` | `load()` di `RoleService` / `UserService` |
| permission 6 men-*detach* `users()` saat permission dihapus | DELETE permission dengan guard `sanctum` → 500 | `App\Models\Permission::bootHasRoles()` |
| dompdf 3 memakai `HeaderUtils::makeDisposition` | Print SO dengan nomor `PAS/SO/...` → 500 | Response dengan header gaya dompdf 2 |
| Rule `image` di L12 menolak SVG dan menerima avif/heic/heif | Pesan 422 logo SVG berubah; foto hadiah SVG ditolak | `App\Validation\Validator` |
| Excel 4 memakai return type native | `model()` yang jatuh ke akhir method → `TypeError` (stocks/products/product-units import 500) | `return null;` eksplisit |
| Symfony 8 menebak MIME CSV sebagai `text/plain` | Content-Type sample CSV berubah | Header `text/csv` eksplisit |
| Excel 4: `csv.input_encoding` default `guess` | Import CSV bisa salah encoding | `config/excel.php` dikunci `UTF-8` |
| dompdf 3: `enable_remote` default false | Gambar remote di PDF hilang | Config dikunci `true` |
| L11: `float(T,P)` / `unsignedFloat()` | Skema test berbeda dari production | `legacyDouble()` |

## Perbedaan perilaku yang disengaja (terdokumentasi)

- `Content-Type` teks memakai `charset=utf-8` huruf kecil (Symfony 7+). Dulu `UTF-8`; menurut RFC keduanya case-insensitive.
- Pesan error yang hanya terlihat saat `APP_DEBUG=true`: `QueryException` kini menyebut host/port/database, dan beberapa teks error PHP 8.5 berbeda. Di production (debug off) tetap "Server Error".
- Halaman error HTML framework (non-JSON) berbeda CSS-nya.
- Middleware global baru dari framework: `ValidatePathEncoding` (path UTF-8 rusak → 400) dan `InvokeDeferredCallbacks`.
- Bila body input (JSON) dan file upload memakai key yang sama, nilai input kini yang dipakai (`Request::all()` L13). Ini tidak terjadi pada upload multipart biasa.
- PDF: metadata `Producer: dompdf 3.1.6`. Teks (pdftotext) dan render piksel identik untuk semua PDF SO/DO/invoice yang dibandingkan.
- Lock cache file disimpan di `storage/framework/cache/locks`; store `apc` tidak ada lagi (tidak dipakai).
- SMTP: skema ditentukan dari port (587 → STARTTLS), dan key `encryption` tidak dibaca lagi. Production memakai transport Brevo API.

## Testing

- Full suite L13 / PHP 8.5: 246 test, 14 failure, sama per nama dan penyebab dengan baseline L10.
- Test regresi baru:
  - `ExcelImportTest`, 3 test;
  - `Laravel10CompatibilityTest`, 9 test.
  - Semuanya hijau di L10, merah di L13 sebelum fix.
- Golden master (harness di luar repo, DB `platinum_event_snapshot`), L10 `refactor/solid` vs L13:
  - **Normal:** 1.698 request, 59 berbeda. Rinciannya: 27 `charset` huruf kecil, 10 byte PDF, 17 data uji acak (faker/`Str::random`), 6 teks error debug, 4 halaman error HTML, 2 timestamp nama file export.
  - **Fuzz:** 5.746 variasi, 76 berbeda. Rinciannya: 61 teks pesan 500 debug, 7 data acak, 4 byte PDF, 4 kasus input-vs-file.
- Skema `platinum_event_test`: 59 tabel, 533 kolom, 185 index, 75 FK, 85 migration — identik.
- Snapshot aplikasi:
  - 285 route dengan nama, action, dan middleware final identik;
  - listener, `Gate::before`, rate limiter, dan priority middleware identik;
  - tidak ada command yang hilang;
  - `route:list` dengan `ENABLE_DOCUMENT_COUNTER=false` tetap gagal (quirk).
- Smoke HTTP (`php8.5 artisan serve` vs `php artisan serve` L10, DB dev, read-only): `/`, `invoices/{id}/print` (nomor dengan `/`), `product-units/{stock}`, `/test`, 401 JSON (dengan/tanpa Accept JSON), login salah 422, public stock 404, 404 JSON, dan preflight CORS — semuanya sama.
- `composer check-platform-reqs` OK; `composer audit` bersih.

## Deploy (wajib dibaca sebelum merge)

1. Server production: PHP **8.5** + `php8.5-fpm`, dengan ekstensi `pdo_mysql`, `redis`, `gd`, `intl`, `zip`, `bcmath`, `mbstring`, `xml`, `curl`. Pastikan CLI (`php`) yang dipakai composer/artisan/worker juga 8.5.
2. `composer install --no-dev --optimize-autoloader`. Tanpa langkah ini, code L13 akan berjalan di atas vendor L10.
3. `php artisan migrate --force`. Tidak ada migration baru; langkah ini hanya memastikan.
4. `php artisan config:cache && php artisan route:cache` (keduanya jalan dengan nilai `ENABLE_DOCUMENT_COUNTER` apa pun; hanya `route:list` yang crash bila flag `false`, sama seperti dulu).
5. `php artisan permission:cache-reset` (atau `cache:clear`). Format cache permission v6 berbeda dari v5.
6. `php artisan queue:restart`, supaya worker Redis memuat code baru.
7. `.env` tidak perlu diubah: nama env lama tetap dibaca, cookie session dan prefix cache sama.

## Smoke test manual setelah deploy

- Login token staff (`POST /api/auth/token`), `GET /api/users/me`, dan satu list ber-filter (sales orders).
- Upload S3: bukti payment, logo brand, foto klaim loyalty.
- Export Excel (stock / stock history / invoices) dan CSV sample voucher.
- Print PDF SO, DO, dan invoice dengan nomor ber-`/`.
- Mail Brevo: verifikasi email loyalty.
- Queue worker: RO done / stock opname / product unit baru (listener ter-queue).
