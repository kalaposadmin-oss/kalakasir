package com.umkm.offline.app

import androidx.work.WorkInfo
import com.umkm.offline.core.common.ConnectivityState
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class MainActivityConnectivityStateTest {

    @Test
    fun `shows SYNCING when sync worker is RUNNING`() {
        val isSyncing = isSyncRunning(listOf(WorkInfo.State.RUNNING))

        val connectivityState = resolveConnectivityState(
            isSyncing = isSyncing,
            networkState = ConnectivityState.ONLINE,
            failedCount = 0,
        )

        assertTrue(isSyncing)
        assertEquals(ConnectivityState.SYNCING, connectivityState)
    }
}
