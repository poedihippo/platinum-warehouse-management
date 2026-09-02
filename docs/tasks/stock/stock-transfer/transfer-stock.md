# PRD — Transfer Stock (Frontend)

> **Target:** AI Agent / Frontend Developer (React + Yup)
> **Backend:** Laravel 10 REST API (BEJO WMS)
> **Last Updated:** 2026-08-25

---

## 1. Feature Overview

Transfer Stock adalah fitur untuk memindahkan stok barang antar warehouse. Stok yang dipindahkan harus **product unit yang sama** — hanya berpindah dari warehouse asal ke warehouse tujuan.

Dua mekanisme transfer:

| Mekanisme | Untuk | Input |
|-----------|-------|-------|
| **QR Flow** | Product unit dengan `is_generate_qr = true` | Scan QR codes (stock IDs) |
| **Non-QR Flow** | Product unit dengan `is_generate_qr = false` | Input qty manual |

---

## 2. API Endpoint

```
POST /api/stocks/transfer
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Permission:** `stock_edit`

---

## 3. Request Payload

### 3.1 QR Flow (is_generate_qr = true)

```json
{
  "stock_product_unit_id": 1,
  "to_warehouse_id": 2,
  "stock_ids": [
    "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "01BX5ZZKB9ACTGK9QWQ4V0ZP6Q"
  ],
  "description": "Pindah gudang Q3"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `stock_product_unit_id` | integer | **Yes** | ID StockProductUnit **asal** (source). Menentukan product unit dan warehouse asal. |
| `to_warehouse_id` | integer | **Yes** | ID Warehouse **tujuan**. Harus berbeda dari warehouse asal. |
| `stock_ids` | string[] | **Yes** | Array ULID stocks yang di-scan. Minimal 1 item. |
| `description` | string | No | Catatan transfer. Maks 255 karakter. |

### 3.2 Non-QR Flow (is_generate_qr = false)

```json
{
  "stock_product_unit_id": 1,
  "to_warehouse_id": 2,
  "qty": 50,
  "description": "Pindah gudang Q3"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `stock_product_unit_id` | integer | **Yes** | ID StockProductUnit **asal** (source). Menentukan product unit dan warehouse asal. |
| `to_warehouse_id` | integer | **Yes** | ID Warehouse **tujuan**. Harus berbeda dari warehouse asal. |
| `qty` | integer | **Yes** | Jumlah qty yang dipindahkan. Minimal 1. |
| `description` | string | No | Catatan transfer. Maks 255 karakter. |

### 3.3 Flow Detection

Backend otomatis mendeteksi flow berdasarkan payload:
- Jika `stock_ids` ada → **QR Flow**
- Jika `stock_ids` tidak ada → **Non-QR Flow**

---

## 4. Response

### 4.1 Success Response (200)

```json
{
  "message": "Transfer stock berhasil"
}
```

**Catatan:**
- QR Flow: `data` adalah **array** (bisa multiple StockTransfer records, 1 per stock)
- Non-QR Flow: `data` adalah **object** (1 StockTransfer record)

### 4.2 Error Response (422 / 400)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "stock_ids": [
      "Stock \"01XYZ...\" adalah child. Scan QR parent-nya"
    ]
  }
}
```

---

## 5. Validation Rules & Error Messages

### 5.1 QR Flow Validation (per stock_id)

| Kondisi | Error Key | Error Message |
|---------|-----------|---------------|
| Stock tidak ditemukan | `stock_ids` | Stock tidak ditemukan: {ulid_list} |
| `is_stock = false` | `stock_ids` | Stock "{ulid}" bukan stock aktif |
| `parent_id IS NOT NULL` (child) | `stock_ids` | Stock "{ulid}" adalah child. Scan QR parent-nya |
| `stock_product_unit_id` tidak cocok | `stock_ids` | Stock "{ulid}" tidak sesuai dengan product unit asal |
| Sudah ada di Sales Order aktif | `stock_ids` | Stock "{ulid}" sudah masuk di Sales Order |

### 5.2 General Validation

| Kondisi | Error Key | Error Message |
|---------|-----------|---------------|
| Warehouse tujuan = warehouse asal | `to_warehouse_id` | Warehouse tujuan harus berbeda dengan warehouse asal |
| SPU tujuan tidak ada | `to_warehouse_id` | Stock product unit tujuan tidak ditemukan |
| Product unit bukan QR tapi kirim stock_ids | `stock_product_unit_id` | Product unit ini tidak menggunakan QR. Gunakan qty untuk transfer |
| Qty kurang dari tersedia (non-QR) | `qty` | Qty tidak mencukupi. Tersedia: {qty} |
| Qty kosong (non-QR) | `qty` | Qty wajib diisi untuk transfer non-QR |

---

## 6. Yup Validation Schema

### 6.1 QR Flow

```typescript
import * as yup from 'yup';

const transferStockQrSchema = yup.object({
  stock_product_unit_id: yup
    .number()
    .required('Stock product unit wajib dipilih'),
  to_warehouse_id: yup
    .number()
    .required('Warehouse tujuan wajib dipilih'),
  stock_ids: yup
    .array()
    .of(yup.string().required())
    .min(1, 'Minimal scan 1 QR stock')
    .required('Stock IDs wajib diisi'),
  description: yup
    .string()
    .max(255, 'Maksimal 255 karakter')
    .optional(),
});
```

### 6.2 Non-QR Flow

```typescript
const transferStockNonQrSchema = yup.object({
  stock_product_unit_id: yup
    .number()
    .required('Stock product unit wajib dipilih'),
  to_warehouse_id: yup
    .number()
    .required('Warehouse tujuan wajib dipilih'),
  qty: yup
    .number()
    .min(1, 'Qty minimal 1')
    .required('Qty wajib diisi'),
  description: yup
    .string()
    .max(255, 'Maksimal 255 karakter')
    .optional(),
});
```

### 6.3 Dynamic Schema Selection

```typescript
// Determine which schema to use based on product_unit.is_generate_qr
const schema = productUnit?.is_generate_qr
  ? transferStockQrSchema
  : transferStockNonQrSchema;
```

---

## 7. Helper API Calls

### 7.1 Get Warehouse List

```
GET /api/warehouses
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "Gd-01",
      "name": "Gudang Pusat",
      "company_name": "PT Platinum Adi Sentosa"
    },
    {
      "id": 2,
      "code": "Gd-02",
      "name": "Gudang Utama",
      "company_name": "PT Platinum Adi Sentosa"
    }
  ]
}
```

**Note:** Warehouse yang dipilih sebagai source otomatis di-exclude dari pilihan warehouse tujuan.

### 7.2 Get Stock Product Units (untuk dropdown product unit)

```
GET /api/stocks
```

**Response item:**
```json
{
  "id": 1,
  "qty": 20,
  "product_unit_id": 10,
  "warehouse_id": 1,
  "product_unit": {
    "id": 10,
    "name": "Pakan Ayam 50kg",
    "code": "PA-50KG",
    "is_generate_qr": true,
    "product": {
      "name": "Pakan Ayam",
      "product_brand": { "id": 1, "name": "Brand A" }
    },
    "uom": { "id": 1, "name": "kg" }
  },
  "warehouse": {
    "id": 1,
    "name": "Gudang Pusat"
  },
  "stocks_count": 20
}
```

**Catatan:**
- Field `stocks_count` = jumlah stock aktif (QR) atau `qty` (non-QR)
- Filter by warehouse: `?filter[warehouse_id]={id}`
- Filter by product unit: `?filter[product_unit_id]={id}`

### 7.3 Get Stock Details (QR Flow — untuk validasi scan)

```
GET /api/stocks/details?filter[stock_product_unit_id]={id}&filter[show_all]=1
```

**Response item:**
```json
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "stock_product_unit_id": 1,
  "parent_id": null,
  "is_stock": true,
  "batch_number": "BATCH-001",
  "expired_date": "2027-01-01",
  "scanned_count": 0,
  "childs_count": 0,
  "created_at": "2026-08-25T10:30:00.000000Z"
}
```

**Catatan:**
- `parent_id = null` → stock ini bisa di-transfer (scan QR ini)
- `parent_id != null` → stock ini adalah child, **tolak** — suruh scan parent-nya
- `is_stock = false` → stock ini tidak aktif, **tolak**
- `childs_count > 0` → stock ini adalah parent, qty transfer = 1 + childs_count

### 7.4 View Transfer History (via StockHistory)

```
GET /api/stock-histories?filter[description]=Transfer&sort=-created_at
```

StockHistory records otomatis dibuat setiap kali transfer. Frontend bisa menampilkan riwayat transfer dari sini.

---

## 8. UI/UX Flow

### 8.1 Form Transfer Stock

```
┌─────────────────────────────────────────────────┐
│              Transfer Stock                      │
├─────────────────────────────────────────────────┤
│                                                  │
│  [Step 1: Pilih Product Unit Asal]               │
│  ┌────────────────────────────────────────────┐  │
│  │ Product Unit Asal  *                       │  │
│  │ ┌────────────────────────────────────────┐  │  │
│  │ │ Search product unit...           ▼    │  │  │
│  │ └────────────────────────────────────────┘  │  │
│  │ → Tampilkan: nama, code, brand, warehouse  │  │
│  │ → Otomatis tentukan:                       │  │
│  │   - Warehouse asal (dari SPU)              │  │
│  │   - Apakah QR atau Non-QR                  │  │
│  └────────────────────────────────────────────┘  │
│                                                  │
│  [Step 2: Pilih Warehouse Tujuan]                │
│  ┌────────────────────────────────────────────┐  │
│  │ Warehouse Tujuan  *                        │  │
│  │ ┌────────────────────────────────────────┐  │  │
│  │ │ Select warehouse...             ▼      │  │  │
│  │ └────────────────────────────────────────┘  │  │
│  │ → Exclude warehouse asal dari list          │  │
│  └────────────────────────────────────────────┘  │
│                                                  │
│  [Step 3: Input Transfer]                        │
│                                                  │
│  ┌─── QR Flow ──────────────────────────────┐   │
│  │ ┌────────────────────────────────────────┐  │  │
│  │ │ Scan QR / Masukkan Stock ID            │  │  │
│  │ │ ┌────────────────────────────────────┐  │  │  │
│  │ │ │ Scan QR disini...                  │  │  │  │
│  │ │ └────────────────────────────────────┘  │  │  │
│  │ │                                        │  │  │
│  │ │ Scanned:                               │  │  │
│  │ │ ┌──────────────────────────────────┐   │  │  │
│  │ │ │ 01ARZ3NDE... [x]                │   │  │  │
│  │ │ │ 01BX5ZZKB... [x]                │   │  │  │
│  │ │ │ 01CN7PQWE... [x]                │   │  │  │
│  │ │ └──────────────────────────────────┘   │  │  │
│  │ └────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────┘   │
│                                                  │
│  ┌─── Non-QR Flow ──────────────────────────┐   │
│  │ ┌────────────────────────────────────────┐  │  │
│  │ │ Qty  *                                │  │  │
│  │ │ ┌────────────────────────────────┐     │  │  │
│  │ │ │ 50                             │     │  │  │
│  │ │ └────────────────────────────────┘     │  │  │
│  │ │ Stok tersedia: 100                     │  │  │
│  │ └────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────┘   │
│                                                  │
│  [Step 4: Catatan (Optional)]                    │
│  ┌────────────────────────────────────────────┐  │
│  │ Description                                │  │
│  │ ┌────────────────────────────────────────┐  │  │
│  │ │                                        │  │  │
│  │ └────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────┘  │
│                                                  │
│  ┌────────────────────────────────────────────┐  │
│  │         [Transfer Stock]                   │  │
│  └────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
```

### 8.2 Step-by-Step UX

**Step 1 — Pilih Product Unit Asal**
1. User search dan pilih `stock_product_unit_id`
2. Tampilkan info: nama product, code, brand, warehouse asal, qty tersedia
3. Deteksi flow: jika `product_unit.is_generate_qr = true` → QR Flow, else → Non-QR Flow

**Step 2 — Pilih Warehouse Tujuan**
1. Tampilkan dropdown warehouse (kecuali warehouse asal)
2. Setelah warehouse dipilih, backend akan validasi apakah SPU tujuan ada

**Step 3 — Input Transfer**

*QR Flow:*
1. User scan QR codes satu per satu (atau input manual stock ID)
2. Setelah scan, **validasi di frontend dulu**:
   - `parent_id != null` → **tampilkan error inline**: "Stock ini adalah child. Scan QR parent-nya." → User harus scan ulang
   - `is_stock = false` → tampilkan error: "Stock tidak aktif"
   - Duplikat scan → abaikan / warning
3. Tampilkan list QR yang sudah di-scan dengan tombol remove `[x]`
4. Tampilkan info: "N stocks akan dipindahkan dari {warehouse_asal} ke {warehouse_tujuan}"

*Non-QR Flow:*
1. User input qty
2. Tampilkan info: "Stok tersedia: {qty}"
3. Validasi: qty tidak boleh melebihi stok tersedia

**Step 4 — Submit**
1. Hit `POST /api/stocks/transfer`
2. Handle error response → tampilkan error message di bawah form
3. Success → tampilkan toast/snackbar "Transfer stock berhasil" → redirect ke halaman stock list atau stock history

### 8.3 Error Handling UX

| Error Type | Display |
|-----------|---------|
| Validation error (field) | Tampilkan di bawah field yang terkait |
| Stock is child | Inline error saat scan, reject QR tersebut |
| Same warehouse | Error di field warehouse tujuan |
| Qty exceeds available | Error di field qty |
| Server error (500) | Toast/snackbar error umum |

---

## 9. Business Rules

| Rule | Detail |
|------|--------|
| Product unit harus sama | Transfer hanya antar warehouse, tidak boleh beda product unit |
| Warehouse berbeda | Warehouse asal ≠ warehouse tujuan |
| QR stock child ditolak | `parent_id IS NOT NULL` → harus scan parent |
| Parent stock dipindah beserta childs | Saat parent di-transfer, semua child ikut pindah |
| Stock tidak boleh di SO aktif | Cek `salesOrderItems()->whereNotReturned()` |
| Stock harus aktif | `is_stock = true` |
| Non-QR: qty ≤ stok tersedia | `StockProductUnit.qty ≥ request.qty` |
| Hanya product unit QR yang bisa pakai stock_ids | Backend cek `product_unit.is_generate_qr` |
| Hanya product unit non-QR yang bisa pakai qty | Backend cek `!product_unit.is_generate_qr` |

---

## 10. Transfer History (Read)

Transfer history bisa dilihat melalui **StockHistory** API:

```
GET /api/stock-histories?sort=-created_at
```

Filter opsional:
- `filter[stock_product_unit_id]` — history per product unit
- `filter[start_date]` & `filter[end_date]` — rentang tanggal
- `filter[description]` — search deskripsi (contoh: "Transfer stock")

**StockHistory item terkait transfer:**
- Source warehouse: `is_increment = 0` (decrement), deskripsi: "Transfer stock ke {nama_warehouse_tujuan}"
- Destination warehouse: `is_increment = 1` (increment), deskripsi: "Transfer stock dari {nama_warehouse_asal}"

Tidak perlu membuat halaman terpisah untuk transfer history — cukup tampilkan di halaman StockHistory yang sudah ada.
