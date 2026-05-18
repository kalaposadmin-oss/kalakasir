package com.umkm.offline.core.network

import retrofit2.http.Body
import retrofit2.http.Header
import retrofit2.http.POST

interface SyncApi {
    @POST("/sync/actions")
    suspend fun pushAction(
        @Header("Idempotency-Key") idempotencyKey: String,
        @Body payload: Map<String, Any?>,
    ): Map<String, Any?>

    @POST("/sync/pull")
    suspend fun pullChanges(@Body payload: Map<String, Any?>): Map<String, Any?>
}
