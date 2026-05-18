package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.SaleFifoAllocationEntity

@Dao
interface SaleFifoAllocationDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(allocations: List<SaleFifoAllocationEntity>)

    @Query("SELECT * FROM sale_fifo_allocations WHERE saleLocalId = :saleLocalId AND reversedAt IS NULL ORDER BY allocationOrder ASC, createdAt ASC")
    suspend fun findActiveBySaleLocalId(saleLocalId: String): List<SaleFifoAllocationEntity>

    @Query("UPDATE sale_fifo_allocations SET reversedAt = :reversedAt, reversalRef = :reversalRef WHERE localId = :allocationLocalId")
    suspend fun markReversed(allocationLocalId: String, reversedAt: Long, reversalRef: String)
}