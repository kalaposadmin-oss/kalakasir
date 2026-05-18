package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "shifts")
data class ShiftEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val userLocalId: String,
    val branchLocalId: String?,
    val registerLocalId: String?,
    val openedAt: Long,
    val closedAt: Long?,
    val openingCash: Long,
    val closingCash: Long?,
    val status: String,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
