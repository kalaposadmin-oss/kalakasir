package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.StockMovementEntity

@Dao
interface StockMovementDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(movements: List<StockMovementEntity>)

    @Query("SELECT * FROM stock_movements WHERE localId = :localId LIMIT 1")
    suspend fun findByLocalId(localId: String): StockMovementEntity?
}