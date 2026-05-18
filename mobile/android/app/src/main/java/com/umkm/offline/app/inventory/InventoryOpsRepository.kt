package com.umkm.offline.app.inventory

import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.entity.SyncActionEntity
import java.util.UUID

class InventoryOpsRepository(
    private val productDao: ProductDao,
    private val syncActionDao: SyncActionDao,
) {
    suspend fun recordStockAdjustmentOffline(
        productLocalId: String,
        qtyDelta: Double,
        reason: String,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ) {
        val normalizedProductId = productLocalId.trim()
        val normalizedReason = reason.trim().ifBlank { "stock_adjustment" }

        require(normalizedProductId.isNotBlank()) { "Product local id wajib diisi." }
        require(qtyDelta != 0.0) { "Qty adjustment tidak boleh 0." }

        val now = System.currentTimeMillis()
        val product = productDao.findByLocalId(normalizedProductId)
            ?: throw IllegalArgumentException("Produk tidak ditemukan.")

        val nextStock = (product.stockOnHand + qtyDelta).coerceAtLeast(0.0)
        productDao.upsert(
            product.copy(
                stockOnHand = nextStock,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = product.version + 1,
                updatedAtLocal = now,
                updatedAtServer = null,
            ),
        )

        val movementId = UUID.randomUUID().toString()
        syncActionDao.enqueue(
            outboxAction(
                entityType = EntityType.STOCK_MOVEMENT,
                entityId = movementId,
                payloadJson = "{\"stock_movement_local_id\":\"$movementId\",\"product_local_id\":\"$normalizedProductId\",\"direction\":\"${if (qtyDelta > 0) "in" else "out"}\",\"qty\":${kotlin.math.abs(qtyDelta)},\"reason\":\"${escapeJson(normalizedReason)}\",\"source\":\"stock_adjustment\",\"source_module\":\"inventory\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":$approvalRequired}",
                now = now,
            ),
        )
    }

    suspend fun recordStockTransferOffline(
        productLocalId: String,
        qty: Double,
        fromLocation: String,
        toLocation: String,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ) {
        val normalizedProductId = productLocalId.trim()
        val normalizedFrom = fromLocation.trim().ifBlank { "utama" }
        val normalizedTo = toLocation.trim().ifBlank { "cabang" }

        require(normalizedProductId.isNotBlank()) { "Product local id wajib diisi." }
        require(qty > 0.0) { "Qty transfer harus lebih besar dari 0." }

        val now = System.currentTimeMillis()
        val product = productDao.findByLocalId(normalizedProductId)
            ?: throw IllegalArgumentException("Produk tidak ditemukan.")

        if (product.stockOnHand + 1e-9 < qty) {
            throw IllegalArgumentException("Stok tidak cukup untuk transfer.")
        }

        productDao.upsert(
            product.copy(
                stockOnHand = (product.stockOnHand - qty).coerceAtLeast(0.0),
                syncStatus = SyncStatus.PENDING_SYNC,
                version = product.version + 1,
                updatedAtLocal = now,
                updatedAtServer = null,
            ),
        )

        val movementId = UUID.randomUUID().toString()
        syncActionDao.enqueue(
            outboxAction(
                entityType = EntityType.STOCK_MOVEMENT,
                entityId = movementId,
                payloadJson = "{\"stock_movement_local_id\":\"$movementId\",\"product_local_id\":\"$normalizedProductId\",\"direction\":\"out\",\"qty\":$qty,\"reason\":\"stock_transfer\",\"from_location\":\"${escapeJson(normalizedFrom)}\",\"to_location\":\"${escapeJson(normalizedTo)}\",\"source_module\":\"inventory\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":$approvalRequired}",
                now = now,
            ),
        )
    }

    private fun outboxAction(
        entityType: EntityType,
        entityId: String,
        payloadJson: String,
        now: Long,
    ): SyncActionEntity {
        return SyncActionEntity(
            actionId = UUID.randomUUID().toString(),
            entityType = entityType,
            entityId = entityId,
            actionType = SyncActionType.RECORD_STOCK_MOVEMENT,
            payloadJson = payloadJson,
            idempotencyKey = "stock-movement-$entityId",
            retryCount = 0,
            nextRetryTime = now,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = now,
        )
    }

    private fun escapeJson(value: String): String {
        return value.replace("\\", "\\\\").replace("\"", "\\\"")
    }
}
