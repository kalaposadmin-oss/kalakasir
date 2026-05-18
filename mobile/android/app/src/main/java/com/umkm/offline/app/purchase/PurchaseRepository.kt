package com.umkm.offline.app.purchase

import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.SupplierDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.PurchaseReturnItemEntity
import com.umkm.offline.core.database.entity.SupplierEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import java.security.MessageDigest
import java.time.Instant
import java.util.Locale
import java.util.UUID

data class SupplierSummary(
    val localId: String,
    val name: String,
    val phone: String?,
)

data class PurchaseItemDraft(
    val productLocalId: String,
    val qty: Double,
    val unitCost: Long,
)

data class PurchaseReturnItemDraft(
    val productLocalId: String,
    val qty: Double,
    val unitCost: Long,
)

data class PurchaseReceiveItemDraft(
    val productLocalId: String,
    val qty: Double,
)

class PurchaseRepository(
    private val supplierDao: SupplierDao,
    private val purchaseDao: PurchaseDao,
    private val syncActionDao: SyncActionDao,
    private val productDao: ProductDao? = null,
    private val onMetric: (name: String, attributes: Map<String, String>) -> Unit = { _, _ -> },
) {
    companion object {
        private const val EPSILON = 1e-9
        private const val RETURN_DUPLICATE_WINDOW_MS = 5 * 60 * 1000L
    }

    private data class NormalizedReturnItem(
        val productLocalId: String,
        val qty: Double,
        val unitCost: Long,
    )

    private data class ReceivePayloadItemInput(
        val productLocalId: String,
        val qty: Double,
    )

    private data class ReceivePayloadItem(
        val productId: String,
        val qty: Double,
    )

    private data class ReturnPayloadItemInput(
        val productLocalId: String,
        val qty: Double,
        val price: Long,
        val reason: String?,
    )

    private data class ReturnPayloadItem(
        val productId: String,
        val qty: Double,
        val price: Long,
        val reason: String?,
    )

    fun observeSuppliers(): Flow<List<SupplierSummary>> {
        return supplierDao.observeSuppliers().map { list ->
            list.map { supplier ->
                SupplierSummary(
                    localId = supplier.localId,
                    name = supplier.name,
                    phone = supplier.phone,
                )
            }
        }
    }

    suspend fun createSupplierOffline(
        name: String,
        phone: String?,
        address: String?,
        notes: String?,
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): String {
        val now = System.currentTimeMillis()
        val supplierLocalId = UUID.randomUUID().toString()
        val normalizedName = name.trim()

        require(normalizedName.isNotBlank()) { "Nama supplier wajib diisi." }

        val supplier = SupplierEntity(
            localId = supplierLocalId,
            serverId = null,
            name = normalizedName,
            phone = phone?.trim()?.ifBlank { null },
            address = address?.trim()?.ifBlank { null },
            notes = notes?.trim()?.ifBlank { null },
            syncStatus = SyncStatus.PENDING_SYNC,
            version = 1,
            updatedAtLocal = now,
            updatedAtServer = null,
        )

        supplierDao.upsert(supplier)

        syncActionDao.enqueue(
            outboxAction(
                entityType = EntityType.SUPPLIER,
                entityId = supplierLocalId,
                actionType = SyncActionType.UPSERT_SUPPLIER,
                payloadJson = "{\"supplier_local_id\":\"$supplierLocalId\",\"name\":\"${escapeJson(normalizedName)}\",\"source_module\":\"purchase\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":$approvalRequired}",
                now = now,
                idempotencyKey = "purchase-supplier-$supplierLocalId",
            )
        )

        return supplierLocalId
    }

    suspend fun finalizePurchaseOffline(
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
        require(items.isNotEmpty()) { "Item pembelian wajib minimal 1 baris." }
        require(discountAmount >= 0) { "Diskon tidak boleh negatif." }
        items.forEachIndexed { index, item ->
            require(item.productLocalId.trim().isNotBlank()) {
                "Produk pada baris ${index + 1} wajib diisi."
            }
            require(item.qty > 0.0) {
                "Qty pada baris ${index + 1} harus lebih besar dari 0."
            }
            require(item.unitCost >= 0) {
                "Harga modal pada baris ${index + 1} tidak boleh negatif."
            }
        }
        validatePurchaseItemsAgainstProductCatalog(items)

        val now = System.currentTimeMillis()
        val purchaseLocalId = UUID.randomUUID().toString()
        val normalizedPurchaseNo = purchaseNo.trim()
        require(normalizedPurchaseNo.isNotBlank()) { "Nomor pembelian wajib diisi." }

        val subtotal = items.sumOf { (it.qty * it.unitCost).toLong() }
        val safeDiscount = discountAmount.coerceAtMost(subtotal)
        val total = subtotal - safeDiscount
        val normalizedPaymentMethod = PurchaseContract.PaymentMethod.normalize(paymentMethod)
        val normalizedPaymentStatus = PurchaseContract.PaymentStatus.normalize(paymentStatus)
        val effectivePaymentStatus = PurchaseContract.resolveEffectivePaymentStatus(
            paymentMethod = normalizedPaymentMethod,
            paymentStatus = normalizedPaymentStatus,
        )
        val dueDateRequired = normalizedPaymentMethod == PurchaseContract.PaymentMethod.TEMPO ||
            normalizedPaymentMethod == PurchaseContract.PaymentMethod.UNPAID ||
            effectivePaymentStatus == PurchaseContract.PaymentStatus.UNPAID
        require(!dueDateRequired || dueDate != null) {
            "Jatuh tempo wajib diisi untuk pembayaran tempo/belum dibayar."
        }

        val normalizedCashSource = if (normalizedPaymentMethod == PurchaseContract.PaymentMethod.CASH) {
            cashSource?.trim()?.ifBlank { "kas_shift" } ?: "kas_shift"
        } else {
            null
        }
        val normalizedNote = note?.trim()?.ifBlank { null }
        val persistedStatus = PurchaseContract.resolvePersistedPurchaseStatus(
            paymentMethod = normalizedPaymentMethod,
            effectivePaymentStatus = effectivePaymentStatus,
        )

        val purchase = PurchaseEntity(
            localId = purchaseLocalId,
            serverId = null,
            purchaseNo = normalizedPurchaseNo,
            supplierLocalId = supplierLocalId,
            subtotal = subtotal,
            discountAmount = safeDiscount,
            totalAmount = total,
            status = persistedStatus,
            purchasedAt = now,
            dueDate = dueDate,
            syncStatus = SyncStatus.PENDING_SYNC,
            version = 1,
            updatedAtLocal = now,
            updatedAtServer = null,
        )

        val purchaseItems = items.map { draft ->
            PurchaseItemEntity(
                localId = UUID.randomUUID().toString(),
                purchaseLocalId = purchaseLocalId,
                productLocalId = draft.productLocalId.trim(),
                qty = draft.qty,
                unitCost = draft.unitCost,
                lineTotal = (draft.qty * draft.unitCost).toLong(),
            )
        }

        purchaseDao.insertFinalizedPurchase(purchase = purchase, items = purchaseItems)

        syncActionDao.enqueue(
            outboxAction(
                entityType = EntityType.PURCHASE,
                entityId = purchaseLocalId,
                actionType = SyncActionType.FINALIZE_PURCHASE,
                payloadJson = "{\"purchase_local_id\":\"$purchaseLocalId\",\"purchase_no\":\"${escapeJson(normalizedPurchaseNo)}\",\"total\":$total,\"status\":\"$persistedStatus\",\"payment_method\":\"${escapeJson(normalizedPaymentMethod)}\",\"payment_status\":\"${escapeJson(effectivePaymentStatus)}\",\"cash_source\":${jsonStringOrNull(normalizedCashSource)},\"note\":${jsonStringOrNull(normalizedNote)},\"source_module\":\"purchase\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":$approvalRequired}",
                now = now,
                idempotencyKey = "purchase-finalize-$purchaseLocalId",
            )
        )

        return purchaseLocalId
    }

    private suspend fun validatePurchaseItemsAgainstProductCatalog(items: List<PurchaseItemDraft>) {
        val resolver = productDao ?: return
        items.forEachIndexed { index, item ->
            val product = resolver.findByLocalId(item.productLocalId.trim())
                ?: throw IllegalArgumentException("Produk pada baris ${index + 1} tidak ditemukan di database.")
            require(isAllowedForPurchase(product)) {
                "Produk ${product.name} pada baris ${index + 1} tidak bisa dibeli sebagai stok (hanya bahan baku)."
            }
        }
    }

    private fun isAllowedForPurchase(product: ProductEntity): Boolean {
        val normalizedMode = product.productMode?.trim()?.lowercase(Locale.ROOT).orEmpty().ifBlank { "standard" }
        if (normalizedMode == "bom") {
            return false
        }
        val normalizedType = product.itemType?.trim()?.lowercase(Locale.ROOT).orEmpty()
        val normalizedItemType = when {
            normalizedMode in setOf("add_on", "addon", "add-on") -> "raw_material"
            normalizedType in setOf("add_on", "addon", "add-on") -> "raw_material"
            else -> normalizedType.ifBlank { "finished_good" }
        }
        return normalizedItemType == "raw_material"
    }

    suspend fun markPurchaseReceivedOffline(
        purchaseLocalId: String,
        receivedAt: Long = System.currentTimeMillis(),
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
        payloadTransactionDate: Long? = receivedAt,
        receiveItems: List<PurchaseReceiveItemDraft>? = null,
    ): Boolean {
        val normalizedId = purchaseLocalId.trim()
        require(normalizedId.isNotBlank()) { "Purchase id wajib diisi." }

        val existing = purchaseDao.findPurchaseByLocalId(normalizedId)
            ?: throw IllegalArgumentException("Data pembelian tidak ditemukan.")

        if (isPurchaseReceivedStatus(existing.status)) {
            return false
        }

        val receivePayload = buildReceivePayload(
            transactionDate = payloadTransactionDate,
            itemInputs = receiveItems?.map { item ->
                ReceivePayloadItemInput(
                    productLocalId = item.productLocalId,
                    qty = item.qty,
                )
            },
        )

        val updated = existing.copy(
            status = PurchaseContract.Status.RECEIVED,
            syncStatus = SyncStatus.PENDING_SYNC,
            version = existing.version + 1,
            updatedAtLocal = receivedAt,
            updatedAtServer = null,
        )

        purchaseDao.upsertPurchase(updated)

        syncActionDao.enqueue(
            outboxAction(
                entityType = EntityType.PURCHASE,
                entityId = normalizedId,
                actionType = SyncActionType.RECORD_STOCK_MOVEMENT,
                payloadJson = receivePayload,
                now = receivedAt,
                idempotencyKey = "purchase-received-$normalizedId",
            )
        )

        return true
    }

    suspend fun recordPurchaseReturnOffline(
        purchaseLocalId: String,
        reason: String,
        items: List<PurchaseReturnItemDraft>,
        returnedAt: Long = System.currentTimeMillis(),
        requestedByRole: String = "system",
        approvalRequired: Boolean = false,
    ): Boolean {
        val normalizedId = purchaseLocalId.trim()
        val normalizedReason = reason.trim()
        require(normalizedId.isNotBlank()) { "Purchase id wajib diisi." }
        require(normalizedReason.isNotBlank()) { "Alasan retur wajib diisi." }
        require(items.isNotEmpty()) { "Item retur wajib minimal 1 baris." }

        items.forEachIndexed { index, item ->
            require(item.productLocalId.trim().isNotBlank()) {
                "Produk retur pada baris ${index + 1} wajib diisi."
            }
            require(item.qty > 0.0) {
                "Qty retur pada baris ${index + 1} harus lebih besar dari 0."
            }
            require(item.unitCost >= 0) {
                "Harga retur pada baris ${index + 1} tidak boleh negatif."
            }
        }

        val existing = purchaseDao.findPurchaseByLocalId(normalizedId)
            ?: throw IllegalArgumentException("Data pembelian tidak ditemukan.")

        require(PurchaseContract.Status.allowsReturn(existing.status)) {
            "Retur hanya bisa diproses untuk pembelian yang sudah diterima."
        }

        val originalItems = purchaseDao.findPurchaseItemsByPurchaseLocalId(normalizedId)
        if (originalItems.isEmpty()) {
            throw IllegalArgumentException("Item pembelian tidak ditemukan.")
        }

        val purchaseServerId = resolveRequiredServerUuid(
            entityName = "Purchase",
            localId = normalizedId,
            serverId = existing.serverId,
        )
        val supplierLocalId = existing.supplierLocalId?.trim().orEmpty()
        require(supplierLocalId.isNotBlank()) {
            "Supplier wajib terhubung pada pembelian untuk payload return."
        }
        val supplierServerId = resolveRequiredServerUuid(
            entityName = "Supplier",
            localId = supplierLocalId,
            serverId = supplierDao.findByLocalId(supplierLocalId)?.serverId,
        )

        val purchasedQtyByProduct = originalItems
            .groupBy { it.productLocalId }
            .mapValues { (_, rows) -> rows.sumOf { it.qty } }

        val historicalReturnItems = purchaseDao.findPurchaseReturnItemsByPurchaseLocalId(normalizedId)
        val normalizedItems = normalizeReturnItems(items)
        if (isDuplicateReturnRequest(historicalReturnItems, normalizedReason, returnedAt, normalizedItems)) {
            emitMetric(
                name = "return_deduplicated",
                attributes = mapOf("purchase_local_id" to normalizedId),
            )
            return true
        }

        val historicalReturnedQtyByProduct = historicalReturnItems
            .groupBy { it.productLocalId }
            .mapValues { (_, rows) -> rows.sumOf { it.qty } }

        val returnQtyByProduct = normalizedItems
            .groupBy { it.productLocalId }
            .mapValues { (_, rows) -> rows.sumOf { it.qty } }

        returnQtyByProduct.forEach { (productId, returnQty) ->
            val purchasedQty = purchasedQtyByProduct[productId]
                ?: throw IllegalArgumentException("Produk $productId tidak ada di purchase awal.")
            val returnedBefore = historicalReturnedQtyByProduct[productId] ?: 0.0
            if (returnedBefore + returnQty > purchasedQty + EPSILON) {
                emitMetric(
                    name = "return_overflow_blocked",
                    attributes = mapOf(
                        "purchase_local_id" to normalizedId,
                        "product_local_id" to productId,
                    ),
                )
                throw IllegalArgumentException("Qty retur produk $productId melebihi qty pembelian.")
            }
        }

        val cumulativeReturnedQtyByProduct = purchasedQtyByProduct.keys.associateWith { productId ->
            (historicalReturnedQtyByProduct[productId] ?: 0.0) + (returnQtyByProduct[productId] ?: 0.0)
        }

        val isFullReturn = purchasedQtyByProduct.keys == cumulativeReturnedQtyByProduct.keys &&
            purchasedQtyByProduct.all { (productId, purchasedQty) ->
                (cumulativeReturnedQtyByProduct[productId] ?: 0.0) + EPSILON >= purchasedQty
            }

        val totalReturn = normalizedItems.sumOf { (it.qty * it.unitCost).toLong() }
        val nextStatus = if (isFullReturn) {
            PurchaseContract.Status.RETURNED_FULL
        } else {
            PurchaseContract.Status.RETURNED_PARTIAL
        }
        val returnPayload = buildReturnPayload(
            purchaseServerId = purchaseServerId,
            supplierServerId = supplierServerId,
            transactionDate = returnedAt,
            notes = null,
            itemInputs = normalizedItems.map { normalizedItem ->
                ReturnPayloadItemInput(
                    productLocalId = normalizedItem.productLocalId,
                    qty = normalizedItem.qty,
                    price = normalizedItem.unitCost,
                    reason = normalizedReason,
                )
            },
        )

        val updated = existing.copy(
            totalAmount = (existing.totalAmount - totalReturn).coerceAtLeast(0),
            status = nextStatus,
            syncStatus = SyncStatus.PENDING_SYNC,
            version = existing.version + 1,
            updatedAtLocal = returnedAt,
            updatedAtServer = null,
        )

        val returnFingerprint = buildReturnFingerprint(normalizedId, normalizedReason, returnedAt, normalizedItems)
        val returnRows = normalizedItems.map { item ->
            PurchaseReturnItemEntity(
                localId = deterministicReturnItemId(returnFingerprint, item),
                purchaseLocalId = normalizedId,
                productLocalId = item.productLocalId,
                qty = item.qty,
                unitCost = item.unitCost,
                reason = normalizedReason,
                returnedAt = returnedAt,
            )
        }

        purchaseDao.upsertPurchase(updated)
        purchaseDao.insertPurchaseReturnItems(returnRows)

        val outboxIdempotencyKey = deterministicOutboxIdempotencyKey(returnFingerprint)
        if (!syncActionDao.existsByIdempotencyKey(outboxIdempotencyKey)) {
            syncActionDao.enqueue(
                outboxAction(
                    entityType = EntityType.PURCHASE,
                    entityId = normalizedId,
                    actionType = SyncActionType.RECORD_PURCHASE_RETURN,
                    payloadJson = returnPayload,
                    now = returnedAt,
                    idempotencyKey = outboxIdempotencyKey,
                )
            )
        }

        emitMetric(
            name = "return_created",
            attributes = mapOf(
                "purchase_local_id" to normalizedId,
                "status" to nextStatus,
            ),
        )

        return true
    }

    private fun outboxAction(
        entityType: EntityType,
        entityId: String,
        actionType: SyncActionType,
        payloadJson: String,
        now: Long,
        idempotencyKey: String? = null,
    ): SyncActionEntity {
        return SyncActionEntity(
            actionId = UUID.randomUUID().toString(),
            entityType = entityType,
            entityId = entityId,
            actionType = actionType,
            payloadJson = payloadJson,
            idempotencyKey = idempotencyKey ?: UUID.randomUUID().toString(),
            retryCount = 0,
            nextRetryTime = now,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = now,
        )
    }

    private fun escapeJson(value: String): String {
        return value.replace("\\", "\\\\").replace("\"", "\\\"")
    }

    private fun jsonStringOrNull(value: String?): String {
        return value?.let { "\"${escapeJson(it)}\"" } ?: "null"
    }

    private fun isPurchaseReceivedStatus(status: String): Boolean {
        return PurchaseContract.Status.isReceivedFamily(status)
    }

    private suspend fun buildReceivePayload(
        transactionDate: Long?,
        itemInputs: List<ReceivePayloadItemInput>?,
    ): String {
        val sections = mutableListOf<String>()

        val serializedTransactionDate = serializeContractDate(transactionDate)
        if (serializedTransactionDate != null) {
            sections += "\"transaction_date\":\"${escapeJson(serializedTransactionDate)}\""
        }

        val normalizedItems = toReceivePayloadItems(itemInputs)
        if (normalizedItems.isNotEmpty()) {
            val itemsJson = normalizedItems.joinToString(",") { item ->
                "{\"product_id\":\"${escapeJson(item.productId)}\",\"qty\":${formatDecimal(item.qty)}}"
            }
            sections += "\"items\":[${itemsJson}]"
        }

        return if (sections.isEmpty()) "{}" else "{${sections.joinToString(",")}}"
    }

    private suspend fun toReceivePayloadItems(
        itemInputs: List<ReceivePayloadItemInput>?,
    ): List<ReceivePayloadItem> {
        if (itemInputs.isNullOrEmpty()) {
            return emptyList()
        }

        return itemInputs.mapIndexed { index, item ->
            val normalizedLocalId = item.productLocalId.trim()
            require(normalizedLocalId.isNotBlank()) {
                "Produk receive pada baris ${index + 1} wajib diisi."
            }
            require(item.qty > 0.0) {
                "Qty receive pada baris ${index + 1} harus lebih besar dari 0."
            }

            val product = productDao?.findByLocalId(normalizedLocalId)
                ?: throw IllegalArgumentException(
                    "Produk receive pada baris ${index + 1} tidak ditemukan untuk pemetaan backend UUID.",
                )
            val productServerId = resolveRequiredServerUuid(
                entityName = "Produk",
                localId = normalizedLocalId,
                serverId = product.serverId,
            )

            ReceivePayloadItem(
                productId = productServerId,
                qty = item.qty,
            )
        }
    }

    private fun resolveRequiredServerUuid(
        entityName: String,
        localId: String,
        serverId: String?,
    ): String {
        val normalizedServerId = serverId?.trim().orEmpty()
        if (normalizedServerId.isBlank()) {
            throw IllegalArgumentException(
                "$entityName dengan local id $localId belum punya backend UUID (serverId). Sinkronisasi diperlukan sebelum payload dikirim.",
            )
        }
        return normalizedServerId
    }

    private fun serializeContractDate(epochMillis: Long?): String? {
        return epochMillis?.let { Instant.ofEpochMilli(it).toString() }
    }

    private fun normalizeReturnItems(items: List<PurchaseReturnItemDraft>): List<NormalizedReturnItem> {
        return items
            .map { item ->
                NormalizedReturnItem(
                    productLocalId = item.productLocalId.trim(),
                    qty = item.qty,
                    unitCost = item.unitCost,
                )
            }
            .groupBy { it.productLocalId to it.unitCost }
            .map { (key, rows) ->
                NormalizedReturnItem(
                    productLocalId = key.first,
                    qty = rows.sumOf { it.qty },
                    unitCost = key.second,
                )
            }
            .sortedWith(compareBy<NormalizedReturnItem> { it.productLocalId }.thenBy { it.unitCost })
    }

    private fun isDuplicateReturnRequest(
        historicalItems: List<PurchaseReturnItemEntity>,
        reason: String,
        returnedAt: Long,
        requestedItems: List<NormalizedReturnItem>,
    ): Boolean {
        val groupedHistory = historicalItems
            .filter { it.reason == reason && kotlin.math.abs(it.returnedAt - returnedAt) <= RETURN_DUPLICATE_WINDOW_MS }
            .groupBy { it.returnedAt }

        return groupedHistory.any { (groupReturnedAt, groupRows) ->
            val matchingHistory = groupRows.groupBy { it.productLocalId to it.unitCost }
                .map { (key, rows) ->
                    NormalizedReturnItem(
                        productLocalId = key.first,
                        qty = rows.sumOf { it.qty },
                        unitCost = key.second,
                    )
                }
                .sortedWith(compareBy<NormalizedReturnItem> { it.productLocalId }.thenBy { it.unitCost })

            matchingHistory.size == requestedItems.size &&
                matchingHistory.zip(requestedItems).all { (saved, incoming) ->
                    saved.productLocalId == incoming.productLocalId &&
                        saved.unitCost == incoming.unitCost &&
                        kotlin.math.abs(saved.qty - incoming.qty) <= EPSILON &&
                        kotlin.math.abs(groupReturnedAt - returnedAt) <= RETURN_DUPLICATE_WINDOW_MS
                }
        }
    }

    private fun emitMetric(name: String, attributes: Map<String, String>) {
        onMetric(name, attributes)
    }

    private fun buildReturnItemsJson(items: List<NormalizedReturnItem>): String {
        return items.joinToString(",") { item ->
            "{\"product_local_id\":\"${escapeJson(item.productLocalId)}\",\"qty\":${formatDecimal(item.qty)},\"unit_cost\":${item.unitCost}}"
        }
    }

    private suspend fun buildReturnPayload(
        purchaseServerId: String,
        supplierServerId: String,
        transactionDate: Long?,
        notes: String?,
        itemInputs: List<ReturnPayloadItemInput>,
    ): String {
        require(itemInputs.isNotEmpty()) { "Item retur wajib minimal 1 baris." }

        val sections = mutableListOf<String>()
        sections += "\"purchase_id\":\"${escapeJson(purchaseServerId)}\""
        sections += "\"supplier_id\":\"${escapeJson(supplierServerId)}\""

        val serializedTransactionDate = serializeContractDate(transactionDate)
        if (serializedTransactionDate != null) {
            sections += "\"transaction_date\":\"${escapeJson(serializedTransactionDate)}\""
        }

        val normalizedNotes = notes?.trim()?.ifBlank { null }
        if (normalizedNotes != null) {
            sections += "\"notes\":\"${escapeJson(normalizedNotes)}\""
        }

        val normalizedItems = toReturnPayloadItems(itemInputs)
        val itemsJson = normalizedItems.joinToString(",") { item ->
            val itemSections = mutableListOf<String>()
            itemSections += "\"product_id\":\"${escapeJson(item.productId)}\""
            itemSections += "\"qty\":${formatDecimal(item.qty)}"
            itemSections += "\"price\":${item.price}"
            val normalizedReason = item.reason?.trim()?.ifBlank { null }
            if (normalizedReason != null) {
                itemSections += "\"reason\":\"${escapeJson(normalizedReason)}\""
            }
            "{${itemSections.joinToString(",")}}"
        }
        sections += "\"items\":[${itemsJson}]"

        return "{${sections.joinToString(",")}}"
    }

    private suspend fun toReturnPayloadItems(
        itemInputs: List<ReturnPayloadItemInput>,
    ): List<ReturnPayloadItem> {
        return itemInputs.mapIndexed { index, item ->
            val normalizedLocalId = item.productLocalId.trim()
            require(normalizedLocalId.isNotBlank()) {
                "Produk retur pada baris ${index + 1} wajib diisi."
            }
            require(item.qty > 0.0) {
                "Qty retur pada baris ${index + 1} harus lebih besar dari 0."
            }
            require(item.price >= 0) {
                "Harga retur pada baris ${index + 1} tidak boleh negatif."
            }

            val productServerId = resolveRequiredServerUuid(
                entityName = "Produk",
                localId = normalizedLocalId,
                serverId = productDao?.findByLocalId(normalizedLocalId)?.serverId,
            )

            ReturnPayloadItem(
                productId = productServerId,
                qty = item.qty,
                price = item.price,
                reason = item.reason,
            )
        }
    }

    private fun buildReturnFingerprint(
        purchaseLocalId: String,
        reason: String,
        returnedAt: Long,
        items: List<NormalizedReturnItem>,
    ): String {
        val itemsPart = items.joinToString("|") { item ->
            "${item.productLocalId}:${formatDecimal(item.qty)}:${item.unitCost}"
        }
        return "$purchaseLocalId#$reason#$returnedAt#$itemsPart"
    }

    private fun deterministicReturnItemId(
        fingerprint: String,
        item: NormalizedReturnItem,
    ): String {
        val itemPart = "${item.productLocalId}:${formatDecimal(item.qty)}:${item.unitCost}"
        return "ret-${sha256Hex("$fingerprint#$itemPart").take(24)}"
    }

    private fun deterministicOutboxIdempotencyKey(fingerprint: String): String {
        return "purchase-return-${sha256Hex(fingerprint)}"
    }

    private fun formatDecimal(value: Double): String {
        return String.format(Locale.US, "%.6f", value)
    }

    private fun sha256Hex(input: String): String {
        val digest = MessageDigest.getInstance("SHA-256").digest(input.toByteArray(Charsets.UTF_8))
        return digest.joinToString("") { byte -> "%02x".format(byte) }
    }
}
