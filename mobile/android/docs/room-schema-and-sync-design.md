# Room Schema and Sync Engine Design

## Entitas Utama
- `products`
- `customers`
- `sales`
- `sale_items`
- `payments`
- `stock_movements`
- `shifts`
- `sync_actions_outbox`

## Metadata Sinkron per Entitas
- `local_id` (TEXT UUID)
- `server_id` (TEXT nullable)
- `sync_status` (TEXT)
- `version` (INTEGER)
- `updated_at_local` (INTEGER epoch millis)
- `updated_at_server` (INTEGER nullable)

## Outbox
Tabel: `sync_actions_outbox`
- `action_id` TEXT PRIMARY KEY
- `entity_type` TEXT
- `entity_id` TEXT
- `action_type` TEXT
- `payload_json` TEXT
- `idempotency_key` TEXT
- `retry_count` INTEGER
- `next_retry_time` INTEGER
- `created_at` INTEGER

## WorkManager Strategy
- Worker 1: PushOutboxWorker
- Worker 2: PullServerChangesWorker
- Orchestrator: SyncOrchestratorWorker

Urutan:
1. Push action yang due.
2. Update status lokal berdasarkan respons server.
3. Pull delta server berbasis cursor.
4. Persist cursor baru.

## Retry Policy
- Error jaringan/5xx: exponential backoff.
- Error 4xx validasi: tandai `FAILED`.
- Conflict 409: tandai `CONFLICT`.
