package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "purchase_return_items")
data class PurchaseReturnItemEntity(
    @PrimaryKey val localId: String,
    val purchaseLocalId: String,
    val productLocalId: String,
    val qty: Double,
    val unitCost: Long,
    val reason: String,
    val returnedAt: Long,
)
