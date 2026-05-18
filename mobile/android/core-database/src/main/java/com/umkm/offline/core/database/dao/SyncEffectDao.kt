package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.umkm.offline.core.database.entity.SyncEffectEntity

@Dao
interface SyncEffectDao {
    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun insertIgnore(effect: SyncEffectEntity): Long

    @Query("DELETE FROM sync_effects WHERE effectKey = :effectKey")
    suspend fun deleteByKey(effectKey: String)
}
