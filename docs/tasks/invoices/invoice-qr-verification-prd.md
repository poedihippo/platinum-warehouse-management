# PRD — Invoice QR Verification (Scan QR Stock pada Detail Invoice)

> **Product:** BEJO — Warehouse Management System
> **Company:** Platinum Adi Sentosa
> **Backend:** Laravel 10 REST API
> **Audience:** Frontend AI agent / BEJO CMS
> **Status:** Backend ready (implemented & tested: 8 tests / 34 assertions passing)

---

## 1. Tujuan

Setelah invoice dibuat (`POST /api/invoices`), user perlu **memindai (scan) QR stock** untuk
mengaitkan kode QR produk yang benar ke setiap baris (detail) invoice. Ini dilakukan **setelah**
invoice dibuat, saat user melihat detail invoice — **bukan saat create**.

Alur lengkap:
1. User buka/lihat invoice (`GET /api/invoices/{invoice}` → `show`).
2. Pada setiap detail (satu product), user scan QR produk yang dibeli.
3. Frontend panggil `POST /api/invoices/{salesOrder}/verification/{salesOrderDetail}` untuk
   memasukkan stock QR tersebut ke table `sales_order_items`, tertaut dengan detail invoice
   (`delivery_order_detail_id = null`).
4. Frontend dapat melihat daftar stock QR yang sudah ter-scan via
   `GET /api/invoices/{salesOrder}/details/{salesOrderDetail}/items`.
5. Bila salah scan / tidak jadi dipakai, Frontend menghapus via
   `DELETE /api/sales-order-items/{salesOrderDetail}`.

---

## 2. Konsep Data: Stock Grouping (Parent–Child)

Satu stock dapat memiliki "children" (grouping, misal kardus berisi produk):

- **Parent** = stock utama (misal kardus) → punya row sendiri di tabel `stocks` **dan** punya `childs`.
- **Child** = stock di dalamnya (produk dalam kardus) → punya `parent_id` yang menunjuk ke stock parent.

### Aturan saat scan

**Jika stock yang di-scan adalah PARENT (memiliki `childs`):**
Backend otomatis menulis beberapa baris ke `sales_order_items`:

| Baris | stock_id | is_parent | parent_id |
|-------|----------|-----------|-----------|
| parent | `stock.id` parent | `true` | `null` |
| child 1 | `childA.id` | `false` (default) | id baris parent |
| child 2 | `childB.id` | `false` (default) | id baris parent |
| ... | dst | | |

**Jika stock yang di-scan adalah CHILD / leaf (tanpa children):**
Backend menulis 1 baris `sales_order_items`:

| stock_id | is_parent | parent_id |
|----------|-----------|-----------|
| `stock.id` | `false` | `null` |

> **Penting:**
> - Semua baris yang dibuat selalu memiliki `sales_order_detail_id` = id detail invoice terkait dan
>   `delivery_order_detail_id = null`.
> - `is_returned` selalu `false` untuk hasil scan baru.

---

## 3. Endpoint-Endpoint

Auth umum untuk semua endpoint di bawah:
- Header `Authorization: Bearer <token>` (Sanctum, ability `warehouse`).
- Permission per endpoint dicantumkan di tiap bagian.

---

### 3.1 GET /api/invoices/{salesOrder}/details/{salesOrderDetail}/items

Menampilkan daftar stock QR yang sudah ter-scan pada satu detail invoice (termasuk struktur
parent/child dari tiap item).

**Route:**
```
GET /api/invoices/{salesOrder}/details/{salesOrderDetail}/items
```

| Param | Tipe | Keterangan |
|-------|------|-----------|
| `{salesOrder}` | int | id invoice (`sales_orders.id`) |
| `{salesOrderDetail}` | int | id detail (`sales_order_details.id`) |

**Auth:** permission `invoice_read`.

**Query params (opsional):**
| Param | Keterangan |
|-------|-----------|
| `per_page` | jumlah per halaman, default `15` |
| `filter[is_parent]` | `1`/`0` atau `true`/`false` |
| `filter[is_returned]` | `1`/`0` atau `true`/`false` |

**Respons 200 — pagination (struktur standar Laravel):**
```json
{
  "data": [
    {
      "id": 12,
      "is_returned": false,
      "parent_id": null,
      "is_parent": true,
      "stock_id": "01H3ABCDEFGHJKLMNPQRSTUVWX",
      "sales_order_detail_id": 7,
      "delivery_order_detail_id": null,
      "stock": {
        "id": "01H3ABCDEFGHJKLMNPQRSTUVWX",
        "parent_id": null,
        "batch_number": null,
        "batch_number_jp": null,
        "stock_product_unit_id": 40,
        "adjustment_request_id": null,
        "receive_order_id": 9,
        "receive_order_detail_id": 15,
        "description": null,
        "qr_code": null,
        "scanned_count": 0,
        "scanned_datetime": null,
        "is_tempel": true,
        "expired_date": null,
        "created_at": "2026-08-31T10:00:00.000000Z",
        "updated_at": "2026-08-31T10:00:00.000000Z",
        "deleted_at": null
      }
    }
  ],
  "links": {
    "first": "http://localhost/api/invoices/5/details/7/items?page=1",
    "last": "http://localhost/api/invoices/5/details/7/items?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "/api/invoices/5/details/7/items",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

**Field objek `data[]` (dari `sales_order_items`):**
| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | int | id baris `sales_order_items` |
| `is_returned` | boolean | apakah stock ini sudah direturn |
| `parent_id` | int \| null | id baris `sales_order_items` parent (jika baris ini child) |
| `is_parent` | boolean | `true` jika baris ini adalah parent group |
| `stock_id` | ULID (26-char) | **id stock / payload QR** |
| `sales_order_detail_id` | int | id detail invoice |
| `delivery_order_detail_id` | int \| null | **selalu `null` untuk hasil scan invoice** |
| `stock` | object \| null | detail data stock (di-eager-load, selalu ada) |

> **Catatan penting utk frontend:**
> - Objek `stock` **selalu ada** (di-eager-load oleh backend).
> - `stock.qr_code` dikembalikan `null` — QR di-render **client-side** memakai `stock.id` sebagai
>   payload/serial number.
> - `sales_order_detail` **TIDAK di-load** di endpoint ini. Jika frontend mengaksesnya lewat resource
>   ini akan bernilai `null`. Gunakan `GET /api/invoices/{invoice}` untuk data detail invoice.
> - Field `childs_count`, `receive_order_detail` juga **tidak di-load** di endpoint ini (akan
>   `null`/tidak ada).

---

### 3.2 POST /api/invoices/{salesOrder}/verification/{salesOrderDetail}

Memasukkan hasil scan QR stock ke dalam detail invoice (membuat baris di `sales_order_items`).

**Route:**
```
POST /api/invoices/{salesOrder}/verification/{salesOrderDetail}
```

| Param | Tipe | Keterangan |
|-------|------|-----------|
| `{salesOrder}` | int | id invoice |
| `{salesOrderDetail}` | int | id detail invoice |

**Auth:** permission `invoice_create`.

**Request body (JSON):**
```json
{
  "stock_id": "01H3ABCDEFGHJKLMNPQRSTUVWX"
}
```
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|-----------|
| `stock_id` | ULID (26-char) | ya | id stock / isi payload QR |

**Proses backend (urutan validasi):**
1. Pastikan `{salesOrderDetail}` **milik** invoice `{salesOrder}`. Jika tidak → `404`.
2. Validasi stock: cari `Stock` dengan `id = stock_id` yang memiliki `stock_product_unit` dengan
   `warehouse_id = sales_order_detail.warehouse_id` dan `product_unit_id` sesuai detail
   (lihat catatan `refer_id` di bawah). Jika tidak cocok → `400 'Stok produk tidak sesuai'`.
3. Cek duplikat: jika stock tsb sudah pernah di-scan (ada `sales_order_items` non-returned dengan
   `stock_id` tsb) → `400 'Product sudah pernah di verifikasi'`.
4. Cek kuota: jika `fulfilled_qty >= sales_order_detail.qty` → `400 'Qty sudah terpenuhi'`.
5. Simpan baris (parent grouping bila ada child, lihat Bagian 2), lalu hitung ulang `fulfilled_qty`.

> **Catatan `refer_id` (pencocokan product unit):**
> Pencocokan dilakukan di tabel `stock_product_units` (kolom `product_unit_id` + `warehouse_id`).
> Jika product unit detail memiliki `refer_id`, yang dicocokkan adalah
> `product_unit_id = refer_id` (bukan `product_unit_id` detail).

**Respons sukses — HTTP 201 (Created), body = `SalesOrderItemResource` berisi baris yang baru dibuat:**

Contoh scan **parent** (memiliki 2 child):
```json
{
  "data": {
    "id": 12,
    "is_returned": false,
    "parent_id": null,
    "is_parent": true,
    "stock_id": "01H3PARENTPARENTPARENTPARENT",
    "sales_order_detail_id": 7,
    "delivery_order_detail_id": null,
    "stock": {
      "id": "01H3PARENTPARENTPARENTPARENT",
      "parent_id": null,
      "...": "field stock lain"
    }
  }
}
```

Contoh scan **leaf** (tanpa child):
```json
{
  "data": {
    "id": 13,
    "is_returned": false,
    "parent_id": null,
    "is_parent": false,
    "stock_id": "01H3LEAFLEAFLEAFLEAFLEAFLEAF",
    "sales_order_detail_id": 7,
    "delivery_order_detail_id": null,
    "stock": {
      "id": "01H3LEAFLEAFLEAFLEAFLEAFLEAF",
      "parent_id": null,
      "...": "field stock lain"
    }
  }
}
```

> **Catatan penting utk frontend:**
> - Response hanya berisi 1 baris (baris **parent** bila stock punya child, atau baris leaf).
> - Baris-baris **child** `sales_order_items` **tidak** ikut dalam response ini, tetapi **sudah
>   tertulis ke database**. Gunakan `GET .../items` untuk melihat parent + child secara lengkap.
> - Kode status sukses adalah **201** (bukan 200).

**Respons gagal (semua 4xx):**
| HTTP | Kondisi | Body |
|------|---------|------|
| 404 | detail bukan milik invoice | (default Laravel 404) |
| 400 | stock tidak sesuai product/warehouse | `{"message": "Stok produk tidak sesuai"}` |
| 400 | stock sudah pernah di-scan | `{"message": "Product sudah pernah di verifikasi"}` |
| 400 | qty sudah terpenuhi | `{"message": "Qty sudah terpenuhi"}` |
| 422 | validasi request gagal (mis. `stock_id` kosong) | struktur error validasi Laravel |

---

### 3.3 DELETE /api/sales-order-items/{salesOrderDetail} (pendamping — sudah ada)

Menghapus stock QR yang salah scan / tidak jadi dipakai dari `sales_order_items`.

**Route:**
```
DELETE /api/sales-order-items/{salesOrderDetail}
```

| Param | Tipe | Keterangan |
|-------|------|-----------|
| `{salesOrderDetail}` | int | id detail invoice |

**Request body (JSON):**
```json
{
  "stock_id": "01H3ABCDEFGHJKLMNPQRSTUVWX"
}
```

**Perilaku:**
- Jika `stock_id` yang dihapus adalah **parent**, backend menghapus baris parent **beserta semua
  child-nya** (`parent_id = id baris parent`) dari `sales_order_items`.
- `fulfilled_qty` detail otomatis dihitung ulang.

**Respons sukses:** HTTP 200
```json
{ "message": "Data deleted successfully" }
```

> Frontend dapat memanggil endpoint ini kapan pun untuk "membatalkan" satu scan yang salah.

---

## 4. Kapasitas Scan vs Qty Target

Jumlah unit yang ter-scan dihitung dari **baris `is_parent = false`** pada `sales_order_items` dari
detail tsb (tersimpan di kolom `fulfilled_qty` pada `sales_order_details`).

Backend menolak scan lanjutan bila `fulfilled_qty >= sales_order_detail.qty`
(message `Qty sudah terpenuhi`).

**Rekomendasi frontend:**
- Tampilkan indikator jumlah ter-scan vs qty target, contoh: `"2/5 unit di-scan"`.
- Disable tombol scan pada detail yang sudah penuh (`fulfilled_qty >= qty`).
- Beri notifikasi error saat backend me-return 400 (tampilkan `response.message`).

---

## 5. Panduan Integrasi Frontend (best practice)

1. **Membaca invoice + detail**
   - Panggil `GET /api/invoices/{invoice}`.
   - Field `data.details[]` berisi array detail. Tiap detail berisi minimal:
     `id` (= `sales_order_detail_id`), `qty`, `fulfilled_qty`, `product_unit`.
   - Simpan `id` detail ini untuk dipakai pada endpoint scan & items.

2. **Menampilkan stock yang sudah ter-scan**
   - Saat detail dibuka, panggil `GET /api/invoices/{invoice}/details/{detailId}/items`.
   - Render tiap item: `stock_id` sebagai identitas QR, dan struktur grouping dari
     `is_parent` + `parent_id` (parent → tampilkan sebagai 1 group; child → di bawah parent).
   - Ingat: `stock.qr_code = null`, render QR client-side dari `stock_id`.

3. **Proses scan**
   - Setelah scanner/barcode reader membaca kode QR → kirim `stock_id`.
   - Panggil `POST /api/invoices/{invoice}/verification/{detailId}` body `{ "stock_id": "..." }`.
   - Sukses (201) → refresh daftar items utk detail itu.
   - Gagal (400/404) → tampilkan `response.message` kepada user.

4. **Hapus salah scan**
   - Panggil `DELETE /api/sales-order-items/{detailId}` body `{ "stock_id": "..." }`.
   - Sukses (200) → refresh daftar items.

5. **Error handling umum**
   - `201` → scan sukses.
   - `400` → validasi bisnis (tampilkan `response.message`).
   - `404` → invoice/detail tidak ditemukan atau mismatch.
   - `401/403` → autentikasi / permission `invoice_create` atau `invoice_read`.
   - `422` → validasi request (misal `stock_id` kosong).
   - `500` → kesalahan server.

---

## 6. Peta Implementasi Backend (referensi utk QA / debugging)

| Area | Lokasi |
|------|--------|
| Route baru | `routes/api.php` — `GET invoices/{salesOrder}/details/{salesOrderDetail}/items` & `POST invoices/{salesOrder}/verification/{salesOrderDetail}` |
| Controller | `app/Http/Controllers/Api/InvoiceController.php` — method `items()` & `verification()` |
| Validasi request | `app/Http/Requests/Api/SalesOrderItemStoreRequest.php` — rule `stock_id` (required) |
| Shape response | `app/Http/Resources/SalesOrderItemResource.php`, `app/Http/Resources/Stocks/BaseStockResource.php` |
| Model `sales_order_items` | `app/Models/SalesOrderItem.php` — `SELECT_COLUMNS`, relasi `stock`/`childs`, scope `whereNotReturned` |
| Model `stocks` | `app/Models/Stock.php` — relasi `childs`, `stockProductUnit` |
| Hitung qty | `app/Services/SalesOrderService.php` — `countFulfilledQty()` |
| Endpoint delete (sudah ada) | `app/Http/Controllers/Api/SalesOrderItemController.php` — `index`/`store`/`destroy` |

Permission yang dibutuhkan role: `invoice_read` (items), `invoice_create` (verification),
`invoice_delete` (destroy invoice). Role `admin` bypass semua permission.

---

## 7. Contoh Alur Lengkap (end-to-end)

```
1.  GET /api/invoices/5
    → data.details[] berisi:
        { id: 7, qty: 2, fulfilled_qty: 0, product_unit: { name: "Pakan A" } }

2.  Scanner membaca QR parent "01H3...P1"
    POST /api/invoices/5/verification/7  { "stock_id": "01H3...P1" }
    → 201  (backend menulis parent P1 + 2 child)

3.  GET /api/invoices/5/details/7/items
    → data[] =
        [ { stock_id: "01H3...P1",  is_parent: true,  parent_id: null },
          { stock_id: "01H3...CA",  is_parent: false, parent_id: <id item P1> },
          { stock_id: "01H3...CB",  is_parent: false, parent_id: <id item P1> } ]

4.  User salah scan "01H3...WRONG" (misal terlanjur di-scan di detail ini)
    DELETE /api/sales-order-items/7  { "stock_id": "01H3...WRONG" }
    → 200

5.  User scan ulang dengan QR yang benar, dst.
    fulfilled_qty pada detail 7 akan bertambah setara jumlah leaf yang ter-scan.
```

---

## 8. Catatan Khusus

- **`stock_id` = 26 karakter ULID** (Crockford base32). Ini **adalah** payload / nomor seri QR.
- **`qr_code`** pada stock selalu `null` — jangan bergantung padanya untuk menampilkan gambar.
- **`delivery_order_detail_id`** untuk item hasil scan invoice selalu `null` (belum masuk DO).
- Fitur scan bersifat **incremental** — satu `POST verification` hanya memproses **satu** `stock_id`.
  Untuk multi-scan, frontend memanggil endpoint beberapa kali (satu per QR).
- Backend **tidak** membuat `stock_history` baru pada operasi scan/verification invoice ini
  (berbeda dengan DO verification). Perilaku sesuai implementasi saat ini.
