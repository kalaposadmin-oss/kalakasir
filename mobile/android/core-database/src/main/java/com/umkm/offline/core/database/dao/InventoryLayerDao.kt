package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.InventoryLayerEntity

@Dao
interface InventoryLayerDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(layer: InventoryLayerEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(layers: List<InventoryLayerEntity>)

    @Query("SELECT * FROM inventory_layers_local WHERE productLocalId = :productLocalId AND qtyAvailable > 0 ORDER BY receivedAt ASC, localId ASC")
    suspend fun findAvailableLayersByProduct(productLocalId: String): List<InventoryLayerEntity>

    @Query("SELECT * FROM inventory_layers_local WHERE localId = :layerLocalId LIMIT 1")
    suspend fun findByLocalId(layerLocalId: String): InventoryLayerEntity?
}