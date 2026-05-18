package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType

@Entity(tableName = "sync_actions_outbox")
data class SyncActionEntity(
    @PrimaryKey val actionId: String,
    val entityType: EntityType,
    val entityId: String,
    val actionType: SyncActionType,
    val payloadJson: String,
    val idempotencyKey: String,
    val retryCount: Int,
    val nextRetryTime: Long,
    val actionStatus: OutboxStatus,
    val lockedUntil: Long?,
    val lastError: String?,
    val createdAt: Long,
)
