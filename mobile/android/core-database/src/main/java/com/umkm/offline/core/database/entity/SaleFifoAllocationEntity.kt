package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "sale_fifo_allocations")
data class SaleFifoAllocationEntity(
    @PrimaryKey val localId: String,
    val saleLocalId: String,
    val saleItemLocalId: String,
    val productLocalId: String,
    val inventoryLayerLocalId: String,
    val qtyIssued: Double,
    val unitCost: Double,
    val totalCost: Double,
    val allocationOrder: Int,
    val createdAt: Long,
    val reversedAt: Long?,
    val reversalRef: String?,
)