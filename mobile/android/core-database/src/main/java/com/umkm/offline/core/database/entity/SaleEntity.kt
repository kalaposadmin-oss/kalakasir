package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "sales")
data class SaleEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val receiptNo: String,
    val customerLocalId: String?,
    val subtotal: Long,
    val discountAmount: Long,
    val totalAmount: Long,
    val finalizedAt: Long,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
