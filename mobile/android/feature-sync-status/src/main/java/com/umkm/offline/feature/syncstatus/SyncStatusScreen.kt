package com.umkm.offline.feature.syncstatus

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.umkm.offline.core.common.ConnectivityState

@Composable
fun SyncStatusScreen(
    connectivityState: ConnectivityState,
    isSyncing: Boolean,
    pendingCount: Int,
    failedCount: Int,
    onSyncNow: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(text = "Status Sinkronisasi")

        Card(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = "Konektivitas: $connectivityState")
                Text(text = if (isSyncing) "Sinkronisasi sedang berjalan" else "Sinkronisasi idle")
                Text(text = "Pending: $pendingCount")
                Text(text = "Failed: $failedCount")
            }
        }

        Button(onClick = onSyncNow, enabled = !isSyncing, modifier = Modifier.fillMaxWidth()) {
            Text(if (isSyncing) "Sedang Sinkronisasi..." else "Sinkronkan Sekarang")
        }
    }
}
