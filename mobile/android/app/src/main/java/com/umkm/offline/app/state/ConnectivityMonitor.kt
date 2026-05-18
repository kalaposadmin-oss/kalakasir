package com.umkm.offline.app.state

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import com.umkm.offline.core.common.ConnectivityState
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.conflate
import kotlinx.coroutines.flow.distinctUntilChanged

class ConnectivityMonitor(
    private val context: Context,
) {
    val connectivityState: Flow<ConnectivityState> = callbackFlow {
        val manager = context.getSystemService(ConnectivityManager::class.java)
        if (manager == null) {
            trySend(ConnectivityState.OFFLINE)
            close()
            return@callbackFlow
        }

        fun currentState(): ConnectivityState {
            val network = manager.activeNetwork ?: return ConnectivityState.OFFLINE
            val capabilities = manager.getNetworkCapabilities(network) ?: return ConnectivityState.OFFLINE
            val hasInternet = capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            val validated = capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
            return if (hasInternet && validated) ConnectivityState.ONLINE else ConnectivityState.OFFLINE
        }

        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                trySend(ConnectivityState.ONLINE)
            }

            override fun onLost(network: Network) {
                trySend(currentState())
            }

            override fun onUnavailable() {
                trySend(ConnectivityState.OFFLINE)
            }
        }

        trySend(currentState())
        manager.registerDefaultNetworkCallback(callback)

        awaitClose {
            manager.unregisterNetworkCallback(callback)
        }
    }
        .distinctUntilChanged()
        .conflate()
}
