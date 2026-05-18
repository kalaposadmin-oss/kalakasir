package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "stock_movements")
data class StockMovementEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val productLocalId: String,
    val direction: String,
    val qty: Double,
    val reason: String,
    val movedAt: Long,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
