package com.umkm.offline.app.purchase

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PurchaseState(
    val suppliers: List<SupplierSummary> = emptyList(),
    val isSaving: Boolean = false,
    val message: String? = null,
    val errorMessage: String? = null,
    val lastSupplierId: String? = null,
    val lastPurchaseId: String? = null,
    val createCanSubmit: Boolean = false,
    val createFieldErrors: Map<String, String> = emptyMap(),
    val createFormError: String? = null,
    val receiveCanSubmit: Boolean = false,
    val receiveActionEnabled: Boolean = false,
    val receiveFieldErrors: Map<String, String> = emptyMap(),
    val receiveActionError: String? = null,
    val returnCanSubmit: Boolean = false,
    val returnActionEnabled: Boolean = false,
    val returnFieldErrors: Map<String, String> = emptyMap(),
    val returnActionError: String? = null,
)

class PurchaseViewModel(
    private val observeSuppliersUseCase: ObserveSuppliersUseCase,
    private val createSupplierUseCase: CreateSupplierUseCase,
    private val finalizePurchaseUseCase: FinalizePurchaseUseCase,
    private val validateFinalizePurchaseDraftUseCase: ValidateFinalizePurchaseDraftUseCase,
    private val validateMarkPurchaseReceivedDraftUseCase: ValidateMarkPurchaseReceivedDraftUseCase,
    private val markPurchaseReceivedUseCase: MarkPurchaseReceivedUseCase,
    private val validateRecordPurchaseReturnDraftUseCase: ValidateRecordPurchaseReturnDraftUseCase,
    private val recordPurchaseReturnUseCase: RecordPurchaseReturnUseCase,
) : ViewModel() {

    private val _uiState = MutableStateFlow(PurchaseState())
    val uiState: StateFlow<PurchaseState> = _uiState.asStateFlow()

    init {
        observeSuppliers()
    }

    private fun observeSuppliers() {
        viewModelScope.launch {
            observeSuppliersUseCase().collect { suppliers ->
                _uiState.update { it.copy(suppliers = suppliers) }
            }
        }
    }

    fun createSupplier(
        name: String,
        phone: String?,
        address: String?,
        notes: String?,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, message = null, errorMessage = null) }
            runCatching {
                createSupplierUseCase(
                    name = name,
                    phone = phone,
                    address = address,
                    notes = notes,
                    requestedByRole = requestedByRole,
                    approvalRequired = approvalRequired,
                )
            }.onSuccess { supplierId ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        lastSupplierId = supplierId,
                        message = "Supplier tersimpan lokal dan siap sinkronisasi.",
                    )
                }
            }.onFailure { error ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = error.message ?: "Gagal menyimpan supplier.",
                    )
                }
            }
        }
    }

    fun finalizePurchase(
        purchaseNo: String,
        supplierLocalId: String?,
        items: List<PurchaseItemDraft>,
        discountAmount: Long,
        dueDate: Long?,
        paymentMethod: String = "cash",
        paymentStatus: String = "paid",
        cashSource: String? = null,
        note: String? = null,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ) {
        viewModelScope.launch {
            val validation = validateFinalizePurchaseDraftUseCase(
                supplierLocalId = supplierLocalId,
                items = items,
                paymentMethod = paymentMethod,
                dueDate = dueDate,
                transactionDate = null,
            )
            _uiState.update {
                it.copy(
                    createCanSubmit = validation.canSubmit,
                    createFieldErrors = validation.fieldErrors,
                    createFormError = validation.formError,
                )
            }
            if (!validation.canSubmit) {
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = validation.formError ?: "Validasi pembelian gagal.",
                    )
                }
                return@launch
            }

            _uiState.update { it.copy(isSaving = true, message = null, errorMessage = null) }
            runCatching {
                finalizePurchaseUseCase(
                    purchaseNo = purchaseNo,
                    supplierLocalId = supplierLocalId,
                    items = items,
                    discountAmount = discountAmount,
                    dueDate = dueDate,
                    paymentMethod = paymentMethod,
                    paymentStatus = paymentStatus,
                    cashSource = cashSource,
                    note = note,
                    requestedByRole = requestedByRole,
                    approvalRequired = approvalRequired,
                )
            }.onSuccess { purchaseId ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        lastPurchaseId = purchaseId,
                        createCanSubmit = false,
                        createFieldErrors = emptyMap(),
                        createFormError = null,
                        message = "Pembelian tersimpan lokal dan siap sinkronisasi.",
                    )
                }
            }.onFailure { error ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = error.message ?: "Gagal menyimpan pembelian.",
                    )
                }
            }
        }
    }

    fun evaluateFinalizePurchaseDraft(
        supplierLocalId: String?,
        items: List<PurchaseItemDraft>,
        paymentMethod: String,
        dueDate: Long?,
        transactionDate: Long? = null,
    ) {
        val validation = validateFinalizePurchaseDraftUseCase(
            supplierLocalId = supplierLocalId,
            items = items,
            paymentMethod = paymentMethod,
            dueDate = dueDate,
            transactionDate = transactionDate,
        )
        _uiState.update {
            it.copy(
                createCanSubmit = validation.canSubmit,
                createFieldErrors = validation.fieldErrors,
                createFormError = validation.formError,
            )
        }
    }

    fun markPurchaseReceived(
        purchaseLocalId: String,
        purchaseStatus: String,
        receiveLines: List<PurchaseReceiveLineDraft>,
        payloadTransactionDate: Long?,
        isWriteLocked: Boolean,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ) {
        viewModelScope.launch {
            val validation = validateMarkPurchaseReceivedDraftUseCase(
                purchaseLocalId = purchaseLocalId,
                purchaseStatus = purchaseStatus,
                receiveLines = receiveLines,
                transactionDate = payloadTransactionDate,
                isWriteLocked = isWriteLocked,
            )
            _uiState.update {
                it.copy(
                    receiveCanSubmit = validation.canSubmit,
                    receiveActionEnabled = validation.actionEnabled,
                    receiveFieldErrors = validation.fieldErrors,
                    receiveActionError = validation.actionError,
                )
            }
            if (!validation.canSubmit) {
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = validation.actionError ?: "Validasi receive pembelian gagal.",
                    )
                }
                return@launch
            }

            _uiState.update { it.copy(isSaving = true, message = null, errorMessage = null) }
            runCatching {
                markPurchaseReceivedUseCase(
                    purchaseLocalId = purchaseLocalId,
                    purchaseStatus = purchaseStatus,
                    receiveLines = receiveLines,
                    payloadTransactionDate = payloadTransactionDate,
                    isWriteLocked = isWriteLocked,
                    requestedByRole = requestedByRole,
                    approvalRequired = approvalRequired,
                )
            }.onSuccess { updated ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        receiveCanSubmit = false,
                        receiveActionEnabled = false,
                        receiveFieldErrors = emptyMap(),
                        receiveActionError = null,
                        message = if (updated) {
                            "Status pembelian diperbarui ke received dan siap sinkronisasi."
                        } else {
                            "Pembelian sudah berstatus received."
                        },
                    )
                }
            }.onFailure { error ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = error.message ?: "Gagal memperbarui status pembelian.",
                    )
                }
            }
        }
    }

    fun evaluateMarkPurchaseReceivedDraft(
        purchaseLocalId: String?,
        purchaseStatus: String?,
        receiveLines: List<PurchaseReceiveLineDraft>,
        payloadTransactionDate: Long?,
        isWriteLocked: Boolean,
    ) {
        val validation = validateMarkPurchaseReceivedDraftUseCase(
            purchaseLocalId = purchaseLocalId,
            purchaseStatus = purchaseStatus,
            receiveLines = receiveLines,
            transactionDate = payloadTransactionDate,
            isWriteLocked = isWriteLocked,
        )
        _uiState.update {
            it.copy(
                receiveCanSubmit = validation.canSubmit,
                receiveActionEnabled = validation.actionEnabled,
                receiveFieldErrors = validation.fieldErrors,
                receiveActionError = validation.actionError,
            )
        }
    }

    fun recordPurchaseReturn(
        purchaseLocalId: String,
        purchaseStatus: String,
        supplierLocalId: String,
        purchaseSupplierLocalId: String,
        reason: String,
        items: List<PurchaseReturnLineDraft>,
        payloadTransactionDate: Long?,
        isWriteLocked: Boolean,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ) {
        viewModelScope.launch {
            val validation = validateRecordPurchaseReturnDraftUseCase(
                purchaseLocalId = purchaseLocalId,
                purchaseStatus = purchaseStatus,
                supplierLocalId = supplierLocalId,
                purchaseSupplierLocalId = purchaseSupplierLocalId,
                reason = reason,
                returnLines = items,
                transactionDate = payloadTransactionDate,
                isWriteLocked = isWriteLocked,
            )
            _uiState.update {
                it.copy(
                    returnCanSubmit = validation.canSubmit,
                    returnActionEnabled = validation.actionEnabled,
                    returnFieldErrors = validation.fieldErrors,
                    returnActionError = validation.actionError,
                )
            }
            if (!validation.canSubmit) {
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = validation.actionError ?: "Validasi retur pembelian gagal.",
                    )
                }
                return@launch
            }

            _uiState.update { it.copy(isSaving = true, message = null, errorMessage = null) }
            runCatching {
                recordPurchaseReturnUseCase(
                    purchaseLocalId = purchaseLocalId,
                    purchaseStatus = purchaseStatus,
                    supplierLocalId = supplierLocalId,
                    purchaseSupplierLocalId = purchaseSupplierLocalId,
                    reason = reason,
                    items = items,
                    payloadTransactionDate = payloadTransactionDate,
                    isWriteLocked = isWriteLocked,
                    requestedByRole = requestedByRole,
                    approvalRequired = approvalRequired,
                )
            }.onSuccess {
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        returnCanSubmit = false,
                        returnActionEnabled = false,
                        returnFieldErrors = emptyMap(),
                        returnActionError = null,
                        message = "Retur pembelian tersimpan lokal dan siap sinkronisasi.",
                    )
                }
            }.onFailure { error ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = error.message ?: "Gagal mencatat retur pembelian.",
                    )
                }
            }
        }
    }

    fun evaluateRecordPurchaseReturnDraft(
        purchaseLocalId: String?,
        purchaseStatus: String?,
        supplierLocalId: String?,
        purchaseSupplierLocalId: String?,
        reason: String,
        items: List<PurchaseReturnLineDraft>,
        payloadTransactionDate: Long?,
        isWriteLocked: Boolean,
    ) {
        val validation = validateRecordPurchaseReturnDraftUseCase(
            purchaseLocalId = purchaseLocalId,
            purchaseStatus = purchaseStatus,
            supplierLocalId = supplierLocalId,
            purchaseSupplierLocalId = purchaseSupplierLocalId,
            reason = reason,
            returnLines = items,
            transactionDate = payloadTransactionDate,
            isWriteLocked = isWriteLocked,
        )
        _uiState.update {
            it.copy(
                returnCanSubmit = validation.canSubmit,
                returnActionEnabled = validation.actionEnabled,
                returnFieldErrors = validation.fieldErrors,
                returnActionError = validation.actionError,
            )
        }
    }
}
