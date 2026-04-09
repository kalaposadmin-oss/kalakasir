# Backend POS - Standar Response & Error

Dokumen ini menjelaskan standar response API dan error untuk backend POS.

## 1) Format Sukses
```json
{
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 120,
    "last_page": 6
  }
}
```

Catatan:
- `meta` bersifat opsional dan hanya dikirim ketika ada informasi tambahan (mis. pagination).
- Payload `data` berisi objek atau list sesuai endpoint.

## 2) Format Error
```json
{
  "code": "SALE_STOCK_INSUFFICIENT",
  "message": "Stok tidak cukup",
  "details": { "variant_id": 101 },
  "request_id": "req-123"
}
```

## 3) HTTP Status Code
- 400 BAD_REQUEST
- 401 UNAUTHORIZED
- 403 FORBIDDEN
- 404 NOT_FOUND
- 409 CONFLICT
- 422 VALIDATION_ERROR
- 500 INTERNAL_ERROR

## 4) Error Codes Utama (Contoh)
- AUTH_INVALID_CREDENTIALS
- AUTH_TOKEN_EXPIRED
- OUTLET_ACCESS_DENIED
- REGISTER_INACTIVE
- SALE_ALREADY_PAID
- SALE_STOCK_INSUFFICIENT
- PAYMENT_MISMATCH
- REFUND_NOT_ALLOWED
- PURCHASE_ALREADY_RECEIVED
- VALIDATION_ERROR

## 5) Aturan Validasi Umum
- Semua ID harus milik tenant yang sama.
- Semua request transaksi wajib membawa outlet_id dan register_id.
- Harga, diskon, promo dihitung ulang di backend.
- Perubahan stok hanya melalui stock_movements.

## 6) Format List (GET)
```json
{
  "data": {
    "items": [ ... ]
  },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 120,
    "last_page": 6
  }
}
```

Catatan:
- Nama key list mengikuti domain endpoint, contoh `products`, `customers`, `logs`.
- Pagination muncul jika endpoint mendukung `page`/`per_page`.

## 7) Traceability
- Semua request API sebaiknya menyertakan header `X-Request-Id`.
- Response error menyertakan `request_id` untuk troubleshooting.
