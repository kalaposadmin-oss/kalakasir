package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.AppPolicyEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface AppPolicyDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(policy: AppPolicyEntity)

    @Query("SELECT value FROM app_policies WHERE `key` = :key LIMIT 1")
    suspend fun getValue(key: String): String?

    @Query("SELECT value FROM app_policies WHERE `key` = :key LIMIT 1")
    fun observeValue(key: String): Flow<String?>
}
