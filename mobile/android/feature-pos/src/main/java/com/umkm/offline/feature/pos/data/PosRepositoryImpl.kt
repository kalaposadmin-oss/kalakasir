package com.umkm.offline.feature.pos.data

import android.util.Log
import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.CustomerDao
import com.umkm.offline.core.database.dao.InventoryLayerDao
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.RecipeDao
import com.umkm.offline.core.database.dao.SaleDao
import com.umkm.offline.core.database.dao.SaleFifoAllocationDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.entity.InventoryLayerEntity
import com.umkm.offline.core.database.entity.PaymentEntity
import com.umkm.offline.core.database.entity.SaleEntity
import com.umkm.offline.core.database.entity.SaleFifoAllocationEntity
import com.umkm.offline.core.database.entity.SaleItemEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import com.umkm.offline.feature.pos.domain.CheckoutItem
import com.umkm.offline.feature.pos.domain.PosCustomer
import com.umkm.offline.feature.pos.domain.PosProduct
import com.umkm.offline.feature.pos.domain.PosRepository
import com.umkm.offline.feature.pos.usecase.AllocateFifoLayersUseCase
import com.umkm.offline.feature.pos.usecase.FifoLayerSnapshot
import com.umkm.offline.feature.pos.usecase.ReverseFifoAllocationUseCase
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlin.math.roundToInt
import java.util.UUID

class PosRepositoryImpl(
    private val customerDao: CustomerDao,
    private val productDao: ProductDao,
    private val recipeDao: RecipeDao,
    private val saleDao: SaleDao,
    private val syncActionDao: SyncActionDao,
    private val inventoryLayerDao: InventoryLayerDao? = null,
    private val saleFifoAllocationDao: SaleFifoAllocationDao? = null,
    private val allowNegativeRawMaterialStockProvider: suspend () -> Boolean = { true },
    private val allocateFifoLayersUseCase: AllocateFifoLayersUseCase = AllocateFifoLayersUseCase(),
    private val reverseFifoAllocationUseCase: ReverseFifoAllocationUseCase = ReverseFifoAllocationUseCase(),
) : PosRepository {

    override fun observeProducts(keyword: String): Flow<List<PosProduct>> {
        val source = if (keyword.isBlank()) {
            productDao.observeProducts()
        } else {
            productDao.observeProductsByKeyword(keyword)
        }

        return source.map { products ->
            products.map {
                PosProduct(
                    localId = it.localId,
                    name = it.name,
                    categoryName = it.categoryName,
                    price = it.price,
                    itemType = it.itemType,
                    productMode = it.productMode,
                    stockOnHand = it.stockOnHand,
                )
            }
        }
    }

    override fun observeCustomers(): Flow<List<PosCustomer>> {
        return customerDao.observeCustomers().map { customers ->
            customers.map {
                PosCustomer(
                    localId = it.localId,
                    name = it.name,
                )
            }
        }
    }

    override suspend fun finalizeSaleOffline(
        receiptNo: String,
        customerLocalId: String?,
        items: List<CheckoutItem>,
        discountAmount: Long,
        paymentMethod: String,
        paidAmount: Long,
        shiftLocalId: String?,
    ): String {
        val now = System.currentTimeMillis()
        validateCheckoutReadiness(
            receiptNo = receiptNo,
            items = items,
            now = now,
        )
        val saleLocalId = UUID.randomUUID().toString()
        val subtotal = items.sumOf { (it.qty * it.unitPrice).toLong() }
        val total = subtotal - discountAmount

        val sale = SaleEntity(
            localId = saleLocalId,
            serverId = null,
            receiptNo = receiptNo,
            customerLocalId = customerLocalId,
            subtotal = subtotal,
            discountAmount = discountAmount,
            totalAmount = total,
            finalizedAt = now,
            syncStatus = SyncStatus.PENDING_SYNC,
            version = 1L,
            updatedAtLocal = now,
            updatedAtServer = null,
        )

        val saleItems = items.map {
            SaleItemEntity(
                localId = UUID.randomUUID().toString(),
                saleLocalId = saleLocalId,
                productLocalId = it.productLocalId,
                qty = it.qty,
                unitPrice = it.unitPrice,
                lineTotal = (it.qty * it.unitPrice).toLong(),
            )
        }

        val payment = PaymentEntity(
            localId = UUID.randomUUID().toString(),
            serverId = null,
            saleLocalId = saleLocalId,
            method = paymentMethod,
            amount = paidAmount,
            paidAt = now,
            syncStatus = SyncStatus.PENDING_SYNC,
            version = 1L,
            updatedAtLocal = now,
            updatedAtServer = null,
        )

        saleDao.insertFinalizedSale(sale = sale, items = saleItems, payment = payment)

        val fifoAllocationPayload = allocateSaleFifoLayers(
            saleLocalId = saleLocalId,
            saleItems = saleItems,
            now = now,
        )

        autoConsumeRecipeInputs(
            saleLocalId = saleLocalId,
            soldItems = items,
            now = now,
        )

        enqueueIfNotExists(
            outboxAction(
                entityType = EntityType.SALE,
                entityId = saleLocalId,
                actionType = SyncActionType.FINALIZE_SALE,
                payloadJson = "{\"sale_local_id\":\"$saleLocalId\",\"receipt_no\":\"${escapeJson(receiptNo)}\",\"total\":$total,\"shift_local_id\":\"${escapeJson(shiftLocalId.orEmpty())}\",\"fifo_allocations\":$fifoAllocationPayload}",
                now = now,
            )
        )
        enqueueIfNotExists(
            outboxAction(
                entityType = EntityType.PAYMENT,
                entityId = payment.localId,
                actionType = SyncActionType.RECORD_PAYMENT,
                payloadJson = "{\"payment_local_id\":\"${payment.localId}\",\"sale_local_id\":\"$saleLocalId\",\"method\":\"$paymentMethod\",\"amount\":$paidAmount}",
                now = now,
            )
        )
        return saleLocalId
    }

    suspend fun reverseSaleFifoAllocationsOffline(
        saleLocalId: String,
        reversalRef: String,
        now: Long = System.currentTimeMillis(),
    ): String {
        val layerDao = inventoryLayerDao ?: return "[]"
        val allocationDao = saleFifoAllocationDao ?: return "[]"

        val allocations = allocationDao.findActiveBySaleLocalId(saleLocalId)
        if (allocations.isEmpty()) {
            return "[]"
        }

        val currentByLayer = allocations
            .map { it.inventoryLayerLocalId }
            .distinct()
            .associateWith { layerId ->
                layerDao.findByLocalId(layerId)?.qtyAvailable ?: 0.0
            }

        val mappedAllocations = allocations.map {
            com.umkm.offline.feature.pos.usecase.FifoAllocationResult(
                layerLocalId = it.inventoryLayerLocalId,
                qtyIssued = it.qtyIssued,
                unitCost = it.unitCost,
                totalCost = it.totalCost,
                allocationOrder = it.allocationOrder,
            )
        }

        val reversed = reverseFifoAllocationUseCase.reverse(
            allocations = mappedAllocations,
            currentByLayer = currentByLayer,
        )

        val layerUpdates = mutableListOf<InventoryLayerEntity>()
        for ((layerId, restoredQty) in reversed.restoredByLayer) {
            val existing = layerDao.findByLocalId(layerId)
            if (existing != null) {
                layerUpdates += existing.copy(
                    qtyAvailable = restoredQty,
                    updatedAtLocal = now,
                )
            }
        }
        if (layerUpdates.isNotEmpty()) {
            layerDao.upsertAll(layerUpdates)
        }

        val productRestores = allocations.groupBy { it.productLocalId }
            .mapValues { (_, rows) -> rows.sumOf { it.qtyIssued } }
        for ((productId, restoredQty) in productRestores) {
            val product = productDao.findByLocalId(productId) ?: continue
            productDao.upsert(
                product.copy(
                    stockOnHand = product.stockOnHand + restoredQty,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = product.version + 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
            )
        }

        allocations.forEach { allocation ->
            allocationDao.markReversed(
                allocationLocalId = allocation.localId,
                reversedAt = now,
                reversalRef = reversalRef,
            )
        }

        return allocations.joinToString(prefix = "[", postfix = "]") { allocation ->
            "{\"allocation_local_id\":\"${allocation.localId}\",\"layer_local_id\":\"${allocation.inventoryLayerLocalId}\",\"product_local_id\":\"${allocation.productLocalId}\",\"qty\":${allocation.qtyIssued},\"unit_cost\":${allocation.unitCost}}"
        }
    }

    private suspend fun allocateSaleFifoLayers(
        saleLocalId: String,
        saleItems: List<SaleItemEntity>,
        now: Long,
    ): String {
        val layerDao = inventoryLayerDao ?: return "[]"
        val allocationDao = saleFifoAllocationDao ?: return "[]"

        val allAllocations = mutableListOf<SaleFifoAllocationEntity>()
        val jsonChunks = mutableListOf<String>()

        for (saleItem in saleItems) {
            val product = productDao.findByLocalId(saleItem.productLocalId)
                ?: throw IllegalStateException("Produk ${saleItem.productLocalId} tidak ditemukan.")

            ensureOpeningLayerIfMissing(
                layerDao = layerDao,
                product = product,
                now = now,
            )

            val layers = layerDao.findAvailableLayersByProduct(saleItem.productLocalId)
            val outcome = allocateFifoLayersUseCase.allocate(
                layers = layers.map {
                    FifoLayerSnapshot(
                        layerLocalId = it.localId,
                        qtyAvailable = it.qtyAvailable,
                        unitCost = it.unitCost,
                    )
                },
                requestedQty = saleItem.qty,
            )

            val updatedLayers = layers.map { layer ->
                val nextQty = outcome.remainingByLayer[layer.localId] ?: layer.qtyAvailable
                layer.copy(
                    qtyAvailable = nextQty,
                    updatedAtLocal = now,
                )
            }
            layerDao.upsertAll(updatedLayers)

            val productNextStock = (product.stockOnHand - saleItem.qty).coerceAtLeast(0.0)
            productDao.upsert(
                product.copy(
                    stockOnHand = productNextStock,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = product.version + 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
            )

            val allocationRows = outcome.allocations.map { allocation ->
                SaleFifoAllocationEntity(
                    localId = UUID.randomUUID().toString(),
                    saleLocalId = saleLocalId,
                    saleItemLocalId = saleItem.localId,
                    productLocalId = saleItem.productLocalId,
                    inventoryLayerLocalId = allocation.layerLocalId,
                    qtyIssued = allocation.qtyIssued,
                    unitCost = allocation.unitCost,
                    totalCost = allocation.totalCost,
                    allocationOrder = allocation.allocationOrder,
                    createdAt = now,
                    reversedAt = null,
                    reversalRef = null,
                )
            }
            allAllocations += allocationRows

            jsonChunks += allocationRows.joinToString(
                prefix = "",
                postfix = "",
            ) { row ->
                "{\"sale_item_local_id\":\"${row.saleItemLocalId}\",\"product_local_id\":\"${row.productLocalId}\",\"layer_local_id\":\"${row.inventoryLayerLocalId}\",\"qty\":${row.qtyIssued},\"unit_cost\":${row.unitCost},\"allocation_order\":${row.allocationOrder}}"
            }
        }

        if (allAllocations.isNotEmpty()) {
            allocationDao.insertAll(allAllocations)
        }

        return jsonChunks.joinToString(prefix = "[", postfix = "]")
    }

    private suspend fun ensureOpeningLayerIfMissing(
        layerDao: InventoryLayerDao,
        product: com.umkm.offline.core.database.entity.ProductEntity,
        now: Long,
    ) {
        val existing = layerDao.findAvailableLayersByProduct(product.localId)
        if (existing.isNotEmpty() || product.stockOnHand <= 0.0) {
            return
        }

        layerDao.upsert(
            InventoryLayerEntity(
                localId = UUID.randomUUID().toString(),
                productLocalId = product.localId,
                qtyOriginal = product.stockOnHand,
                qtyAvailable = product.stockOnHand,
                unitCost = product.price.toDouble(),
                sourceType = "opening_balance",
                sourceRef = product.localId,
                receivedAt = now,
                updatedAtLocal = now,
            ),
        )
    }

    private suspend fun autoConsumeRecipeInputs(
        saleLocalId: String,
        soldItems: List<CheckoutItem>,
        now: Long,
    ) {
        val allowNegativeRawMaterialStock = allowNegativeRawMaterialStockProvider()
        val consumptionByMaterial = linkedMapOf<String, Double>()
        debugLog("start sale=$saleLocalId sold_items=${soldItems.size} allow_negative=$allowNegativeRawMaterialStock")

        for (sold in soldItems) {
            val soldProduct = productDao.findByLocalId(sold.productLocalId)
            val recipeLines = recipeDao.findByProductLocalId(sold.productLocalId)
            debugLog("inspect_product=${sold.productLocalId} recipe_lines=${recipeLines.size} item_type=${soldProduct?.itemType.orEmpty()}")

            if (recipeLines.isNotEmpty()) {
                if (soldProduct == null || !hasItemType(soldProduct.itemType, ITEM_TYPE_FINISHED_GOOD)) {
                    debugLog("reject_output_type product=${sold.productLocalId} item_type=${soldProduct?.itemType.orEmpty()}")
                    throw AutoConsumeParityException(
                        code = "RECIPE_OUTPUT_TYPE_INVALID",
                        message = "Produk output recipe tidak eligible untuk auto-consume.",
                    )
                }
            }

            for (recipeLine in recipeLines) {
                val materialId = recipeLine.materialProductLocalId
                    ?: throw AutoConsumeParityException(
                        code = "RECIPE_INPUT_TYPE_INVALID",
                        message = "Komponen recipe tidak valid.",
                    )

                val material = productDao.findByLocalId(materialId)
                    ?: throw AutoConsumeParityException(
                        code = "RECIPE_INPUT_TYPE_INVALID",
                        message = "Komponen recipe tidak ditemukan.",
                    )

                if (!hasItemType(material.itemType, ITEM_TYPE_RAW_MATERIAL)) {
                    debugLog("reject_input_type material=$materialId item_type=${material.itemType.orEmpty()}")
                    throw AutoConsumeParityException(
                        code = "RECIPE_INPUT_TYPE_INVALID",
                        message = "Input recipe harus raw material.",
                    )
                }

                val consumedQty = sold.qty * recipeLine.qty
                if (consumedQty <= 0.0) {
                    continue
                }

                consumptionByMaterial[materialId] = (consumptionByMaterial[materialId] ?: 0.0) + consumedQty
            }
        }

        for ((materialId, consumedQty) in consumptionByMaterial) {
            val material = productDao.findByLocalId(materialId)
                ?: throw AutoConsumeParityException(
                    code = "RECIPE_INPUT_TYPE_INVALID",
                    message = "Komponen recipe tidak ditemukan saat konsumsi.",
                )

            if (!allowNegativeRawMaterialStock && material.stockOnHand < consumedQty) {
                debugLog("insufficient_stock material=$materialId on_hand=${material.stockOnHand} required=$consumedQty")
                throw AutoConsumeParityException(
                    code = "AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL",
                    message = "Stok bahan baku tidak mencukupi untuk auto-consume.",
                )
            }

            val updatedMaterial = material.copy(
                stockOnHand = material.stockOnHand - consumedQty,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = material.version + 1,
                updatedAtLocal = now,
            )
            productDao.upsert(updatedMaterial)

            val movementId = UUID.randomUUID().toString()
            enqueueIfNotExists(
                outboxAction(
                    entityType = EntityType.STOCK_MOVEMENT,
                    entityId = movementId,
                    actionType = SyncActionType.RECORD_STOCK_MOVEMENT,
                    payloadJson = "{\"stock_movement_local_id\":\"$movementId\",\"product_local_id\":\"$materialId\",\"direction\":\"out\",\"qty\":$consumedQty,\"reason\":\"sale_recipe_input\",\"reference_sale_local_id\":\"$saleLocalId\"}",
                    now = now,
                ),
            )
        }

        debugLog("completed sale=$saleLocalId consumed_materials=${consumptionByMaterial.size} material_ids=${consumptionByMaterial.keys}")
    }

    private suspend fun validateCheckoutReadiness(
        receiptNo: String,
        items: List<CheckoutItem>,
        now: Long,
    ) {
        val requestedByProduct = linkedMapOf<String, Double>()
        for (item in items) {
            requestedByProduct[item.productLocalId] = (requestedByProduct[item.productLocalId] ?: 0.0) + item.qty
        }

        val recipeDemandByMaterial = linkedMapOf<String, Double>()
        val recipeMaterialStockCache = linkedMapOf<String, Double>()

        for ((productLocalId, requestedQty) in requestedByProduct) {
            val product = productDao.findByLocalId(productLocalId)
            if (product == null) {
                debugCheckoutLog(
                    receiptNo = receiptNo,
                    productLocalId = productLocalId,
                    availableStock = 0.0,
                    requestedQty = requestedQty,
                    fifoResult = "skipped_product_missing",
                    recipeResult = "skipped_product_missing",
                )
                throw CheckoutGuardException(
                    code = "PRODUCT_NOT_READY",
                    message = "Produk belum siap dijual",
                    detail = CheckoutValidationDetail(
                        receiptNo = receiptNo,
                        productLocalId = productLocalId,
                        availableStock = 0.0,
                        requestedQty = requestedQty,
                        fifoResult = "skipped_product_missing",
                        recipeResult = "skipped_product_missing",
                    ),
                )
            }

            val normalizedType = product.itemType?.trim()?.lowercase().orEmpty()
            val normalizedMode = product.productMode?.trim()?.lowercase().orEmpty().ifBlank { "standard" }
            if (!isSellableProduct(normalizedType = normalizedType, normalizedMode = normalizedMode)) {
                debugCheckoutLog(
                    receiptNo = receiptNo,
                    productLocalId = product.localId,
                    availableStock = product.stockOnHand,
                    requestedQty = requestedQty,
                    fifoResult = "skipped_not_sellable",
                    recipeResult = "skipped_not_sellable",
                )
                throw CheckoutGuardException(
                    code = "PRODUCT_NOT_SELLABLE",
                    message = "Produk belum siap dijual",
                    detail = CheckoutValidationDetail(
                        receiptNo = receiptNo,
                        productLocalId = product.localId,
                        availableStock = product.stockOnHand,
                        requestedQty = requestedQty,
                        fifoResult = "skipped_not_sellable",
                        recipeResult = "skipped_not_sellable",
                    ),
                )
            }

            if (product.price <= 0L) {
                debugCheckoutLog(
                    receiptNo = receiptNo,
                    productLocalId = product.localId,
                    availableStock = product.stockOnHand,
                    requestedQty = requestedQty,
                    fifoResult = "skipped_invalid_price",
                    recipeResult = "skipped_invalid_price",
                )
                throw CheckoutGuardException(
                    code = "INVALID_SELL_PRICE",
                    message = "Harga jual belum diisi",
                    detail = CheckoutValidationDetail(
                        receiptNo = receiptNo,
                        productLocalId = product.localId,
                        availableStock = product.stockOnHand,
                        requestedQty = requestedQty,
                        fifoResult = "skipped_invalid_price",
                        recipeResult = "skipped_invalid_price",
                    ),
                )
            }

            if (product.stockOnHand < requestedQty) {
                debugCheckoutLog(
                    receiptNo = receiptNo,
                    productLocalId = product.localId,
                    availableStock = product.stockOnHand,
                    requestedQty = requestedQty,
                    fifoResult = "skipped_stock_shortage",
                    recipeResult = "skipped_stock_shortage",
                )
                throw CheckoutGuardException(
                    code = "STOCK_INSUFFICIENT",
                    message = "Stok tidak cukup",
                    detail = CheckoutValidationDetail(
                        receiptNo = receiptNo,
                        productLocalId = product.localId,
                        availableStock = product.stockOnHand,
                        requestedQty = requestedQty,
                        fifoResult = "skipped_stock_shortage",
                        recipeResult = "skipped_stock_shortage",
                    ),
                )
            }

            val fifoResult = if (inventoryLayerDao == null) {
                "skipped_no_layer_dao"
            } else {
                ensureOpeningLayerIfMissing(
                    layerDao = inventoryLayerDao,
                    product = product,
                    now = now,
                )
                val layers = inventoryLayerDao.findAvailableLayersByProduct(product.localId)
                val availableFromLayers = layers.sumOf { it.qtyAvailable }
                if (availableFromLayers < requestedQty) {
                    debugCheckoutLog(
                        receiptNo = receiptNo,
                        productLocalId = product.localId,
                        availableStock = product.stockOnHand,
                        requestedQty = requestedQty,
                        fifoResult = "insufficient($availableFromLayers)",
                        recipeResult = "pending",
                    )
                    throw CheckoutGuardException(
                        code = "FIFO_LAYER_INSUFFICIENT",
                        message = "Stok FIFO tidak cukup",
                        detail = CheckoutValidationDetail(
                            receiptNo = receiptNo,
                            productLocalId = product.localId,
                            availableStock = product.stockOnHand,
                            requestedQty = requestedQty,
                            fifoResult = "insufficient($availableFromLayers)",
                            recipeResult = "pending",
                        ),
                    )
                }
                "ok($availableFromLayers)"
            }

            val recipeLines = recipeDao.findByProductLocalId(product.localId)
            var recipeResult = "not_applicable"
            if (normalizedMode == "bom") {
                if (recipeLines.isEmpty()) {
                    debugCheckoutLog(
                        receiptNo = receiptNo,
                        productLocalId = product.localId,
                        availableStock = product.stockOnHand,
                        requestedQty = requestedQty,
                        fifoResult = fifoResult,
                        recipeResult = "missing_recipe",
                    )
                    throw CheckoutGuardException(
                        code = "PRODUCT_NOT_READY",
                        message = "Produk belum siap dijual",
                        detail = CheckoutValidationDetail(
                            receiptNo = receiptNo,
                            productLocalId = product.localId,
                            availableStock = product.stockOnHand,
                            requestedQty = requestedQty,
                            fifoResult = fifoResult,
                            recipeResult = "missing_recipe",
                        ),
                    )
                }

                for (line in recipeLines) {
                    val materialId = line.materialProductLocalId
                    if (materialId.isNullOrBlank()) {
                        debugCheckoutLog(
                            receiptNo = receiptNo,
                            productLocalId = product.localId,
                            availableStock = product.stockOnHand,
                            requestedQty = requestedQty,
                            fifoResult = fifoResult,
                            recipeResult = "invalid_recipe_input",
                        )
                        throw CheckoutGuardException(
                            code = "PRODUCT_NOT_READY",
                            message = "Produk belum siap dijual",
                            detail = CheckoutValidationDetail(
                                receiptNo = receiptNo,
                                productLocalId = product.localId,
                                availableStock = product.stockOnHand,
                                requestedQty = requestedQty,
                                fifoResult = fifoResult,
                                recipeResult = "invalid_recipe_input",
                            ),
                        )
                    }

                    val consumedQty = requestedQty * line.qty
                    if (consumedQty <= 0.0) {
                        continue
                    }
                    recipeDemandByMaterial[materialId] = (recipeDemandByMaterial[materialId] ?: 0.0) + consumedQty
                }
                recipeResult = "pending_material_check"
            }

            debugCheckoutLog(
                receiptNo = receiptNo,
                productLocalId = product.localId,
                availableStock = product.stockOnHand,
                requestedQty = requestedQty,
                fifoResult = fifoResult,
                recipeResult = recipeResult,
            )
        }

        for ((materialId, requiredQty) in recipeDemandByMaterial) {
            val material = productDao.findByLocalId(materialId)
            val availableStock = material?.stockOnHand ?: 0.0
            recipeMaterialStockCache[materialId] = availableStock
            if (material == null || availableStock < requiredQty) {
                debugCheckoutLog(
                    receiptNo = receiptNo,
                    productLocalId = materialId,
                    availableStock = availableStock,
                    requestedQty = requiredQty,
                    fifoResult = "n/a",
                    recipeResult = "insufficient_material",
                )
                throw CheckoutGuardException(
                    code = "RECIPE_MATERIAL_INSUFFICIENT",
                    message = "Bahan resep tidak cukup",
                    detail = CheckoutValidationDetail(
                        receiptNo = receiptNo,
                        productLocalId = materialId,
                        availableStock = availableStock,
                        requestedQty = requiredQty,
                        fifoResult = "n/a",
                        recipeResult = "insufficient_material",
                    ),
                )
            }
        }

        for ((materialId, requiredQty) in recipeDemandByMaterial) {
            debugCheckoutLog(
                receiptNo = receiptNo,
                productLocalId = materialId,
                availableStock = recipeMaterialStockCache[materialId] ?: 0.0,
                requestedQty = requiredQty,
                fifoResult = "n/a",
                recipeResult = "ok_material",
            )
        }
    }

    private fun isSellableProduct(normalizedType: String, normalizedMode: String): Boolean {
        if (normalizedMode in setOf("add_on", "addon", "add-on")) {
            return false
        }
        if (normalizedType in setOf("raw_material", "raw", "material", "ingredient", "bahan_baku")) {
            return false
        }
        return normalizedMode in setOf("standard", "bom", "variant") && normalizedType in setOf("", "finished_good")
    }

    private fun debugCheckoutLog(
        receiptNo: String,
        productLocalId: String,
        availableStock: Double,
        requestedQty: Double,
        fifoResult: String,
        recipeResult: String,
    ) {
        runCatching {
            Log.d(
                "CheckoutGuard",
                "receiptNo=$receiptNo, productLocalId=$productLocalId, availableStock=${availableStock.roundToInt()}, requestedQty=$requestedQty, fifoValidation=$fifoResult, recipeValidation=$recipeResult",
            )
        }
    }

    private fun hasItemType(value: String?, expected: String): Boolean {
        return value?.trim()?.lowercase() == expected
    }

    private fun escapeJson(value: String): String {
        return value.replace("\\", "\\\\").replace("\"", "\\\"")
    }

    private suspend fun enqueueIfNotExists(action: SyncActionEntity) {
        if (!syncActionDao.existsByIdempotencyKey(action.idempotencyKey)) {
            syncActionDao.enqueue(action)
        }
    }

    private fun outboxAction(
        entityType: EntityType,
        entityId: String,
        actionType: SyncActionType,
        payloadJson: String,
        now: Long,
    ): SyncActionEntity {
        return SyncActionEntity(
            actionId = UUID.randomUUID().toString(),
            entityType = entityType,
            entityId = entityId,
            actionType = actionType,
            payloadJson = payloadJson,
            idempotencyKey = "${actionType.name.lowercase()}:$entityId",
            retryCount = 0,
            nextRetryTime = now,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = now,
        )
    }

    private fun debugLog(message: String) {
        runCatching { Log.d("AutoConsume", message) }
    }

    private companion object {
        const val ITEM_TYPE_RAW_MATERIAL = "raw_material"
        const val ITEM_TYPE_FINISHED_GOOD = "finished_good"
    }
}

class AutoConsumeParityException(
    val code: String,
    override val message: String,
) : IllegalStateException("$code: $message")

data class CheckoutValidationDetail(
    val receiptNo: String,
    val productLocalId: String,
    val availableStock: Double,
    val requestedQty: Double,
    val fifoResult: String,
    val recipeResult: String,
)

class CheckoutGuardException(
    val code: String,
    override val message: String,
    val detail: CheckoutValidationDetail,
) : IllegalStateException("$code: $message")
