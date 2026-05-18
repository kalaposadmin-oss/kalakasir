package com.umkm.offline.core.network

import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.Response
import retrofit2.converter.moshi.MoshiConverterFactory
import retrofit2.http.Body
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Url

class RetrofitSyncApi(
    baseUrl: String,
) : SyncApi {

    private val delegate: SyncHttpApi by lazy {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
        }
        val client = OkHttpClient.Builder()
            .addInterceptor(logging)
            .build()

        Retrofit.Builder()
            .baseUrl(normalizeBaseUrl(baseUrl))
            .client(client)
            .addConverterFactory(MoshiConverterFactory.create())
            .build()
            .create(SyncHttpApi::class.java)
    }

    private val pushPaths = listOf("api/v1/sync/actions", "sync/actions")
    private val pullPaths = listOf("api/v1/sync/pull", "sync/pull")

    override suspend fun pushAction(
        idempotencyKey: String,
        payload: Map<String, Any?>,
    ): Map<String, Any?> {
        val response = callWithFallback(pushPaths) { path ->
            delegate.pushAction(path = path, idempotencyKey = idempotencyKey, payload = payload)
        }
        return response.body() ?: emptyMap()
    }

    override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
        return callWithFallback(pullPaths) { path ->
            delegate.pullChanges(path = path, payload = payload)
        }
    }

    private fun normalizeBaseUrl(url: String): String {
        val trimmed = url.trim()
        require(trimmed.isNotBlank()) { "SYNC_BASE_URL tidak boleh kosong untuk RetrofitSyncApi." }
        return if (trimmed.endsWith('/')) trimmed else "$trimmed/"
    }

    private suspend fun <T> callWithFallback(paths: List<String>, request: suspend (String) -> T): T {
        var lastError: Throwable? = null
        paths.forEachIndexed { index, path ->
            try {
                return request(path)
            } catch (error: Throwable) {
                val shouldFallback = error is HttpException && (error.code() == 404 || error.code() == 405)
                if (shouldFallback && index < paths.lastIndex) {
                    lastError = error
                    return@forEachIndexed
                }
                throw error
            }
        }
        throw lastError ?: IllegalStateException("Sync request gagal: tidak ada path endpoint yang valid.")
    }

    private interface SyncHttpApi {
        @POST
        suspend fun pushAction(
            @Url path: String,
            @Header("Idempotency-Key") idempotencyKey: String,
            @Body payload: Map<String, Any?>,
        ): Response<Map<String, Any?>>

        @POST
        suspend fun pullChanges(
            @Url path: String,
            @Body payload: Map<String, Any?>,
        ): Map<String, Any?>
    }
}
