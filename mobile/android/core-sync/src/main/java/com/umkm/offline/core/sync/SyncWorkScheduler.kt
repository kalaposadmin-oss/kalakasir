package com.umkm.offline.core.sync

import android.content.Context
import androidx.work.Constraints
import androidx.work.ExistingWorkPolicy
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.umkm.offline.core.sync.worker.SyncOrchestratorWorker
import java.util.concurrent.TimeUnit

class SyncWorkScheduler(
    private val context: Context,
) {
    fun schedulePeriodicSync() {
        val request = PeriodicWorkRequestBuilder<SyncOrchestratorWorker>(15, TimeUnit.MINUTES)
            .addTag(SYNC_WORK_TAG)
            .setConstraints(
                Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .build()
            )
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            PERIODIC_SYNC_NAME,
            ExistingPeriodicWorkPolicy.KEEP,
            request,
        )
    }

    fun triggerImmediateSync() {
        val request = OneTimeWorkRequestBuilder<SyncOrchestratorWorker>()
            .addTag(SYNC_WORK_TAG)
            .setConstraints(
                Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .build()
            )
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            IMMEDIATE_SYNC_NAME,
            ExistingWorkPolicy.REPLACE,
            request,
        )
    }

    companion object {
        const val PERIODIC_SYNC_NAME = "offline_sync_periodic"
        const val IMMEDIATE_SYNC_NAME = "offline_sync_immediate"
        const val SYNC_WORK_TAG = "offline_sync_orchestrator"
    }
}
