# PRD: Voucher Feature — Frontend Implementation Guide

> **Target:** AI Agent Frontend
> **Backend Base URL:** `/api`
> **Auth:** Bearer token (Sanctum), ability: `warehouse`

---

## 1. Overview

Fitur voucher terdiri dari 3 layer:

1. **VoucherCategory** — Kategori voucher yang menentukan tipe & nilai diskon
2. **VoucherGenerateBatch** — Wadah pembuatan voucher massal (batch)
3. **Voucher** — Data individual kode voucher yang digunakan customer

### Relationship

```
VoucherCategory ──hasMany──▶ Voucher
VoucherGenerateBatch ──hasMany──▶ Voucher
Voucher ──hasOne──▶ SalesOrder (jika sudah dipakai)
```

### Voucher Validity Logic

Voucher valid jika:
- Belum pernah digunakan di SalesOrder (`is_used = false`)
- Tidak di-soft-delete
- Hari ini berada dalam range `start_date` — `end_date`:

| start_date | end_date | Kondisi |
|------------|----------|---------|
| null | null | Aktif selamanya |
| ada | null | Aktif mulai start_date |
| null | ada | Aktif hingga end_date |
| ada | ada | Aktif dalam range |

---

## 2. VoucherCategory

### 2.1 Data Structure

```json
{
  "id": 1,
  "name": "Diskon Ramadhan",
  "discount_type": "nominal",
  "discount_amount": 50000,
  "description": "Diskon khusus bulan Ramadan",
  "created_at": "2026-05-01T10:00:00.000000Z",
  "updated_at": "2026-05-01T10:00:00.000000Z",
  "deleted_at": null
}
```

### 2.2 API Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/voucher-categories` | List (paginated) |
| GET | `/api/voucher-categories/{id}` | Detail |
| POST | `/api/voucher-categories` | Create |
| PUT | `/api/voucher-categories/{id}` | Update |
| DELETE | `/api/voucher-categories/{id}` | Soft delete |
| PUT | `/api/voucher-categories/{id}/restore` | Restore |
| DELETE | `/api/voucher-categories/{id}/force-delete` | Force delete |

### 2.3 Filter & Sort

**Filters (query param `filter[key]`):**
- `filter[name]` — string, partial match
- `filter[discount_type]` — exact: `nominal` atau `percentage`

**Sort (query param `sort`):**
- `sort=id`, `sort=name`, `sort=discount_type`, `sort=created_at`
- Gunakan `-` untuk descending: `sort=-created_at`

### 2.4 Create / Update Request

```json
{
  "name": "Diskon Ramadhan",
  "discount_type": "nominal",
  "discount_amount": 50000,
  "description": "Keterangan opsional"
}
```

- `name`: required, string
- `discount_type`: required, enum: `nominal` | `percentage`
- `discount_amount`: required, numeric
- `description`: nullable, string

### 2.5 Frontend Display

**Halaman Index:**
- Tabel: ID, Name, Discount Type (badge), Discount Amount (formatted), Description, Actions
- Discount Type badge: hijau untuk `nominal`, biru untuk `percentage`
- Discount Amount: jika `nominal` tampilkan `Rp 50.000`, jika `percentage` tampilkan `10%`

**Form Create/Edit:**
- `name`: text input
- `discount_type`: select/dropdown (`nominal` / `percentage`)
- `discount_amount`: number input, label berubah sesuai type ("Jumlah Diskon (Rp)" / "Persentase Diskon (%)")
- `description`: textarea (optional)

---

## 3. VoucherGenerateBatch

### 3.1 Data Structure

```json
{
  "id": 1,
  "user_id": 5,
  "source": "upload",
  "description": "Batch voucher Mei 2026",
  "start_date": "2026-05-01",
  "end_date": "2026-05-31",
  "created_at": "2026-05-01T10:00:00.000000Z",
  "updated_at": "2026-05-01T10:00:00.000000Z"
}
```

### 3.2 API Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/vouchers/generate-batches` | List (paginated) |
| GET | `/api/vouchers/generate-batches/{id}` | Detail + `vouchers_count` |
| POST | `/api/vouchers/generate-batches` | Create (generate voucher massal) |
| PUT | `/api/vouchers/generate-batches/{id}` | Update (description, dates) |
| DELETE | `/api/vouchers/generate-batches/{id}` | Delete |

**Note:** Endpoint di-nest di bawah `/vouchers` prefix.

### 3.3 Filter, Sort & Include

**Filters:**
- `filter[user_id]` — exact match

**Includes:**
- `include=user` — sertakan data user pembuat

**Sort:**
- `sort=id`, `sort=user_id`, `sort=created_at`

### 3.4 Create Request (Generate Voucher Massal)

```json
{
  "voucher_category_id": 1,
  "description": "Batch voucher Mei",
  "value": 50,
  "start_date": "2026-05-01",
  "end_date": "2026-05-31"
}
```

- `voucher_category_id`: required, exists:voucher_categories,id
- `description`: nullable, string
- `value`: required, integer, min:1 (jumlah voucher yang akan di-generate)
- `start_date`: nullable, date
- `end_date`: nullable, date, after_or_equal:start_date

**Behavior:**
- Voucher akan otomatis dibuat sebanyak `value` dengan format code: `{batch_id}-{index}` (contoh: `1-0`, `1-1`, ...)
- `start_date` dan `end_date` akan di-copy ke setiap voucher yang dibuat

### 3.5 Update Request

```json
{
  "description": "Updated description",
  "start_date": "2026-06-01",
  "end_date": "2026-06-30"
}
```

- `description`: nullable, string
- `start_date`: nullable, date
- `end_date`: nullable, date, after_or_equal:start_date

**Important:** Ketika `start_date` atau `end_date` batch di-update, semua child vouchers otomatis ikut ter-update.

### 3.6 Frontend Display

**Halaman Index:**
- Tabel: ID, User (name), Source (badge), Description, Start Date, End Date, Voucher Count, Created At, Actions
- Source badge: `upload` = biru, `import` = hijau
- Kolom Voucher Count: tampilkan jumlah voucher dari `vouchers_count` (via loadCount di show)

**Form Create:**
- `voucher_category_id`: select/dropdown (ambil data dari GET `/api/voucher-categories`)
- `description`: textarea (optional)
- `value`: number input, min 1
- `start_date`: date picker (optional)
- `end_date`: date picker (optional, harus >= start_date)

**Detail Page:**
- Tampilkan info batch + tabel voucher di batch ini
- Filter voucher berdasarkan `voucher_generate_batch_id`

---

## 4. Voucher

### 4.1 Data Structure

```json
{
  "id": 1,
  "voucher_generate_batch_id": 1,
  "voucher_category_id": 1,
  "code": "ABC12345",
  "description": "Voucher diskon",
  "start_date": "2026-05-01",
  "end_date": "2026-05-31",
  "created_at": "2026-05-01T10:00:00.000000Z",
  "updated_at": "2026-05-01T10:00:00.000000Z",
  "deleted_at": null,

  "is_used": false,

  "category": {
    "id": 1,
    "name": "Diskon Ramadhan",
    "discount_type": "nominal",
    "discount_amount": 50000
  },
  "voucher_generate_batch": {
    "id": 1,
    "description": "Batch voucher Mei"
  },
  "sales_order": {
    "id": 123,
    "invoice_no": "PAS/SO/05/26/01",
    "voucher_id": 1
  }
}
```

### 4.2 API Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/vouchers` | List (paginated) |
| GET | `/api/vouchers/{id}` | Detail |
| POST | `/api/vouchers` | Create (single) |
| PUT | `/api/vouchers/{id}` | Update |
| DELETE | `/api/vouchers/{id}` | Soft delete |
| PUT | `/api/vouchers/{id}/restore` | Restore |
| DELETE | `/api/vouchers/{id}/force-delete` | Force delete |
| POST | `/api/vouchers/import` | Import dari Excel/CSV |

### 4.3 Filter, Sort & Include

**Filters (query param `filter[key]`):**
- `filter[voucher_generate_batch_id]` — exact match
- `filter[voucher_category_id]` — exact match
- `filter[code]` — partial match
- `filter[start_date]` — date (format: `Y-m-d`), filter voucher yang `start_date <= value`
- `filter[end_date]` — date (format: `Y-m-d`), filter voucher yang `end_date >= value`

**Includes (query param `include`):**
- `include=category` — sertakan VoucherCategory (id, name, discount_type, discount_amount)
- `include=salesOrder` — sertakan SalesOrder (id, invoice_no, voucher_id) jika sudah dipakai
- `include=voucherGenerateBatch` — sertakan VoucherGenerateBatch

**Sort (query param `sort`):**
- `sort=id`, `sort=voucher_category_id`, `sort=code`, `sort=created_at`
- Gunakan `-` untuk descending: `sort=-created_at`

### 4.4 Create Request (Single Voucher)

```json
{
  "voucher_generate_batch_id": 1,
  "voucher_category_id": 1,
  "code": "MYCODE123",
  "description": "Voucher spesial",
  "start_date": "2026-05-01",
  "end_date": "2026-05-31"
}
```

- `voucher_generate_batch_id`: nullable, exists:voucher_generate_batches,id
- `voucher_category_id`: required, exists:voucher_categories,id
- `code`: nullable, unique:vouchers,code — jika kosong, system auto-generate 8 karakter uppercase (contoh: `V-A1B2C3D4`)
- `description`: nullable, string
- `start_date`: nullable, date
- `end_date`: nullable, date, after_or_equal:start_date

### 4.5 Update Request

```json
{
  "voucher_generate_batch_id": 1,
  "voucher_category_id": 1,
  "code": "MYCODE123",
  "description": "Updated desc",
  "start_date": "2026-06-01",
  "end_date": "2026-06-30"
}
```

- `voucher_generate_batch_id`: nullable, exists:voucher_generate_batches,id
- `voucher_category_id`: required, exists:voucher_categories,id
- `code`: required, unique:vouchers,code,{id} — harus unique kecuali diri sendiri
- `description`: nullable, string
- `start_date`: nullable, date
- `end_date`: nullable, date, after_or_equal:start_date

**Note:** Voucher bisa diedit meskipun sudah dipakai (`is_used = true`). Jika code diubah, related SalesOrder otomatis ter-update (raw_source.voucher_code disinkronkan).

### 4.6 Import Request

**Method:** `POST /api/vouchers/import` (multipart/form-data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `voucher_category_id` | integer | Yes | ID VoucherCategory |
| `description` | string | No | Deskripsi batch |
| `file` | file | Yes | Format: xls, xlsx, csv |

**Format file Excel/CSV:**

| code | description |
|------|-------------|
| ABC12345 | Voucher diskon Mei |
| XYZ67890 | Voucher Juni |

- Kolom `code` wajib, harus unique
- Kolom `description` optional
- Otomatis membuat VoucherGenerateBatch baru (source: `import`)

### 4.7 Frontend Display

**Halaman Index — Tabel Voucher:**

| Kolom | Keterangan |
|-------|-----------|
| ID | Auto-increment |
| Code | Tampilkan code voucher (bold) |
| Category | Nama VoucherCategory |
| Discount | Tampilkan dari category: `Rp 50.000` atau `10%` |
| Batch | ID atau description VoucherGenerateBatch (jika ada) |
| Start Date | Format: `DD MMM YYYY` atau `-` jika null |
| End Date | Format: `DD MMM YYYY` atau `-` jika null |
| Status | Badge (lihat status logic di bawah) |
| Sales Order | Invoice No (jika sudah dipakai) atau `-` |
| Actions | Edit, Delete |

**Status Logic:**

| Kondisi | Label | Warna Badge |
|---------|-------|-------------|
| `is_used = true` | Digunakan | Abu-abu |
| `isValid() = false`, today < start_date | Belum Aktif | Kuning |
| `isValid() = false`, today > end_date | Expired | Merah |
| `isValid() = true` | Aktif | Hijau |

**Filter Panel:**
- Voucher Category: dropdown (ambil dari GET `/api/voucher-categories`)
- Voucher Batch: dropdown (ambil dari GET `/api/vouchers/generate-batches`)
- Code: text search
- Start Date: date picker (filter voucher dengan start_date <= value)
- End Date: date picker (filter voucher dengan end_date >= value)

**Form Create (Single):**
- `voucher_category_id`: select/dropdown
- `voucher_generate_batch_id`: select/dropdown (optional)
- `code`: text input (placeholder: "Kosongkan untuk auto-generate")
- `description`: textarea (optional)
- `start_date`: date picker (optional)
- `end_date`: date picker (optional, harus >= start_date)

**Form Edit:**
- Sama seperti Create, tapi `code` wajib diisi (unique kecuali diri sendiri)
- Tampilkan info jika voucher sudah dipakai: "Voucher ini sudah digunakan di Invoice {invoice_no}"

**Form Import:**
- `voucher_category_id`: select/dropdown
- `description`: textarea (optional)
- `file`: file upload (accept: .xls, .xlsx, .csv)

---

## 5. Voucher Usage di Invoice (Reference)

Ketika user memasukkan `voucher_code` di form invoice:

1. **Validasi** (`InvoiceStoreRequest`):
   - Voucher harus ada
   - Voucher belum dipakai
   - Voucher valid (dalam range tanggal)

2. **Perhitungan** (`CalculateVoucher` pipe):
   - Jika `discount_type = nominal`: potong langsung `discount_amount` dari harga
   - Jika `discount_type = percentage`: potong `harga x discount_amount / 100`
   - Harga final tidak boleh minus (`max(harga - diskon, 0)`)

3. **Penyimpanan**:
   - `voucher_id` disimpan di `sales_orders` (FK relationship)
   - Detail diskon disimpan di `sales_orders.raw_source`:
     ```json
     {
       "voucher_code": "ABC12345",
       "voucher_type": "nominal",
       "voucher_value": 50000,
       "voucher_value_nominal": 50000
     }
     ```

---

## 6. Permission

| Action | Permission |
|--------|-----------|
| Store (create) | `voucher_create` |
| Update | `voucher_edit` |
| Delete, Force Delete, Restore | `voucher_delete` |
| Import | `voucher_import` |

**Note:** Tidak ada permission untuk read/index. Semua user yang terautentikasi bisa membaca data voucher.

---

## 7. Pagination

Default: `per_page=15`. Bisa dikustom via query param `?per_page=20`.

Response format:
```json
{
  "data": [...],
  "links": {
    "first": "/api/vouchers?page=1",
    "last": "/api/vouchers?page=5",
    "prev": null,
    "next": "/api/vouchers?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "/api/vouchers",
    "per_page": 15,
    "to": 15,
    "total": 70
  }
}
```

---

## 8. Error Response

```json
{
  "message": "Validation failed",
  "errors": {
    "code": ["The code has already been taken."],
    "start_date": ["The start date must be a date."]
  }
}
```

HTTP Status Codes:
- `200` — Success
- `201` — Created
- `202` — Accepted (update)
- `404` — Not Found
- `422` — Validation Error
- `500` — Server Error
