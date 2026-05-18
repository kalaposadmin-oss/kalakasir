package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.SupplierEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface SupplierDao {
    @Query("SELECT * FROM suppliers ORDER BY name ASC")
    fun observeSuppliers(): Flow<List<SupplierEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(supplier: SupplierEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(suppliers: List<SupplierEntity>)

    @Query("SELECT * FROM suppliers WHERE localId = :localId LIMIT 1")
    suspend fun findByLocalId(localId: String): SupplierEntity?

    @Query("SELECT COUNT(*) FROM suppliers")
    suspend fun countSuppliers(): Long
}
