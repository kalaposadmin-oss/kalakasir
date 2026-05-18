package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "sync_effects")
data class SyncEffectEntity(
    @PrimaryKey val effectKey: String,
    val createdAt: Long,
)
