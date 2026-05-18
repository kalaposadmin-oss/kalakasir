package com.umkm.offline.core.sync.worker

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.umkm.offline.core.sync.SyncDependencies

class PullServerChangesWorker(
    appContext: Context,
    workerParams: WorkerParameters,
) : CoroutineWorker(appContext, workerParams) {

    override suspend fun doWork(): Result {
        val syncEngine = SyncDependencies.syncEngine ?: return Result.retry()

        return runCatching {
            syncEngine.pullServerChanges()
            Result.success()
        }.getOrElse {
            Result.retry()
        }
    }
}
