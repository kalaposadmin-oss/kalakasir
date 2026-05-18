package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "products")
data class ProductEntity(
    @PrimaryKey val localId: String,
    val serverId: String?,
    val name: String,
    val categoryName: String,
    val itemType: String? = null,
    val productMode: String? = null,
    val barcode: String?,
    val price: Long,
    val stockOnHand: Double,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
