package com.umkm.offline.app.state

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.umkm.offline.feature.pos.domain.FinalizeSaleUseCase
import com.umkm.offline.feature.pos.domain.PosRepository
import com.umkm.offline.feature.pos.presentation.PosViewModel

class PosViewModelFactory(
    private val repository: PosRepository,
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(PosViewModel::class.java)) {
            return PosViewModel(
                repository = repository,
                finalizeSaleUseCase = FinalizeSaleUseCase(repository),
            ) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class: ${modelClass.simpleName}")
    }
}
