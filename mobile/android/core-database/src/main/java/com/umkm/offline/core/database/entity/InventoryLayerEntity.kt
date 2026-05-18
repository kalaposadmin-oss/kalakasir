package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "inventory_layers_local")
data class InventoryLayerEntity(
    @PrimaryKey val localId: String,
    val productLocalId: String,
    val qtyOriginal: Double,
    val qtyAvailable: Double,
    val unitCost: Double,
    val sourceType: String,
    val sourceRef: String,
    val receivedAt: Long,
    val updatedAtLocal: Long,
)