package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(
    tableName = "recipe_items",
    indices = [
        Index(value = ["productLocalId"]),
    ],
)
data class RecipeItemEntity(
    @PrimaryKey val localId: String,
    val productLocalId: String,
    val materialProductLocalId: String?,
    val materialName: String,
    val qty: Double,
    val unit: String,
    val syncStatus: SyncStatus,
    val version: Long,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
