package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.ColumnInfo
import com.umkm.offline.core.database.entity.SyncActionEntity
import kotlinx.coroutines.flow.Flow

data class SyncActionTypeCountRow(
    @ColumnInfo(name = "actionType") val actionType: String,
    @ColumnInfo(name = "total") val total: Int,
)

@Dao
interface SyncActionDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun enqueue(action: SyncActionEntity)

    @Query("SELECT * FROM sync_actions_outbox WHERE actionStatus IN ('PENDING', 'FAILED') AND nextRetryTime <= :now AND (lockedUntil IS NULL OR lockedUntil <= :now) ORDER BY createdAt ASC LIMIT :limit")
    suspend fun findDueActions(now: Long, limit: Int): List<SyncActionEntity>

    @Query("UPDATE sync_actions_outbox SET actionStatus = 'PROCESSING', lockedUntil = :lockedUntil WHERE actionId = :actionId AND actionStatus IN ('PENDING', 'FAILED')")
    suspend fun claimForProcessing(actionId: String, lockedUntil: Long): Int

    @Query("DELETE FROM sync_actions_outbox WHERE actionId = :actionId")
    suspend fun delete(actionId: String)

    @Query("UPDATE sync_actions_outbox SET actionStatus = 'FAILED', retryCount = retryCount + 1, nextRetryTime = :nextRetryTime, lastError = :errorMessage, lockedUntil = NULL WHERE actionId = :actionId")
    suspend fun markFailed(actionId: String, nextRetryTime: Long, errorMessage: String)

    @Query("SELECT COUNT(*) FROM sync_actions_outbox WHERE idempotencyKey = :idempotencyKey")
    suspend fun countByIdempotencyKey(idempotencyKey: String): Int

    @Query("SELECT COUNT(*) FROM sync_actions_outbox WHERE actionStatus IN ('PENDING', 'PROCESSING')")
    fun observePendingCount(): Flow<Int>

    @Query("SELECT COUNT(*) FROM sync_actions_outbox WHERE actionStatus = 'FAILED'")
    fun observeFailedCount(): Flow<Int>

    @Query("SELECT actionType AS actionType, COUNT(*) AS total FROM sync_actions_outbox GROUP BY actionType ORDER BY total DESC")
    fun observeActionTypeCounts(): Flow<List<SyncActionTypeCountRow>>

    @Query("SELECT actionType AS actionType, COUNT(*) AS total FROM sync_actions_outbox WHERE actionStatus = 'FAILED' GROUP BY actionType ORDER BY total DESC")
    fun observeFailedActionTypeCounts(): Flow<List<SyncActionTypeCountRow>>

    suspend fun existsByIdempotencyKey(idempotencyKey: String): Boolean {
        return countByIdempotencyKey(idempotencyKey) > 0
    }
}
