package com.umkm.offline.core.database.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import com.umkm.offline.core.common.SyncStatus

@Entity(tableName = "daily_reports")
data class DailyReportEntity(
    @PrimaryKey val reportDate: String,
    val salesTotal: Long,
    val transactionCount: Int,
    val grossProfitEstimate: Long,
    val cashInTotal: Long,
    val cashOutTotal: Long,
    val stockValueEstimate: Long,
    val syncStatus: SyncStatus,
    val updatedAtLocal: Long,
    val updatedAtServer: Long?,
)
