package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.RecipeItemEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface RecipeDao {
    @Query("SELECT * FROM recipe_items ORDER BY productLocalId ASC, materialName ASC")
    fun observeAll(): Flow<List<RecipeItemEntity>>

    @Query("SELECT * FROM recipe_items WHERE productLocalId = :productLocalId ORDER BY materialName ASC")
    suspend fun findByProductLocalId(productLocalId: String): List<RecipeItemEntity>

    @Query("DELETE FROM recipe_items WHERE productLocalId = :productLocalId")
    suspend fun deleteByProductLocalId(productLocalId: String)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(items: List<RecipeItemEntity>)
}
