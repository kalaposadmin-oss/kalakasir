package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "sale_items",
    indices = [Index(value = ["saleLocalId"])]
)
data class SaleItemEntity(
    @PrimaryKey val localId: String,
    val saleLocalId: String,
    val productLocalId: String,
    val qty: Double,
    val unitPrice: Long,
    val lineTotal: Long,
)
