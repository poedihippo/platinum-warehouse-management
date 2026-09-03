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
Model SalesOrder memiliki **append attribute `vouchers_data`** (lihat `@app/Models/SalesOrder.php` line 152) yang berisi detail diskon per voucher yang sudah dihitung backend:

```json
"vouchers_data": [
  {
    "code": "KODE1",
    "type": "nominal" | "percentage",
    "value": 10000.0,        // nilai face voucher (bisa nominal/config persen)
    "nominal": 10000.0        // nominal yang benar-benar terpotong untuk voucher ini
  }
]
```

Gunakan key **`nominal`** untuk menampilkan/menghitung potongan riil tiap voucher (karena `value` belum tentu sama dengan potongan aktual, terutama untuk tipe percentage dan saat harga di-floored).

---

## 4. Pedoman Implementasi Frontend

1. **Input voucher** → kirim `voucher_codes` sebagai array string pada payload create/update invoice.
2. **Edit invoice** → prefill input voucher dari response `vouchers[].code`.
3. **Preview harga** → aplikasikan diskon voucher secara sequential (bukan sum nominal), gunakan `vouchers_data[].nominal` jika tersedia untuk memastikan cocok dengan backend.
4. **Update** → kirim daftar lengkap yang diinginkan; backend akan `sync` (daftar lama diganti total).
5. **Hapus key `voucher`** dari semua handling data frontend — ganti dengan `vouchers` (dan opsional `vouchers_data`).

---

## 5. Acceptance Criteria

- [ ] Create invoice dengan 2+ voucher berhasil dan diskon dihitung sequential.
- [ ] Update invoice sesuai array `voucher_codes` yang dikirim (voucher lama terganti total).
- [ ] Response berisi key `vouchers` (array) dan TIDAK lagi berisi key `voucher`.
- [ ] Frontend menampilkan potongan tiap voucher menggunakan `vouchers_data[].nominal`.
- [ ] Voucher kadaluwarsa/tidak valid menampilkan pesan error dari backend.
