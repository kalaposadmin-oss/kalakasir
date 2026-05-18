package com.umkm.offline.app

import com.umkm.offline.core.network.SyncApi

class FakeSyncApi : SyncApi {
    override suspend fun pushAction(
        idempotencyKey: String,
        payload: Map<String, Any?>,
    ): Map<String, Any?> {
        // Simulate successful sync for local MVP demo.
        return emptyMap()
    }

    override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
        return mapOf(
            "next_cursor" to "cursor-${System.currentTimeMillis()}",
            "changes" to emptyList<Map<String, Any?>>(),
        )
    }
}
