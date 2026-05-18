package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "purchase_items")
data class PurchaseItemEntity(
    @PrimaryKey val localId: String,
    val purchaseLocalId: String,
    val productLocalId: String,
    val qty: Double,
    val unitCost: Long,
    val lineTotal: Long,
    val receivedQty: Double? = null,
    val remainingQty: Double? = null,
)
