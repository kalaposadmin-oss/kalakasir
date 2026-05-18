package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "purchases")
data class PurchaseEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val purchaseNo: String,
    val supplierLocalId: String?,
    val subtotal: Long,
    val discountAmount: Long,
    val totalAmount: Long,
    val status: String,
    val purchasedAt: Long,
    val dueDate: Long?,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
