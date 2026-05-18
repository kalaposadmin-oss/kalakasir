package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "payments")
data class PaymentEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val saleLocalId: String,
    val method: String,
    val amount: Long,
    val paidAt: Long,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
