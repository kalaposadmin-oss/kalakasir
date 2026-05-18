package com.umkm.offline.feature.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

@Composable
fun LoginScreen(
    onLoginSuccess: (String, String, String) -> Unit,
) {
    var storeCode by remember { mutableStateOf("") }
    var branchLocalId by remember { mutableStateOf("") }
    var registerLocalId by remember { mutableStateOf("") }
    var pin by remember { mutableStateOf("") }
    var errorText by remember { mutableStateOf<String?>(null) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(text = "Masuk UMKM Offline")
        Text(text = "Sederhana, tetap jalan walau internet mati.")

        OutlinedTextField(
            value = storeCode,
            onValueChange = { storeCode = it.trim() },
            label = { Text("Kode Toko") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )

        OutlinedTextField(
            value = branchLocalId,
            onValueChange = { branchLocalId = it.trim() },
            label = { Text("Branch Local ID") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )

        OutlinedTextField(
            value = registerLocalId,
            onValueChange = { registerLocalId = it.trim() },
            label = { Text("Register Local ID") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )

        OutlinedTextField(
            value = pin,
            onValueChange = { pin = it.trim() },
            label = { Text("PIN Kasir") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )

        Button(
            onClick = {
                if (storeCode.isBlank() || branchLocalId.isBlank() || registerLocalId.isBlank() || pin.length < 4) {
                    errorText = "Isi kode toko, branch/register, dan PIN minimal 4 digit."
                } else {
                    errorText = null
                    onLoginSuccess(storeCode, branchLocalId, registerLocalId)
                }
            },
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text("Masuk")
        }

        errorText?.let {
            Text(text = it)
        }
    }
}
