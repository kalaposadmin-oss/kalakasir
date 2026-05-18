package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "cash_movements")
data class CashMovementEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val shiftLocalId: String,
    val direction: String,
    val amount: Long,
    val note: String?,
    val createdAt: Long,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
