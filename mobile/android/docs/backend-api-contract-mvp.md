# Backend API Contract MVP (Android Offline-First)

Dokumen ini hanya kontrak integrasi untuk backend Laravel. Tidak ada perubahan kode backend pada fase ini.

## Tujuan

- Menjaga alur POS tetap jalan saat offline.
- Menstandarkan payload outbox dan pull changes agar sinkronisasi aman (idempotent + retryable).
- Menjadi acuan tim Android dan backend untuk implementasi paralel.

## Prinsip Kontrak

- Semua request menggunakan JSON.
- Semua endpoint private memakai `Authorization: Bearer <token>`.
- Semua action push wajib kirim `Idempotency-Key` pada header.
- Server harus mengembalikan response deterministik untuk key yang sama.
- Waktu server menggunakan epoch milliseconds UTC.

## Endpoint Ringkas

### 1) Login Kasir

- Method: `POST`
- Path: `/api/v1/auth/login`
- Request:

```json
{
  "store_code": "TOKO-A",
  "pin": "1234",
  "device_id": "android-unique-device-id"
}
```

- Response 200:

```json
{
  "access_token": "jwt-or-opaque-token",
  "token_type": "Bearer",
  "expires_in": 3600,
  "cashier": {
    "id": "cshr_001",
    "name": "Kasir Pagi"
  },
  "store": {
    "id": "str_001",
    "code": "TOKO-A",
    "name": "Toko A"
  }
}
```

### 2) Push Satu Action Outbox

- Method: `POST`
- Path: `/api/v1/sync/actions`
- Header wajib:
  - `Authorization: Bearer ...`
  - `Idempotency-Key: <uuid>`
- Request:

```json
{
  "action_id": "act_001",
  "entity_type": "SALE",
  "entity_id": "sale_local_001",
  "action_type": "FINALIZE_SALE",
  "payload": {
    "sale_local_id": "sale_local_001",
    "receipt_no": "TRX-1730000000000",
    "total": 48000
  },
  "client_ts": 1730000000000
}
```

- Response 200 (sukses):

```json
{
  "status": "ok",
  "server_event_id": "evt_001",
  "entity": {
    "entity_type": "SALE",
    "local_id": "sale_local_001",
    "server_id": "sale_srv_001",
    "server_version": 3,
    "updated_at_server": 1730000005000
  }
}
```

- Response 409 (konflik bisnis):

```json
{
  "status": "conflict",
  "code": "SALE_ALREADY_VOIDED",
  "message": "Sale sudah void di server",
  "server_state": {
    "sale_id": "sale_srv_001",
    "status": "VOID"
  }
}
```

### 3) Pull Perubahan Server

- Method: `POST`
- Path: `/api/v1/sync/pull`
- Request:

```json
{
  "cursor": "cursor-previous-value",
  "limit": 200
}
```

- Response 200:

```json
{
  "next_cursor": "cursor-new-value",
  "changes": [
    {
      "entity_type": "PRODUCT",
      "server_id": "prd_001",
      "operation": "UPSERT",
      "server_version": 10,
      "updated_at_server": 1730000010000,
      "data": {
        "name": "Kopi Gula Aren",
        "price": 18000,
        "stock_on_hand": 118.0
      }
    },
    {
      "entity_type": "PRODUCT",
      "server_id": "prd_002",
      "operation": "DELETE",
      "server_version": 11,
      "updated_at_server": 1730000011000,
      "data": null
    }
  ]
}
```

## Entity dan Action yang Dipakai MVP Android

- `entity_type`:
  - `SALE`
  - `PAYMENT`
  - `PRODUCT`
- `action_type`:
  - `FINALIZE_SALE`
  - `RECORD_PAYMENT`
  - `ADJUST_STOCK`

## Aturan Idempotency

- Scope idempotency minimal: `store_id + idempotency_key`.
- Jika request diulang dengan key sama:
  - server mengembalikan hasil yang sama seperti request pertama,
  - tidak membuat duplikasi sale/payment/event.

## Kode Error Minimum

- `401 UNAUTHORIZED`: token invalid/expired.
- `422 VALIDATION_ERROR`: payload tidak valid.
- `409 CONFLICT`: state server bertentangan dengan action client.
- `429 RATE_LIMITED`: backend overload, client retry dengan backoff.
- `500 INTERNAL_ERROR`: error internal backend.

Format error disarankan:

```json
{
  "status": "error",
  "code": "VALIDATION_ERROR",
  "message": "Field receipt_no wajib diisi",
  "details": {
    "receipt_no": ["required"]
  }
}
```

## Kontrak Retry Android

- Android hanya retry action dengan status transport error atau `5xx`.
- Android tidak retry otomatis untuk `4xx` kecuali `429`.
- Backoff minimum Android saat ini: `15s * 2^retryCount`, capped di level retry tertentu.

## Acceptance Criteria Integrasi

- Push action yang sama 3x dengan `Idempotency-Key` sama tetap menghasilkan 1 transaksi di server.
- Pull changes dengan cursor lama mengembalikan delta konsisten dan `next_cursor` valid.
- Konflik bisnis mengembalikan `409` + `code` + `server_state`.
- Semua timestamp server berupa epoch milliseconds.

## Catatan Implementasi Laravel (referensi)

- Header idempotency wajib tervalidasi di middleware.
- Simpan tabel jejak idempotency untuk replay response.
- Endpoint pull sebaiknya berbasis event log/cursor monotonic agar aman untuk resume.
