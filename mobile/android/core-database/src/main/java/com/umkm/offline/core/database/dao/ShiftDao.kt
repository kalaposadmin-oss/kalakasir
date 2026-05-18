package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.ShiftEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface ShiftDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(shift: ShiftEntity)

    @Query("SELECT * FROM shifts WHERE status = 'OPEN' ORDER BY openedAt DESC")
    suspend fun getOpenShifts(): List<ShiftEntity>

    @Query("SELECT * FROM shifts WHERE status = 'OPEN' ORDER BY openedAt DESC LIMIT 1")
    suspend fun getActiveShift(): ShiftEntity?

    @Query("SELECT * FROM shifts WHERE localId = :localId LIMIT 1")
    suspend fun getByLocalId(localId: String): ShiftEntity?

    @Query("SELECT * FROM shifts WHERE status = 'OPEN' ORDER BY openedAt DESC LIMIT 1")
    fun observeActiveShift(): Flow<ShiftEntity?>

    @Query("SELECT * FROM shifts ORDER BY openedAt DESC LIMIT :limit")
    fun observeRecent(limit: Int): Flow<List<ShiftEntity>>
}
