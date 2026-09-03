# PRD — Frontend AI Agent: Multiple Voucher Support

## Tujuan
Backend sekarang mendukung **multiple voucher** dalam 1 sales order / invoice. Fitur ini menggantikan sistem voucher tunggal yang lama.

Dokumen ini khusus untuk **AI agent di sisi frontend** — hanya membahas kontrak input/output yang berubah terkait voucher. Tidak membahas flow create invoice secara menyeluruh.

## Breaking Changes (Ringkasan)

| Sebelumnya | Sekarang |
|------------|----------|
| Request `voucher_code` (string) | Request `voucher_codes` (array of string) |
| Response `voucher` (single object) | Response `vouchers` (array) |
| Kolom `sales_orders.voucher_id` | Dihapus → ganti table pivot `sales_order_vouchers` |

---

## 1. Input Contract — Create & Update Invoice

File: `@app/Http/Controllers/Api/InvoiceController.php`

- Function `store` (line 52) dan function `update` (line 216) keduanya memakai pipeline order yang membaca voucher dari request.
- **`voucher_codes` WAJIB dikirim sebagai ARRAY** (bukan string).

Contoh JSON body (create maupun update):

```json
{
  "warehouse_id": 1,
  "reseller_id": 2,
  "items": [ ... ],
  "voucher_codes": ["KODE1", "KODE2"]
}
```

- Tidak pakai voucher → **abaikan field** atau kirim `[]`.
- Pada saat **update**, backend melakukan `sync` terhadap pivot. Artinya array yang dikirim akan **mengganti seluruh voucher lama** secara penuh (bukan append).

### Validasi (dari `@app/Http/Requests/Api/InvoiceStoreRequest.php` dan `InvoiceUpdateRequest.php`)
Setiap kode voucher akan divalidasi:
- Voucher harus ada → error `Voucher tidak ditemukan!`
- Voucher belum dipakai → error `Voucher sudah digunakan!`
  - Khusus saat update: voucher yang sudah melekat pada invoice ini tetap diperbolehkan.
- Voucher masih dalam masa berlaku (`start_date`–`end_date`) → error `Voucher tidak valid atau sudah kedaluwarsa!`

---

## 2. Semantik Diskon (Sequential)

File: `@app/Pipes/Order/CalculateVoucher.php`

Diskon voucher diterapkan **berurutan (sequential)**, satu per satu ke harga berjalan:

1. Setiap voucher memotong dari **harga berjalan** (harga sudah terpotong voucher sebelumnya).
2. Tipe `nominal` → potong sebesar `discount_amount`.
3. Tipe `percentage` → potong sebesar `harga_berjalan × discount_amount / 100`.
4. Besar potongan dibatasi (floored): `min(potongan, harga_berjalan)` supaya harga tidak negatif.

> **Penting untuk frontend:** Jangan hanya menjumlahkan nominal semua voucher. Untuk preview/display yang benar, aplikasikan voucher secara berurutan sesuai urutan `voucher_codes` agar total sesuai dengan perhitungan backend.

Backend menyimpan hasil di `raw_source`:
- `voucher_total_nominal` → total nominal diskon voucher.
- `voucher_value_nominal_per_voucher` → nominal diskon tiap voucher (indeks sesuai urutan `voucher_codes`).

---

## 3. Output Contract — Response Data

File: `@app/Http/Resources/SalesOrderResource.php` (line 25)

**Response sales_order / invoice SUDAH TIDAK PUNYA KEY `voucher` lagi.**

Yang ada sekarang adalah key **`vouchers`** (array). Key ini hanya muncul jika relationship di-load, yaitu dengan `?include=vouchers.category` pada endpoint `show`/`index`.

Bentuknya:

```json
"vouchers": [
  {
    "id": 1,
    "code": "KODE1",
    "category": {
      "id": 1,
      "name": "Nama Kategori",
      "discount_type": "nominal" | "percentage",
      "discount_amount": 10000.0
    }
  }
]
```

### Data tambahan untuk menampilkan besaran diskon
Per-voucher applied discount disimpan di **pivot `sales_order_vouchers.discount_amount`** dan diekspos lewat relasi `vouchers` (bukan append `vouchers_data`, yang sudah dihapus). Setiap item di `vouchers` memuat:

```json
{
  "code": "KODE1",
  "description": "...",
  "pivot": {
    "discount_amount": 10000.0
  }
}
```

Gunakan **`pivot.discount_amount`** pada tiap item untuk menampilkan/menghitung potongan riil voucher tersebut (karena `category.discount_amount` belum tentu sama dengan potongan aktual, terutama untuk tipe percentage dan saat harga di-floored).

### Total diskon voucher (`total_voucher`)

Sales order / invoice memiliki field **`total_voucher`** (integer) — total nominal yang benar-benar terpotong dari semua voucher (sama dengan penjumlahan `vouchers[].pivot.discount_amount`).

Field ini tersedia di:

- **Order tersimpan** (list/detail): kolom persisten di table `sales_orders`, langsung ada di response tanpa perlu load relasi `vouchers` (cocok untuk menampilkan total voucher di grid/list).
- **Preview** (`is_preview = true`): diisi pipeline saat kalkulasi, jadi tetap muncul di response preview sebelum order tersimpan.

---

## 4. Pedoman Implementasi Frontend

1. **Input voucher** → kirim `voucher_codes` sebagai array string pada payload create/update invoice.
2. **Edit invoice** → prefill input voucher dari response `vouchers[].code`.
3. **Preview harga** → aplikasikan diskon voucher secara sequential (bukan sum nominal).
4. **Update** → kirim daftar lengkap yang diinginkan; backend akan `sync` (daftar lama diganti total).
5. **Hapus key `voucher`** dari semua handling data frontend — ganti dengan `vouchers`. Tampilkan potongan tiap voucher dari `vouchers[].pivot.discount_amount`.

---

## 5. Acceptance Criteria

- [ ] Create invoice dengan 2+ voucher berhasil dan diskon dihitung sequential.
- [ ] Update invoice sesuai array `voucher_codes` yang dikirim (voucher lama terganti total).
- [ ] Response berisi key `vouchers` (array) dan TIDAK lagi berisi key `voucher` maupun `vouchers_data`.
- [ ] Frontend menampilkan potongan tiap voucher menggunakan `vouchers[].pivot.discount_amount`.
- [ ] Response (saved maupun preview) memuat field `total_voucher` berisi total potongan semua voucher.
- [ ] Update invoice dengan `voucher_codes: []` menghapus semua voucher lama (pivot ter-sync kosong, `total_voucher` kembali 0).
- [ ] Voucher kadaluwarsa/tidak valid menampilkan pesan error dari backend.
