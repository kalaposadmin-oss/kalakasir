package com.umkm.offline.app.purchase

import kotlinx.coroutines.flow.Flow

data class PurchaseCreateValidationResult(
    val canSubmit: Boolean,
    val fieldErrors: Map<String, String>,
    val formError: String?,
)

data class PurchaseReceiveLineDraft(
    val productLocalId: String,
    val qty: Double,
    val remainingQty: Double,
)

data class PurchaseReceiveValidationResult(
    val canSubmit: Boolean,
    val actionEnabled: Boolean,
    val fieldErrors: Map<String, String>,
    val actionError: String?,
    val effectiveItems: List<PurchaseReceiveItemDraft>,
)

data class PurchaseReturnLineDraft(
    val productLocalId: String,
    val qty: Double,
    val unitCost: Long,
    val localRemainingQty: Double? = null,
)

data class PurchaseReturnValidationResult(
    val canSubmit: Boolean,
    val actionEnabled: Boolean,
    val fieldErrors: Map<String, String>,
    val actionError: String?,
    val effectiveItems: List<PurchaseReturnItemDraft>,
)

class ValidateFinalizePurchaseDraftUseCase {
    operator fun invoke(
        supplierLocalId: String?,
        items: List<PurchaseItemDraft>,
        paymentMethod: String,
        dueDate: Long?,
        transactionDate: Long? = null,
    ): PurchaseCreateValidationResult {
        val errors = linkedMapOf<String, String>()

        val normalizedSupplierId = supplierLocalId?.trim().orEmpty()
        if (normalizedSupplierId.isBlank()) {
            errors["supplier"] = "Supplier wajib dipilih."
        }

        if (items.isEmpty()) {
            errors["items"] = "Minimal 1 item pembelian wajib diisi."
        }

        items.forEachIndexed { index, item ->
            if (item.productLocalId.trim().isBlank()) {
                errors["item_$index.product_id"] = "Produk pada baris ${index + 1} wajib diisi."
            }
            if (item.qty <= 0.0) {
                errors["item_$index.qty"] = "Qty pada baris ${index + 1} harus lebih besar dari 0."
            }
            if (item.unitCost < 0L) {
                errors["item_$index.price"] = "Harga pada baris ${index + 1} tidak boleh negatif."
            }
        }

        if (!PurchaseContract.PaymentMethod.isAllowedForCreate(paymentMethod)) {
            errors["payment_method"] = "Metode pembayaran hanya boleh Tunai atau Tempo."
        }

        val normalizedPaymentMethod = PurchaseContract.PaymentMethod.normalizeForCreate(paymentMethod)
        if (normalizedPaymentMethod == PurchaseContract.PaymentMethod.TEMPO && (dueDate == null || dueDate <= 0L)) {
            errors["due_date"] = "Jatuh tempo wajib diisi untuk pembayaran tempo."
        }

        if (transactionDate != null && transactionDate <= 0L) {
            errors["transaction_date"] = "Tanggal transaksi tidak valid."
        }

        val formError = errors.values.firstOrNull()
        return PurchaseCreateValidationResult(
            canSubmit = errors.isEmpty(),
            fieldErrors = errors,
            formError = formError,
        )
    }
}

class ValidateMarkPurchaseReceivedDraftUseCase {
    operator fun invoke(
        purchaseLocalId: String?,
        purchaseStatus: String?,
        receiveLines: List<PurchaseReceiveLineDraft>,
        transactionDate: Long?,
        isWriteLocked: Boolean,
    ): PurchaseReceiveValidationResult {
        val errors = linkedMapOf<String, String>()
        val effectiveItems = mutableListOf<PurchaseReceiveItemDraft>()

        val normalizedPurchaseId = purchaseLocalId?.trim().orEmpty()
        if (normalizedPurchaseId.isBlank()) {
            errors["purchase_id"] = "Purchase id wajib diisi."
        }

        val normalizedStatus = PurchaseContract.Status.normalize(purchaseStatus)
        val statusAllowed = PurchaseContract.Status.allowsReceive(normalizedStatus)
        if (!statusAllowed) {
            errors["status"] = "Receive hanya dapat diproses dari status approved atau partially_received."
        }

        if (transactionDate != null && transactionDate <= 0L) {
            errors["transaction_date"] = "Tanggal transaksi tidak valid."
        }

        receiveLines.forEachIndexed { index, line ->
            val normalizedProductId = line.productLocalId.trim()
            if (normalizedProductId.isBlank()) {
                errors["item_$index.product_id"] = "Produk receive pada baris ${index + 1} wajib diisi."
            }
            if (line.qty <= 0.0) {
                errors["item_$index.qty"] = "Qty receive pada baris ${index + 1} harus lebih besar dari 0."
            }
            if (line.remainingQty <= 0.0) {
                errors["item_$index.remaining"] = "Sisa qty pada baris ${index + 1} harus lebih besar dari 0."
            }
            if (line.qty > line.remainingQty) {
                errors["item_$index.qty"] = "Qty receive pada baris ${index + 1} tidak boleh melebihi sisa qty."
            }

            val isLineValid = normalizedProductId.isNotBlank() && line.qty > 0.0 && line.remainingQty > 0.0 && line.qty <= line.remainingQty
            if (isLineValid) {
                effectiveItems += PurchaseReceiveItemDraft(
                    productLocalId = normalizedProductId,
                    qty = line.qty,
                )
            }
        }

        if (effectiveItems.isEmpty()) {
            errors["items"] = "Minimal 1 item receive valid wajib diisi."
        }

        val actionEnabled = !isWriteLocked && statusAllowed
        val actionError = when {
            isWriteLocked -> "Periode terkunci. Aksi receive tidak dapat diproses."
            !statusAllowed -> "Receive hanya dapat diproses dari status approved atau partially_received."
            else -> errors.values.firstOrNull()
        }

        return PurchaseReceiveValidationResult(
            canSubmit = actionEnabled && errors.isEmpty(),
            actionEnabled = actionEnabled,
            fieldErrors = errors,
            actionError = actionError,
            effectiveItems = effectiveItems,
        )
    }
}

class ValidateRecordPurchaseReturnDraftUseCase {
    operator fun invoke(
        purchaseLocalId: String?,
        purchaseStatus: String?,
        supplierLocalId: String?,
        purchaseSupplierLocalId: String?,
        reason: String,
        returnLines: List<PurchaseReturnLineDraft>,
        transactionDate: Long?,
        isWriteLocked: Boolean,
    ): PurchaseReturnValidationResult {
        val errors = linkedMapOf<String, String>()
        val effectiveItems = mutableListOf<PurchaseReturnItemDraft>()

        val normalizedPurchaseId = purchaseLocalId?.trim().orEmpty()
        if (normalizedPurchaseId.isBlank()) {
            errors["purchase_id"] = "Purchase id wajib diisi."
        }

        val normalizedSupplierId = supplierLocalId?.trim().orEmpty()
        if (normalizedSupplierId.isBlank()) {
            errors["supplier_id"] = "Supplier id wajib diisi."
        }

        val normalizedPurchaseSupplierId = purchaseSupplierLocalId?.trim().orEmpty()
        if (normalizedPurchaseSupplierId.isBlank()) {
            errors["purchase_supplier_id"] = "Supplier purchase asal wajib diisi."
        }
        if (
            normalizedSupplierId.isNotBlank() &&
            normalizedPurchaseSupplierId.isNotBlank() &&
            normalizedSupplierId != normalizedPurchaseSupplierId
        ) {
            errors["supplier_match"] = "Supplier return harus sama dengan supplier pada purchase asal."
        }

        val normalizedStatus = PurchaseContract.Status.normalize(purchaseStatus)
        val statusAllowed = PurchaseContract.Status.allowsReturn(normalizedStatus)
        if (!statusAllowed) {
            errors["status"] = "Retur hanya dapat diproses untuk status purchase yang sudah diterima."
        }

        if (returnLines.isEmpty()) {
            errors["items"] = "Item retur wajib minimal 1 baris."
        }

        returnLines.forEachIndexed { index, line ->
            val normalizedProductId = line.productLocalId.trim()
            if (normalizedProductId.isBlank()) {
                errors["item_$index.product_id"] = "Produk retur pada baris ${index + 1} wajib diisi."
            }
            if (line.qty <= 0.0) {
                errors["item_$index.qty"] = "Qty retur pada baris ${index + 1} harus lebih besar dari 0."
            }
            if (line.unitCost < 0L) {
                errors["item_$index.price"] = "Harga retur pada baris ${index + 1} tidak boleh negatif."
            }

            val remaining = line.localRemainingQty
            if (remaining != null) {
                if (remaining <= 0.0) {
                    errors["item_$index.remaining"] = "Sisa qty lokal pada baris ${index + 1} harus lebih besar dari 0."
                } else if (line.qty > remaining) {
                    errors["item_$index.qty"] = "Qty retur pada baris ${index + 1} melebihi sisa qty lokal."
                }
            }

            val isLineValid = normalizedProductId.isNotBlank() && line.qty > 0.0 && line.unitCost >= 0L && (remaining == null || (remaining > 0.0 && line.qty <= remaining))
            if (isLineValid) {
                effectiveItems += PurchaseReturnItemDraft(
                    productLocalId = normalizedProductId,
                    qty = line.qty,
                    unitCost = line.unitCost,
                )
            }
        }

        val normalizedReason = reason.trim()
        if (normalizedReason.isBlank()) {
            errors["reason"] = "Alasan retur wajib diisi."
        }

        if (transactionDate != null && transactionDate <= 0L) {
            errors["transaction_date"] = "Tanggal transaksi tidak valid."
        }

        if (effectiveItems.isEmpty()) {
            errors["items_effective"] = "Item retur efektif harus minimal 1 baris valid."
        }

        val actionEnabled = !isWriteLocked && statusAllowed
        val actionError = when {
            isWriteLocked -> "Periode terkunci. Aksi retur tidak dapat diproses."
            !statusAllowed -> "Retur hanya dapat diproses untuk status purchase yang sudah diterima."
            else -> errors.values.firstOrNull()
        }

        return PurchaseReturnValidationResult(
            canSubmit = actionEnabled && errors.isEmpty(),
            actionEnabled = actionEnabled,
            fieldErrors = errors,
            actionError = actionError,
            effectiveItems = effectiveItems,
        )
    }
}

class ObserveSuppliersUseCase(
    private val repository: PurchaseRepository,
) {
    operator fun invoke(): Flow<List<SupplierSummary>> {
        return repository.observeSuppliers()
    }
}

class CreateSupplierUseCase(
    private val repository: PurchaseRepository,
) {
    suspend operator fun invoke(
        name: String,
        phone: String?,
        address: String?,
        notes: String?,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): String {
        return repository.createSupplierOffline(
            name = name,
            phone = phone,
            address = address,
            notes = notes,
            requestedByRole = requestedByRole,
            approvalRequired = approvalRequired,
        )
    }
}

class FinalizePurchaseUseCase(
    private val repository: PurchaseRepository,
    private val validateFinalizePurchaseDraftUseCase: ValidateFinalizePurchaseDraftUseCase,
) {
    suspend operator fun invoke(
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
    ): String {
        val validation = validateFinalizePurchaseDraftUseCase(
            supplierLocalId = supplierLocalId,
            items = items,
            paymentMethod = paymentMethod,
            dueDate = dueDate,
            transactionDate = null,
        )
        if (!validation.canSubmit) {
            throw IllegalArgumentException(validation.formError ?: "Validasi pembelian gagal.")
        }

        val normalizedPaymentMethod = PurchaseContract.PaymentMethod.normalizeForCreate(paymentMethod)
        return repository.finalizePurchaseOffline(
            purchaseNo = purchaseNo,
            supplierLocalId = supplierLocalId,
            items = items,
            discountAmount = discountAmount,
            dueDate = dueDate,
            paymentMethod = normalizedPaymentMethod,
            paymentStatus = paymentStatus,
            cashSource = cashSource,
            note = note,
            requestedByRole = requestedByRole,
            approvalRequired = approvalRequired,
        )
    }
}

class MarkPurchaseReceivedUseCase(
    private val repository: PurchaseRepository,
    private val validateMarkPurchaseReceivedDraftUseCase: ValidateMarkPurchaseReceivedDraftUseCase,
) {
    suspend operator fun invoke(
        purchaseLocalId: String,
        purchaseStatus: String,
        receiveLines: List<PurchaseReceiveLineDraft>,
        receivedAt: Long = System.currentTimeMillis(),
        payloadTransactionDate: Long? = receivedAt,
        isWriteLocked: Boolean = false,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): Boolean {
        val validation = validateMarkPurchaseReceivedDraftUseCase(
            purchaseLocalId = purchaseLocalId,
            purchaseStatus = purchaseStatus,
            receiveLines = receiveLines,
            transactionDate = payloadTransactionDate,
            isWriteLocked = isWriteLocked,
        )
        if (!validation.canSubmit) {
            throw IllegalArgumentException(validation.actionError ?: "Validasi receive pembelian gagal.")
        }

        return repository.markPurchaseReceivedOffline(
            purchaseLocalId = purchaseLocalId,
            receivedAt = receivedAt,
            payloadTransactionDate = payloadTransactionDate,
            receiveItems = validation.effectiveItems,
            requestedByRole = requestedByRole,
            approvalRequired = approvalRequired,
        )
    }
}

class RecordPurchaseReturnUseCase(
    private val repository: PurchaseRepository,
    private val validateRecordPurchaseReturnDraftUseCase: ValidateRecordPurchaseReturnDraftUseCase,
) {
    suspend operator fun invoke(
        purchaseLocalId: String,
        purchaseStatus: String,
        supplierLocalId: String,
        purchaseSupplierLocalId: String,
        reason: String,
        items: List<PurchaseReturnLineDraft>,
        returnedAt: Long = System.currentTimeMillis(),
        payloadTransactionDate: Long? = returnedAt,
        isWriteLocked: Boolean = false,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): Boolean {
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
        if (!validation.canSubmit) {
            throw IllegalArgumentException(validation.actionError ?: "Validasi retur pembelian gagal.")
        }

        return repository.recordPurchaseReturnOffline(
            purchaseLocalId = purchaseLocalId,
            reason = reason,
            items = validation.effectiveItems,
            returnedAt = payloadTransactionDate ?: returnedAt,
            requestedByRole = requestedByRole,
            approvalRequired = approvalRequired,
        )
    }
}
