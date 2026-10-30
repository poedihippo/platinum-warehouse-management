# BACKEND DOCUMENTATION — Transfer Stock v3 (Grouping Integrity)

> **Target:** AI Agent / Backend Developer
> **Framework:** Laravel 10 REST API (BEJO WMS)
> **Feature Status:** Implemented — v3 (Agustus 2026)
> **Related docs:** `backend-flow-v1.md` (v1) · `backend-flow-v2.md` (v2) · `transfer-stock.md` (PRD frontend)

---

## 1. Tujuan Dokumen

Dokumen ini adalah **addendum delta** dari v2. Bagian yang tidak berubah (schema, route, permission, flow non-QR, StockHistory, response) tetap mengacu ke `backend-flow-v2.md`.

Yang dibahas di sini hanya perubahan v2 → v3:

1. **Transfer Stock menolak child stock** (stock yang masih berada di dalam grouping).
2. **Delivery Order verification menolak grouping yang tidak lengkap.**

Keduanya saling berkaitan: keduanya menjaga agar grouping stock tidak pernah terpecah antar warehouse / product unit.

---

## 2. Changelog (v2 → v3)

| # | Aspek | v2 | v3 (sekarang) |
|---|-------|----|---------------|
| 1 | Child stock (`parent_id != null`) di-transfer | **Diizinkan** — hanya child itu yang pindah | **Ditolak** — harus ungroup dulu |
| 2 | Validasi `parent_id` di `TransferStockRequest` | Tidak ada | **Ada**, dicek paling awal di loop |
| 3 | Validasi `is_stock` | Tidak ada | **Tetap tidak ada** |
| 4 | Parent stock di-scan | Parent + semua childs pindah | **Tidak berubah** |
| 5 | Stock individual di-scan | Pindah sendiri, qty 1 | **Tidak berubah** |
| 6 | Jalur verifikasi stock (DO, SO, Invoice, marketplace) | Tidak cek konsistensi grouping | **Cek `stock_product_unit_id` seluruh childs = parent**, satu sumber di `Stock::hasIncompleteGrouping()` |
| 7 | Update query di `StockTransferController@transferQr` | Kondisional (`childCount > 0` vs `else`) | **Tidak berubah** |

---

## 3. Alasan Perubahan

v2 mengizinkan child stock dipindah mandiri. Konsekuensinya:

```
Parent (SPU Warehouse A)
├── C1 (SPU Warehouse A)
├── C2 ──► dipindah ke Warehouse B (SPU berubah)
└── C3 (SPU Warehouse A)
```

Grouping menjadi **pecah**: satu child berada di warehouse/product unit berbeda dari parent dan sibling-nya. Saat parent di-scan pada Delivery Order verification, child yang sudah pindah tetap ikut terverifikasi sebagai bagian grup — padahal fisiknya ada di warehouse lain.

v3 menutup kedua celah:

- **Input**: child tidak bisa lagi dipindah selama masih menjadi anggota grouping.
- **Output**: jika data lama terlanjur pecah, verification menolaknya.

---

## 4. Aturan Transfer Stock (v3)

| Kondisi | `parent_id` | Punya childs | Transfer | Efek |
|---------|-------------|--------------|----------|------|
| **Individual** | `null` | tidak | ✅ Boleh | Hanya stock itu pindah, `qty = 1` |
| **Parent / grouping** | `null` | ya | ✅ Boleh | Parent + semua childs pindah, `qty = childs_count` |
| **Child** (anggota grouping) | terisi | tidak | ❌ Ditolak | Harus ungroup dulu |

### Alur yang benar

```
Grouping utuh (P + C1 + C2 + C3)
      │
      ├─ Pindah SEMUA     → scan label P saja                → 1 transfer, qty 3
      │
      └─ Pindah SEBAGIAN  → POST /stocks/{P}/ungrouping
                            → C1, C2, C3 jadi parent_id = null
                            → scan C1 / C2 → transfer per stock
```

### Catatan penting: parent + child dalam satu payload

Setiap stock punya QR sendiri (termasuk parent dan childs). Jika operator memindai **parent dan childs sekaligus**, request **ditolak** karena ada child di `stock_ids`.

Frontend harus mengirim **hanya baris parent** ketika memindahkan satu grouping utuh. Ini juga mencegah StockTransfer duplikat (masalah yang tercatat sebagai known issue v2 aturan #12).

---

## 5. Validasi `TransferStockRequest@validateStockIds()`

Urutan cek:

| # | Cek | Error key | Pesan |
|---|-----|-----------|-------|
| 1 | Stock ditemukan | `stock_ids` | `Stock tidak ditemukan: {ulid_list}` (dicek sebelum loop) |
| 2 | **`parent_id` harus `null`** (batch, sebelum loop) | `stock_ids` | `Stock berikut masih di dalam grouping: {ulid_list}. Lakukan ungroup terlebih dahulu` |
| 3 | `stock_product_unit_id` = SPU asal | `stock_ids` | `Stock "{ulid}" tidak sesuai dengan product unit asal` |
| 4 | Belum masuk Sales Order aktif | `stock_ids` | `Stock "{ulid}" sudah masuk di Sales Order` |

### Implementasi

```php
$groupedIds = $stocks->whereNotNull('parent_id')->pluck('id');

if ($groupedIds->isNotEmpty()) {
    $validator->errors()->add('stock_ids', 'Stock berikut masih di dalam grouping: '.$groupedIds->implode(', ').'. Lakukan ungroup terlebih dahulu');

    return;
}

foreach ($stocks as $stock) {
    if ($stock->stock_product_unit_id != $fromSpuId) { /* ... */ }

    if ($stock->salesOrderItems()->whereNotReturned()->exists()) { /* ... */ }
}
```

Cek grouping dilakukan **sekali untuk seluruh payload** (bukan per stock) karena bersifat struktural: menentukan apakah stock boleh dipindah sama sekali, sebelum menilai kesesuaian SPU atau status Sales Order. Semua ULID yang masih child dilaporkan sekaligus, sehingga user tidak perlu mencoba berulang kali.

### Perilaku frontend (`/stock-product/transfer`)

Halaman `src/screens/Stock/TransferStock.tsx` (repo frontend) menyesuaikan diri dengan aturan ini:

| Perilaku | Detail |
|----------|--------|
| Dedupe scan | ULID yang sudah ada di daftar ditolak dengan toast `Stock sudah ada di daftar` |
| Pesan error | Toast memakai pesan field pertama dari backend, jadi pesan grouping langsung terbaca (bukan `The given data was invalid.`); pesan yang sama juga tampil inline di bawah input scan |
| CTA ungroup | Saat `stock_ids` error, muncul tombol `Kembali ke daftar stock untuk ungroup` (`navigate(-1)`) |
| Validasi QR kosong | `qrSchema` mewajibkan minimal 1 `stock_ids` |
| Ringkasan | Menampilkan `{n} QR terpilih` (bukan jumlah stock) karena scan parent memindahkan seluruh childs |


### Mengapa tidak perlu cek `parent_id` di controller

Setelah validasi, `stock_ids` dijamin hanya berisi stock dengan `parent_id = null`. Di `transferQr()`, `$stock->childs()->count()` sudah cukup membedakan:

- `childCount > 0` → parent, update parent + childs
- `childCount == 0` → individual, update dirinya sendiri

`StockTransferController` **tidak diubah** pada v3.

### Endpoint ungroup

`StockController@ungrouping` (`Stock::where('parent_id', $parent->id)->update(['parent_id' => null])` lalu force-delete parent). Setelah ungroup, seluruh anggota grouping berstatus individual dan bisa di-transfer satu per satu.

### Edge case

Parent yang semua childs-nya sudah soft-deleted dianggap **individual** (`childs()` mengecualikan soft-deleted), sehingga `childCount == 0` dan tetap bisa di-transfer. Tidak ada false error.

---

## 6. Cek Kelengkapan Grouping pada Verifikasi Stock

Berlaku di **dua jalur** verifikasi:

| Jalur | Entry point | Implementasi |
|-------|-------------|--------------|
| Delivery Order | `DeliveryOrderController@verification` | setelah `$totalChilds` dihitung, di dalam blok `if ($totalChilds > 0)` |
| Sales Order · Invoice · Marketplace | `SalesOrderService::verifyStockItem` (dipakai `SalesOrderController@verification` dan `InvoiceController@verification`) | setelah `childs` di-load, sebelum `DB::transaction` |

### Sumber tunggal logika

`app/Models/Stock.php`:

```php
public const INCOMPLETE_GROUPING_MESSAGE = 'Stock grouping tidak lengkap. Terdapat child stock yang berada di product unit / warehouse berbeda dari parent-nya';

public function hasIncompleteGrouping(): bool
{
    return $this->childs->contains(
        fn ($child) => $child->stock_product_unit_id != $this->stock_product_unit_id
    );
}
```

### Perubahan per jalur

1. Relasi `childs` ikut memuat `stock_product_unit_id` (hanya 3 kolom, tanpa query tambahan):

```php
$stock->load(['childs' => fn ($q) => $q->select('id', 'parent_id', 'stock_product_unit_id')]);
```

2. Cek konsistensi SPU sebelum transaksi/insert:

```php
if ($stock->hasIncompleteGrouping()) {
    // Delivery Order
    return response()->json(['message' => Stock::INCOMPLETE_GROUPING_MESSAGE], 400);

    // Sales Order / Invoice / Marketplace
    throw new BadRequestHttpException(Stock::INCOMPLETE_GROUPING_MESSAGE);
}
```

### Sifat pengecekan

| Aspek | Detail |
|-------|--------|
| Scope | Hanya untuk stock grouping (stock dengan childs) |
| Pembanding | `$stock->stock_product_unit_id` — parent sudah tervalidasi berada di warehouse & product unit SO detail |
| Alasan logis | Child yang berbeda SPU berarti sudah berpindah warehouse/product unit → grouping pecah |
| Efisiensi | `contains()` pada collection yang sudah ter-load (short-circuit), tanpa query N+1 |
| Atomisitas | Dicek sebelum `DB::transaction`, sehingga tidak ada data setengah tersimpan |
| Status code | `400` (kedua jalur) |
| Perbandingan | Loose (`!=`) — parent dan child berasal dari koneksi & kolom yang sama, aman dari mismatch tipe |
| Pesan | Satu sumber: `Stock::INCOMPLETE_GROUPING_MESSAGE` |

Stock individual tidak terpengaruh karena `childs` kosong → `contains()` selalu `false`.

Cek ini adalah **lapisan pengaman (defense in depth)**: setelah v3, skenario grouping pecah seharusnya tidak bisa terjadi lagi lewat transfer stock, tetapi data lama yang terlanjur pecah tetap ditolak saat verifikasi.

---

## 7. Testing Checklist (v3)

### Transfer Stock — valid

- [ ] Scan 1 stock individual (`parent_id = null`, tanpa childs) → sukses, qty 1
- [ ] Scan 1 parent grouping → sukses, parent + semua childs pindah, qty = childs_count
- [ ] Scan beberapa stock individual sekaligus → sukses, satu StockTransfer per stock
- [ ] Scan stock `is_stock = false` → sukses
- [ ] Setiap transfer menghasilkan 2 StockHistory (decrement asal + increment tujuan)

### Transfer Stock — ditolak

- [ ] Scan 1 child stock → `Stock berikut masih di dalam grouping: {ulid}. Lakukan ungroup terlebih dahulu`
- [ ] Scan beberapa child sekaligus → semua ULID dilaporkan dalam satu pesan
- [ ] Scan parent + salah satu child → ditolak
- [ ] Scan parent + semua childs → ditolak
- [ ] Scan child yang sudah di-ungroup (kini `parent_id = null`) → sukses
- [ ] ULID tidak dikenal → `Stock tidak ditemukan`
- [ ] Stock beda SPU → `tidak sesuai dengan product unit asal`
- [ ] Stock sudah di Sales Order aktif → `sudah masuk di Sales Order`

### Delivery Order Verification

- [ ] Scan parent dengan seluruh childs di SPU sama → sukses, item terverifikasi
- [ ] Scan parent dengan 1 child di SPU berbeda → `400` + pesan grouping tidak lengkap, **tidak ada** data tersimpan
- [ ] Scan stock individual → tidak terpengaruh cek grouping
- [ ] Scan parent yang semua childs-nya SPU sama tetapi qty melebihi DO → tetap kena validasi qty

### Sales Order / Invoice / Marketplace Verification

- [ ] `POST /sales-orders/{so}/verification/{detail}` scan parent grouping utuh → sukses, parent + childs tersimpan
- [ ] `POST /sales-orders/{so}/verification/{detail}` scan parent dengan child beda SPU → `400` + pesan grouping tidak lengkap, tidak ada `sales_order_item` tersimpan
- [ ] `POST /invoices/{so}/verification/{detail}` dengan kondisi yang sama → perilaku identik
- [ ] Scan stock individual pada kedua endpoint → tetap sukses

### Regresi

- [ ] Product unit harus sama; warehouse asal ≠ tujuan
- [ ] Non-QR flow (`qty`) tidak terpengaruh perubahan ini
