# BACKEND DOCUMENTATION — Transfer Stock v2 (Updated Flow)

> **Target:** AI Agent / Backend Developer
> **Framework:** Laravel 10 REST API (BEJO WMS)
> **Feature Status:** Implemented — v2 (flow updated August 2026)
> **Related PRDs:** `docs/tasks/stock/stock-transfer/transfer-stock.md` (frontend) · `docs/tasks/stock/stock-transfer/backend.md` (v1, sejarah)

---

## 1. Changelog (v1 → v2)

Dokumen ini mendokumentasikan **state implementasi saat ini (v2)**. `backend.md` menyimpan versi sebelumnya (v1) untuk referensi sejarah.

| # | Aspek | v1 (lama) | v2 (sekarang) |
|---|-------|-----------|---------------|
| 1 | `is_stock` | Wajib `true`. Stock `is_stock=false` ditolak | **Bebas.** Stock `is_stock` true/false sama-sama bisa di-transfer |
| 2 | `parent_id` (child) | Child ditolak — harus scan parent | **Child boleh di-scan mandiri.** Hanya stock child itu yang pindah, grouping TIDAK ikut |
| 3 | Parent di-scan | Parent + semua childs ikut pindah | **Tetap dipertahankan** — parent + semua childs ikut pindah |
| 4 | Validasi `is_stock` di FormRequest | Ada | **Dihapus** |
| 5 | Validasi `parent_id` di FormRequest | Ada | **Dihapus** |
| 6 | Update query di controller | `where(id)->orWhere(parent_id)` tanpa kondisi | **Kondisional** — childCount>0 → parent+childs, selainnya → hanya stock itu |

---

## 2. Feature Overview

Transfer Stock memindahkan stok barang **antar warehouse** untuk **product unit yang sama**. Transfer tidak pernah memindahkan stok antar product unit yang berbeda.

Dua mekanisme transfer, dideteksi otomatis dari payload `POST /api/stocks/transfer`:

| Mekanisme | Deteksi | Gateway | Data yang dipindah |
|-----------|---------|---------|--------------------|
| **QR Flow** | Payload berisi `stock_ids` (array) | `Stock` rows (ULID) | Individual `Stock` records |
| **Non-QR Flow** | Payload tidak berisi `stock_ids` | `StockProductUnit.qty` | Integer `qty` dari `stock_product_units` |

---

## 3. File Inventory

| File | Path |
|------|------|
| Model | `app/Models/StockTransfer.php` |
| Controller | `app/Http/Controllers/Api/StockTransferController.php` |
| Form Request | `app/Http/Requests/Api/TransferStockRequest.php` |
| API Resource | `app/Http/Resources/StockTransferResource.php` |
| Migration | `database/migrations/2026_08_25_000001_create_stock_transfers_table.php` |
| Route | `routes/api.php` (line 320) |

---

## 4. Database Schema — `stock_transfers`

```php
Schema::create('stock_transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->char('stock_id', 26)->nullable()->index();      // ULID stock (QR flow only)
    $table->foreignId('from_stock_product_unit_id')->constrained('stock_product_units')->restrictOnDelete();
    $table->foreignId('to_stock_product_unit_id')->constrained('stock_product_units')->restrictOnDelete();
    $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
    $table->unsignedInteger('qty')->default(0);             // jumlah unit yang dipindah
    $table->text('description')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Kolom per Flow

| Kolom | QR Flow | Non-QR Flow |
|-------|---------|-------------|
| `stock_id` | **Terisi** (ULID stock yang di-scan) | **NULL** |
| `from_stock_product_unit_id` | Dari merge request | `stock_product_unit_id` dari payload |
| `to_stock_product_unit_id` | SPU tujuan (product unit sama, warehouse berbeda) | Sama |
| `from_warehouse_id` | Warehouse asal (dari SPU asal) | Sama |
| `to_warehouse_id` | Dari payload | Sama |
| `product_unit_id` | Dari SPU asal | Sama |
| `qty` | `childs_count` jika parent, `1` jika child/individual | Dari payload `qty` |

---

## 5. Route & Permission

```php
Route::post('stocks/transfer', [StockTransferController::class, 'transfer']);
```

- Auth: `auth:sanctum` (group middleware)
- Permission: `stock_edit` (di controller constructor)
- HTTP Method: `POST`
- Rate limit: group `api` (default throttling)

---

## 6. Controller Logic

### 6.1 Entry Point — `transfer()`

```php
public function transfer(TransferStockRequest $request)
{
    if ($request->has('stock_ids')) {
        return $this->transferQr($request);
    }

    return $this->transferNonQr($request);
}
```

**Flow detection didasarkan keberadaan key `stock_ids` di payload** (bukan dari nilai `is_generate_qr`).

### 6.2 QR Flow — `transferQr()` (DIUPDATE di v2)

```
1. Query stocks: Stock::whereIn('id', $request->stock_ids)->get()
2. Ambil nilai dari request (sudah di-merge oleh FormRequest):
   - from_stock_product_unit_id, to_stock_product_unit_id
   - from_warehouse_id, to_warehouse_id
   - product_unit_id
3. Cache nama warehouse (1 query per warehouse, di luar loop):
   - $fromWarehouseName, $toWarehouseName → dipakai untuk deskripsi StockHistory
4. DB::beginTransaction
5. Loop setiap stock:
   a. $childCount = $stock->childs()->count()
   b. $qty = $childCount > 0 ? $childCount : 1
   c. Update stock_product_unit_id (KONDISIONAL di v2):
      - Jika $childCount > 0  → stock ini PARENT:
          Stock::where('id', $stock->id)
               ->orWhere('parent_id', $stock->id)
               ->update(['stock_product_unit_id' => $toSpuId])
          → parent + SEMUA childs-nya pindah warehouse
      - Jika $childCount == 0 → stock ini CHILD atau INDIVIDUAL:
          Stock::where('id', $stock->id)
               ->update(['stock_product_unit_id' => $toSpuId])
          → HANYA stock itu sendiri yang pindah, grouping TIDAK ikut
   d. Create StockTransfer (1 record per stock yang di-scan)
   e. Create 2 StockHistory records:
      - Decrement (is_increment = 0) di SPU asal
      - Increment (is_increment = 1) di SPU tujuan
6. DB::commit
7. Return createdResponse("Transfer stock berhasil")
```

**Perilaku detail scan QR (v2):**

| Yang di-scan | childs_count | qty di StockTransfer | Update stock_product_unit_id |
|--------------|--------------|----------------------|------------------------------|
| **Parent stock** (punya childs) | > 0 | `childs_count` | Parent + semua childs |
| **Child stock** (parent_id terisi, tanpa childs) | 0 | `1` | Hanya stock child itu |
| **Individual stock** (tanpa parent, tanpa childs) | 0 | `1` | Hanya stock itu |

**Contoh v2 — scan child mandiri:** Group `GR-1` berisi parent `P` + 3 childs (`C1`, `C2`, `C3`). User scan `C2`:
- `C2.childs()->count()` = 0 → `qty = 1`
- Update hanya `C2.stock_product_unit_id` → warehouse tujuan
- `P`, `C1`, `C3` **tetap** di warehouse asal
- 1 StockTransfer record (`stock_id = C2`, qty 1)
- 2 StockHistory records

**Contoh v2 — scan parent:** User scan `P`:
- `P.childs()->count()` = 3 → `qty = 3`
- Update `P`, `C1`, `C2`, `C3` semuanya → warehouse tujuan
- 1 StockTransfer record (`stock_id = P`, qty 3)
- 2 StockHistory records

**Contoh v2 — scan 5 stock individual:** 5 StockTransfer records, masing-masing `qty = 1`.

**Catatan penting:** Jika parent DI-SCAN bersama child-nya dalam request yang sama, child akan diproses dua kali (pertama ikut parent via `orWhere parent_id`, kedua sebagai item loop sendiri). Batch update idempotent sehingga tidak ada efek ganda — satu StockTransfer tambahan dibuat untuk child, tapi stock tetap di warehouse tujuan.

### 6.3 Non-QR Flow — `transferNonQr()` (TIDAK BERUBAH)

```
1. $fromSpuId = $request->stock_product_unit_id
2. $productUnitId, $qty dari request (sudah di-merge oleh FormRequest)
3. Query $fromSpu (dengan relasi warehouse), $toSpu (SPU tujuan)
4. $fromSpu->decrement('qty', $qty)   // kurangi stock asal
5. $toSpu->increment('qty', $qty)     // tambah stock tujuan
6. Create StockTransfer (1 record, stock_id = null)
7. Create 2 StockHistory records (decrement + increment)
8. DB::commit
9. Return createdResponse("Transfer stock berhasil")
```

**Catatan:** `StockTransferResource` yang dipakai di dalam method sudah **di-comment out** — kedua flow saat ini return `$this->createdResponse("Transfer stock berhasil")` tanpa detail data.

---

## 7. FormRequest — `TransferStockRequest`

### 7.1 Rules

```php
'stock_product_unit_id' => ['required', 'integer', 'exists:stock_product_units,id'],
'to_warehouse_id'       => ['required', TenantedRule(Warehouse::class)],
'stock_ids'             => ['required', 'array', 'min:1'],
'stock_ids.*'           => ['required', 'string'],
'qty'                   => ['nullable', 'integer', 'min:1'],
'description'           => ['nullable', 'string', 'max:255'],
```

**Validasi bawaan Laravel (sebelum `withValidator`):**
- `stock_product_unit_id` wajib, integer, dan exists di `stock_product_units`
- `to_warehouse_id` wajib dan harus dalam tenant scope user (`TenantedRule`)
- `stock_ids` wajib array minimal 1 item, tiap item string
- `qty` optional, integer, minimal 1
- `description` optional, string, maks 255 karakter

**Catatan penting:** `stock_ids` dideklarasikan `required|array|min:1` — artinya **Non-QR Flow secara teknis HARUS mengirim `qty`**, dan jika tidak mengirim `stock_ids` akan lolos validasi rules. Behavior aktual:

| Payload | Flow | Notes |
|---------|------|-------|
| `stock_ids` ada | QR Flow | `qty` diabaikan |
| `stock_ids` tidak ada + `qty` ada | Non-QR Flow | Validasi qty di `after` callback |

### 7.2 `withValidator` (validasi tambahan)

```php
protected function withValidator(Validator $validator): void
{
    $validator->after(function ($validator) {
        $hasStockIds = ! empty($this->stock_ids);
        $hasSpuId = ! empty($this->stock_product_unit_id);

        if ($hasStockIds) {
            $this->validateQrFlow($validator);
        } elseif ($hasSpuId) {
            $this->validateNonQrFlow($validator);
        }
    });
}
```

### 7.3 `validateQrFlow()`

Validasi berurutan; jika satu gagal, berhenti (return):

```
1. SPU asal harus ada → error: 'Stock product unit asal tidak ditemukan'
2. productUnit.is_generate_qr HARUS true → error: 'Product unit ini tidak menggunakan QR. Gunakan qty untuk transfer'
3. SPU asal.warehouse_id != to_warehouse_id → error: 'Warehouse tujuan harus berbeda dengan warehouse asal'
4. SPU tujuan harus ada (product_unit_id sama, warehouse_id = to_warehouse_id) → error: 'Stock product unit tujuan tidak ditemukan'
5. Merge ke request:
   from_stock_product_unit_id = SPU asal id
   to_stock_product_unit_id   = SPU tujuan id
   product_unit_id            = SPU asal.product_unit_id
   from_warehouse_id          = SPU asal.warehouse_id
6. Panggil validateStockIds()
```

### 7.4 `validateStockIds()` (DIUPDATE di v2)

```
1. Query stocks: Stock::whereIn('id', $request->stock_ids)->get()
2. Deteksi missing (ULID yang tidak ditemukan): array_diff(stock_ids, found_ids)
   → error: 'Stock tidak ditemukan: {comma_separated_ulids}'
3. Loop setiap stock, cek berurutan:
   a. stock_product_unit_id == $fromSpuId → error: 'Stock "{ulid}" tidak sesuai dengan product unit asal'
   b. salesOrderItems()->whereNotReturned() TIDAK ada → error: 'Stock "{ulid}" sudah masuk di Sales Order'
```

**Perubahan v2 — validasi yang DIHAPUS:**
- ~~`is_stock == true`~~ → **dihapus.** Stock `is_stock=false` tetap valid untuk di-transfer.
- ~~`parent_id == NULL`~~ → **dihapus.** Child stock (`parent_id != null`) tetap valid untuk di-transfer mandiri.

**Daftar cek validasi per stock yang TETAP aktif (v2):**

| # | Cek | Error Key | Error Message |
|---|-----|-----------|---------------|
| 1 | Stock ditemukan di DB | `stock_ids` | Stock tidak ditemukan: {ulid_list} |
| 2 | `stock_product_unit_id` cocok dengan SPU asal | `stock_ids` | Stock "{ulid}" tidak sesuai dengan product unit asal |
| 3 | Belum masuk Sales Order aktif | `stock_ids` | Stock "{ulid}" sudah masuk di Sales Order |

### 7.5 `validateNonQrFlow()` (TIDAK BERUBAH)

Validasi berurutan; jika satu gagal, berhenti:

```
1. qty wajib ada → error: 'Qty wajib diisi untuk transfer non-QR'
2. SPU asal harus ada → error: 'Stock product unit tidak ditemukan'
3. productUnit.is_generate_qr HARUS false → error: 'Product unit ini menggunakan QR. Gunakan stock_ids untuk transfer'
4. SPU asal.qty >= qty → error: 'Qty tidak mencukupi. Tersedia: {qty}'
5. SPU asal.warehouse_id != to_warehouse_id → error: 'Warehouse tujuan harus berbeda dengan warehouse asal'
6. SPU tujuan harus ada → error: 'Stock product unit tujuan tidak ditemukan'
7. Merge ke request:
   from_stock_product_unit_id = SPU asal id
   to_stock_product_unit_id   = SPU tujuan id
   product_unit_id            = SPU asal.product_unit_id
   from_warehouse_id          = SPU asal.warehouse_id
```

---

## 8. StockHistory Records

Setiap transfer membuat **2 StockHistory records** (1 per transfer).

### 8.1 QR Flow

Untuk setiap StockTransfer:

| # | stock_product_unit_id | is_increment | value | description |
|---|------------------------|--------------|-------|-------------|
| 1 | `from_stock_product_unit_id` | `0` (decrement) | `qty` | `Transfer stock ke {toWarehouseName}` + ` - {description}` jika ada |
| 2 | `to_stock_product_unit_id` | `1` (increment) | `qty` | `Transfer stock dari {fromWarehouseName}` + ` - {description}` jika ada |

Field yang sama: `user_id` (user login), `ip`, `agent` (dari request header).

### 8.2 Non-QR Flow

Identik dengan QR flow, tetapi menggunakan nama warehouse dari relasi `$toSpu->warehouse->name` dan `$fromSpu->warehouse->name`.

### 8.3 Polymorphic Parent

StockHistory records terhubung ke `StockTransfer` via `morphMany`:

```php
// StockTransfer.php
public function histories(): MorphMany
{
    return $this->morphMany(StockHistory::class, 'model');
}
```

Artinya di `stock_histories`:
- `model_type` = `App\Models\StockTransfer`
- `model_id` = `stock_transfers.id`

---

## 9. Response

### Success

```json
{
  "message": "Transfer stock berhasil"
}
```

Status code: `201 Created` (dari `$this->createdResponse()`).

**Catatan:** API Resource `StockTransferResource` sudah dibuat (`app/Http/Resources/StockTransferResource.php`) dan berisi relasi lengkap (`stock`, `from_stock_product_unit`, `to_stock_product_unit`, `from_warehouse`, `to_warehouse`, `product_unit`, `user`), tetapi pemakaiannya **di-comment out** di controller. Saat mengaktifkan kembali, return `StockTransferResource::collection($transfers)` (QR) / `new StockTransferResource($stockTransfer)` (Non-QR).

### Error (422 Validation)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "stock_ids": [
      "Stock \"01XYZ...\" sudah masuk di Sales Order"
    ]
  }
}
```

### Error (500 Server)

```json
{
  "message": "{exception message}",
  "error": true
}
```

Return dari catch block `transferQr()`/`transferNonQr()` dengan `status_code = 500`.

---

## 10. Business Rules & Edge Cases (v2)

| # | Rule |
|---|------|
| 1 | **Product unit harus sama** — `from` dan `to` selalu `product_unit_id` yang sama. Tidak ada transfer antar product unit berbeda. |
| 2 | **Warehouse harus berbeda** — `from_warehouse_id != to_warehouse_id`. |
| 3 | **Stock `is_stock` true/false sama-sama boleh** — TIDAK ada cek `is_stock`. |
| 4 | **Child stock boleh di-scan mandiri** — ketika child di-scan, HANYA stock itu yang dipindah, grouping (parent + sibling) TIDAK ikut. |
| 5 | **Parent pindah beserta childs** — ketika parent di-scan, update `stock_product_unit_id` di parent + SEMUA childs-nya. |
| 6 | **qty pada parent = jumlah childs** — `qty = childs_count` (TIDAK termasuk parent). |
| 7 | **qty pada child/individual = 1** — stock tanpa childs → `qty = 1`. |
| 8 | **Stock tidak boleh di Sales Order aktif** — `salesOrderItems()->whereNotReturned()->exists()` → ditolak. |
| 9 | **QR flow menolak non-QR product** — product unit harus `is_generate_qr == true` saat kirim `stock_ids`. |
| 10 | **Non-QR flow menolak QR product** — product unit harus `is_generate_qr == false` saat transfer via `qty`. |
| 11 | **Non-QR qty tidak boleh melebihi stok** — `SPU.qty >= request.qty`. |
| 12 | **Parent + child di-scan dalam 1 payload** — child diproses dua kali (ikut parent + loop mandiri); idempotent, dibuat StockTransfer tambahan. |
| 13 | **Duplicate `stock_ids`** — tidak ada validasi eksplisit; duplicate → beberapa StockTransfer dibuat, batch update `stock_product_unit_id` idempotent. |

---

## 11. Model — `StockTransfer`

| Relasi | Tipe |
|--------|------|
| `stock()` | BelongsTo (Stock) |
| `fromStockProductUnit()` | BelongsTo (StockProductUnit, `from_stock_product_unit_id`) |
| `toStockProductUnit()` | BelongsTo (StockProductUnit, `to_stock_product_unit_id`) |
| `fromWarehouse()` | BelongsTo (Warehouse, `from_warehouse_id`) |
| `toWarehouse()` | BelongsTo (Warehouse, `to_warehouse_id`) |
| `productUnit()` | BelongsTo (ProductUnit) |
| `user()` | BelongsTo (User) |
| `histories()` | MorphMany (StockHistory) |

**Scopes:**
- `startDate($value)` — `whereDate('created_at', '>=', $value)`
- `endDate($value)` — `whereDate('created_at', '<=', $value)`

**Auto-assign:** `user_id` otomatis diisi dari `auth('sanctum')->id()` saat creating (jika kosong).

**Soft Deletes:** Ya — `deleted_at` tracker.

---

## 12. Catatan Khusus / Known Issues

1. **Tidak ada endpoint GET untuk StockTransfer** — Riwayat transfer dilihat via **StockHistory API** (`GET /api/stock-histories`), bukan via StockTransfer langsung. Akses data StockTransfer melalui relasi polymorphic `stockHistoryable` pada StockHistory.

2. **`StockTransferResource` belum aktif** — Sudah dibuat tapi pemakaiannya di-comment out. Response saat ini hanya `{ message: "Transfer stock berhasil" }`.

3. **`stock_transfers` tidak memiliki `Tenanted` trait** — Model tidak warehouse-scoped. Seperti `StockHistory`/`AdjustmentRequest`, keamanan ditangani di level permission (`stock_edit`), bukan tenancy.

4. **Update query kondisional di `transferQr`** — Update dipecah dua cabang:
   - `childs_count > 0` → `where('id')->orWhere('parent_id')` (parent + childs)
   - `childs_count == 0` → `where('id')` saja (child/individual), tanpa grouping

5. **Nama `Warehouse` di-hardcode lookup di `transferQr`** — menggunakan `\App\Models\Warehouse::where('id', ...)->value('name')` untuk menghindari N+1 di dalam loop.

6. **Semantik `qty` pada parent** — `qty = childs_count`, TIDAK termasuk parent itu sendiri. Konsisten dengan perilaku v1.

---

## 13. Testing Checklist (v2)

### Valid (diperbolehkan)

- [ ] Scan 1 stock individual → StockTransfer qty=1, `stock_product_unit_id` dipindah
- [ ] Scan 1 parent stock (dengan childs) → StockTransfer qty=childs_count, parent + childs pindah
- [ ] Scan 1 child stock → StockTransfer qty=1, HANYA child itu yang pindah, grouping tetap di warehouse asal
- [ ] Scan stock dengan `is_stock = false` → berhasil di-transfer (tanpa error)
- [ ] Scan banyak stock campuran (parent + child + individual) sekaligus → sukses, masing-masing StockTransfer records
- [ ] Validasi sukses: 2 StockHistory records dibuat (decrement + increment) dengan `model_type = App\Models\StockTransfer`

### Invalid (ditolak)

- [ ] Scan ULID tidak dikenal → error "Stock tidak ditemukan"
- [ ] Scan stock dari product unit / SPU berbeda → error "tidak sesuai dengan product unit asal"
- [ ] Scan stock yang sudah di Sales Order → error "sudah masuk di Sales Order"
- [ ] `to_warehouse_id` sama dengan warehouse asal → error "Warehouse tujuan harus berbeda"
- [ ] SPU tujuan tidak ada di warehouse tujuan → error "Stock product unit tujuan tidak ditemukan"
- [ ] Non-QR: `qty` melebihi SPU.qty → error "Qty tidak mencukupi"
- [ ] Non-QR: product QR dikirim tanpa `stock_ids` → error "Gunakan stock_ids untuk transfer"
- [ ] Product unit non-QR dikirim `stock_ids` → error "Product unit ini tidak menggunakan QR. Gunakan qty untuk transfer"

### Regresi (pastikan tidak berubah dari v1)

- [ ] Product unit harus sama — tidak bisa transfer antar product unit berbeda
- [ ] Warehouse asal ≠ warehouse tujuan
- [ ] Stock yang sudah di Sales Order aktif ditolak