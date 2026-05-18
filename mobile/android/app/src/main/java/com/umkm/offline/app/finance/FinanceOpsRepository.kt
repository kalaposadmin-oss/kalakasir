package com.umkm.offline.app.finance

import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.CashMovementDao
import com.umkm.offline.core.database.dao.DailyReportDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.entity.CashMovementEntity
import com.umkm.offline.core.database.entity.DailyReportEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.UUID

class FinanceOpsRepository(
    private val cashMovementDao: CashMovementDao,
    private val dailyReportDao: DailyReportDao,
    private val syncActionDao: SyncActionDao,
) {
    suspend fun recordCashMovementOffline(
        shiftLocalId: String,
        direction: String,
        amount: Long,
        note: String?,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): String {
        val normalizedShift = shiftLocalId.trim().ifBlank { "manual-shift" }
        val normalizedDirection = direction.trim().lowercase(Locale.ROOT)
        require(normalizedDirection == "in" || normalizedDirection == "out") { "Arah kas wajib in/out." }
        require(amount > 0) { "Nominal kas harus lebih besar dari 0." }

        val now = System.currentTimeMillis()
        val movementId = UUID.randomUUID().toString()
        cashMovementDao.upsert(
            CashMovementEntity(
                localId = movementId,
                serverId = null,
                shiftLocalId = normalizedShift,
                direction = normalizedDirection,
                amount = amount,
                note = note?.trim()?.ifBlank { null },
                createdAt = now,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = 1,
                updatedAtLocal = now,
                updatedAtServer = null,
            ),
        )

        syncActionDao.enqueue(
            outbox(
                entityType = EntityType.CASH_MOVEMENT,
                entityId = movementId,
                actionType = SyncActionType.UPSERT_CASH_MOVEMENT,
                payloadJson = "{\"cash_movement_local_id\":\"$movementId\",\"shift_local_id\":\"$normalizedShift\",\"direction\":\"$normalizedDirection\",\"amount\":$amount,\"note\":\"${escapeJson(note ?: "")}\",\"source_module\":\"finance\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":$approvalRequired}",
                now = now,
            ),
        )

        return movementId
    }

    suspend fun snapshotDailyReportOffline(
        salesTotal: Long,
        transactionCount: Int,
        grossProfitEstimate: Long,
        cashInTotal: Long,
        cashOutTotal: Long,
        stockValueEstimate: Long,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): String {
        val now = System.currentTimeMillis()
        val reportDate = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date(now))

        dailyReportDao.upsert(
            DailyReportEntity(
                reportDate = reportDate,
                salesTotal = salesTotal,
                transactionCount = transactionCount,
                grossProfitEstimate = grossProfitEstimate,
                cashInTotal = cashInTotal,
                cashOutTotal = cashOutTotal,
                stockValueEstimate = stockValueEstimate,
                syncStatus = SyncStatus.PENDING_SYNC,
                updatedAtLocal = now,
                updatedAtServer = null,
            ),
        )

        syncActionDao.enqueue(
            outbox(
                entityType = EntityType.DAILY_REPORT,
                entityId = reportDate,
                actionType = SyncActionType.UPSERT_DAILY_REPORT,
                payloadJson = "{\"report_date\":\"$reportDate\",\"sales_total\":$salesTotal,\"transaction_count\":$transactionCount,\"gross_profit_estimate\":$grossProfitEstimate,\"cash_in_total\":$cashInTotal,\"cash_out_total\":$cashOutTotal,\"stock_value_estimate\":$stockValueEstimate,\"source_module\":\"finance\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":$approvalRequired}",
                now = now,
            ),
        )

        return reportDate
    }

    private fun outbox(
        entityType: EntityType,
        entityId: String,
        actionType: SyncActionType,
        payloadJson: String,
        now: Long,
    ): SyncActionEntity {
        return SyncActionEntity(
            actionId = UUID.randomUUID().toString(),
            entityType = entityType,
            entityId = entityId,
            actionType = actionType,
            payloadJson = payloadJson,
            idempotencyKey = "${actionType.name.lowercase(Locale.ROOT)}-$entityId",
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
