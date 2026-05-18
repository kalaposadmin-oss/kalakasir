package com.umkm.offline.app

import com.umkm.offline.core.database.dao.AuditEventDao
import com.umkm.offline.core.database.entity.AuditEventEntity
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

const val POLICY_WRITE_LOCK_ENABLED = "write_lock_enabled"
const val POLICY_TAX_PERCENT = "tax_percent"
const val POLICY_GOV_CURRENT_ROLE = "gov_current_role"
const val POLICY_GOV_VOID_REQUIRES_APPROVAL = "gov_void_requires_approval"
const val POLICY_GOV_STOCK_ADJUSTMENT_REQUIRES_APPROVAL = "gov_stock_adjustment_requires_approval"
const val POLICY_GOV_STOCK_TRANSFER_REQUIRES_APPROVAL = "gov_stock_transfer_requires_approval"
const val POLICY_GOV_CASH_MOVEMENT_REQUIRES_APPROVAL = "gov_cash_movement_requires_approval"
const val POLICY_GOV_DAILY_SNAPSHOT_REQUIRES_APPROVAL = "gov_daily_snapshot_requires_approval"
const val POLICY_GOV_PURCHASE_MUTATION_REQUIRES_APPROVAL = "gov_purchase_mutation_requires_approval"
const val POLICY_ALLOW_NEGATIVE_RAW_MATERIAL_STOCK = "allow_negative_raw_material_stock"

fun formatAuditEntry(timestamp: Long, message: String): String {
    val stamp = SimpleDateFormat("dd/MM HH:mm", Locale("id", "ID")).format(Date(timestamp))
    return "$stamp - $message"
}

suspend fun appendAuditEvent(
    auditEventDao: AuditEventDao,
    message: String,
    category: String = "governance",
) {
    val now = System.currentTimeMillis()
    auditEventDao.insert(
        AuditEventEntity(
            category = category,
            message = message,
            createdAt = now,
        ),
    )
}
