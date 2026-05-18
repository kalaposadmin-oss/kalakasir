package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "app_policies")
data class AppPolicyEntity(
    @PrimaryKey val key: String,
    val value: String,
    val updatedAt: Long,
)
