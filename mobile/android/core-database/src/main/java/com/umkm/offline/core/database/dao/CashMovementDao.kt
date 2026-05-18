package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.CashMovementEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface CashMovementDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(item: CashMovementEntity)

    @Query("SELECT * FROM cash_movements WHERE shiftLocalId = :shiftLocalId ORDER BY createdAt DESC")
    fun observeByShift(shiftLocalId: String): Flow<List<CashMovementEntity>>

    @Query("SELECT COALESCE(SUM(amount), 0) FROM cash_movements WHERE shiftLocalId = :shiftLocalId AND lower(direction) = 'in'")
    suspend fun sumCashInByShift(shiftLocalId: String): Long

    @Query("SELECT COALESCE(SUM(amount), 0) FROM cash_movements WHERE shiftLocalId = :shiftLocalId AND lower(direction) = 'out'")
    suspend fun sumCashOutByShift(shiftLocalId: String): Long
}
