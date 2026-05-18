package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "sync_cursor")
data class SyncCursorEntity(
    @PrimaryKey val key: String,
    val cursorToken: String,
    val updatedAt: Long,
)
