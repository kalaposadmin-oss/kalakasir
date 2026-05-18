package com.umkm.offline.app.state

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.umkm.offline.app.purchase.CreateSupplierUseCase
import com.umkm.offline.app.purchase.FinalizePurchaseUseCase
import com.umkm.offline.app.purchase.MarkPurchaseReceivedUseCase
import com.umkm.offline.app.purchase.ObserveSuppliersUseCase
import com.umkm.offline.app.purchase.PurchaseRepository
import com.umkm.offline.app.purchase.PurchaseViewModel
import com.umkm.offline.app.purchase.RecordPurchaseReturnUseCase
import com.umkm.offline.app.purchase.ValidateFinalizePurchaseDraftUseCase
import com.umkm.offline.app.purchase.ValidateMarkPurchaseReceivedDraftUseCase
import com.umkm.offline.app.purchase.ValidateRecordPurchaseReturnDraftUseCase

class PurchaseViewModelFactory(
    private val repository: PurchaseRepository,
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(PurchaseViewModel::class.java)) {
            val createValidator = ValidateFinalizePurchaseDraftUseCase()
            val receiveValidator = ValidateMarkPurchaseReceivedDraftUseCase()
            val returnValidator = ValidateRecordPurchaseReturnDraftUseCase()
            return PurchaseViewModel(
                observeSuppliersUseCase = ObserveSuppliersUseCase(repository),
                createSupplierUseCase = CreateSupplierUseCase(repository),
                finalizePurchaseUseCase = FinalizePurchaseUseCase(repository, createValidator),
                validateFinalizePurchaseDraftUseCase = createValidator,
                validateMarkPurchaseReceivedDraftUseCase = receiveValidator,
                markPurchaseReceivedUseCase = MarkPurchaseReceivedUseCase(repository, receiveValidator),
                validateRecordPurchaseReturnDraftUseCase = returnValidator,
                recordPurchaseReturnUseCase = RecordPurchaseReturnUseCase(repository, returnValidator),
            ) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class: ${modelClass.simpleName}")
    }
}
