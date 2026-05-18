package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "suppliers")
data class SupplierEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val name: String,
    val phone: String?,
    val address: String?,
    val notes: String?,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
