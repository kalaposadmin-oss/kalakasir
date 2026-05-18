package com.umkm.offline.feature.pos.data

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
import com.umkm.offline.core.database.dao.SaleItemWithProductNameRow
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.SyncActionTypeCountRow
import com.umkm.offline.core.database.entity.CustomerEntity
import com.umkm.offline.core.database.entity.InventoryLayerEntity
import com.umkm.offline.core.database.entity.PaymentEntity
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.RecipeItemEntity
import com.umkm.offline.core.database.entity.SaleEntity
import com.umkm.offline.core.database.entity.SaleFifoAllocationEntity
import com.umkm.offline.core.database.entity.SaleItemEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import com.umkm.offline.feature.pos.domain.CheckoutItem
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class PosRepositoryImplTest {

    @Test
    fun finalizeSaleOffline_persistsSaleAsPendingSync() = runTest {
        val productDao = FakeProductDao()
        val recipeDao = FakeRecipeDao()
        val customerDao = FakeCustomerDao()
        val saleDao = FakeSaleDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(customerDao, productDao, recipeDao, saleDao, syncActionDao)

        val saleId = repository.finalizeSaleOffline(
            receiptNo = "TRX-001",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 2.0, unitPrice = 15000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 30000,
            shiftLocalId = "shift-001",
        )

        val storedSale = saleDao.lastSale
        assertNotNull(storedSale)
        assertEquals(saleId, storedSale?.localId)
        assertEquals(SyncStatus.PENDING_SYNC, storedSale?.syncStatus)
        assertEquals(30000L, storedSale?.totalAmount)
    }

    @Test
    fun finalizeSaleOffline_enqueuesOutboxActions() = runTest {
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), FakeProductDao(), FakeRecipeDao(), FakeSaleDao(), syncDao)

        repository.finalizeSaleOffline(
            receiptNo = "TRX-002",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 20000)),
            discountAmount = 0,
            paymentMethod = "transfer",
            paidAmount = 20000,
            shiftLocalId = "shift-002",
        )

        assertEquals(2, syncDao.actions.size)
        val actionTypes = syncDao.actions.map { it.actionType }.toSet()
        assertTrue(actionTypes.contains(SyncActionType.FINALIZE_SALE))
        assertTrue(actionTypes.contains(SyncActionType.RECORD_PAYMENT))
        assertTrue(syncDao.actions.all { it.actionStatus == OutboxStatus.PENDING })
        assertTrue(syncDao.actions.all { it.entityType == EntityType.SALE || it.entityType == EntityType.PAYMENT })
        val saleFinalizePayload = syncDao.actions.first { it.actionType == SyncActionType.FINALIZE_SALE }.payloadJson
        assertTrue(saleFinalizePayload.contains("\"shift_local_id\":\"shift-002\""))
    }

    @Test
    fun finalizeSaleOffline_storesPaymentPendingSync() = runTest {
        val saleDao = FakeSaleDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), FakeProductDao(), FakeRecipeDao(), saleDao, FakeSyncActionDao())

        repository.finalizeSaleOffline(
            receiptNo = "TRX-003",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 10000,
            shiftLocalId = "shift-003",
        )

        assertEquals(SyncStatus.PENDING_SYNC, saleDao.lastPayment?.syncStatus)
        assertEquals("cash", saleDao.lastPayment?.method)
    }

    @Test
    fun finalizeSaleOffline_consumesRecipeMaterials_andEnqueuesStockMovement() = runTest {
        val productDao = FakeProductDao()
        val recipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-1",
                    productLocalId = "prd-1",
                    materialProductLocalId = "mat-1",
                    materialName = "Gula",
                    qty = 2.0,
                    unit = "gram",
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = null,
                ),
            ),
        )
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), productDao, recipeDao, FakeSaleDao(), syncDao)

        repository.finalizeSaleOffline(
            receiptNo = "TRX-004",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 10000,
            shiftLocalId = "shift-004",
        )

        val updatedMaterial = productDao.findByLocalId("mat-1")
        assertNotNull(updatedMaterial)
        assertEquals(8.0, updatedMaterial!!.stockOnHand, 0.0001)

        val actionTypes = syncDao.actions.map { it.actionType }
        assertTrue(actionTypes.contains(SyncActionType.RECORD_STOCK_MOVEMENT))
    }

    @Test
    fun `PARITY-RECIPE-001 valid recipe sale auto-consumes raw material and emits sale_recipe_input movement`() = runTest {
        val productDao = FakeProductDao()
        val recipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-2",
                    productLocalId = "prd-1",
                    materialProductLocalId = "mat-1",
                    materialName = "Gula",
                    qty = 2.0,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), productDao, recipeDao, FakeSaleDao(), syncDao)

        val saleLocalId = repository.finalizeSaleOffline(
            receiptNo = "TRX-RECIPE-001",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 10000,
            shiftLocalId = "shift-recipe-001",
        )

        val material = productDao.findByLocalId("mat-1")
        assertNotNull(material)
        assertEquals(8.0, material!!.stockOnHand, 0.0001)

        val movement = syncDao.actions.firstOrNull { it.actionType == SyncActionType.RECORD_STOCK_MOVEMENT }
        assertNotNull(movement)
        val payload = movement!!.payloadJson
        assertTrue(payload.contains("\"reason\":\"sale_recipe_input\""))
        assertTrue(payload.contains("\"direction\":\"out\""))
        assertTrue(payload.contains("\"reference_sale_local_id\":\"$saleLocalId\""))
    }

    @Test
    fun `PARITY-RECIPE-002 invalid recipe input type rejects and does not mutate stock or outbox`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct(),
                defaultMaterialProduct().copy(itemType = "finished_good"),
            ),
        )
        val recipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-invalid-input",
                    productLocalId = "prd-1",
                    materialProductLocalId = "mat-1",
                    materialName = "Komponen Salah Tipe",
                    qty = 1.0,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), productDao, recipeDao, FakeSaleDao(), syncDao)

        val error = assertAutoConsumeError {
            repository.finalizeSaleOffline(
                receiptNo = "TRX-RECIPE-002",
                customerLocalId = null,
                items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
                discountAmount = 0,
                paymentMethod = "cash",
                paidAmount = 10000,
                shiftLocalId = "shift-recipe-002",
            )
        }
        assertEquals("RECIPE_INPUT_TYPE_INVALID", error.code)
        val material = productDao.findByLocalId("mat-1")
        assertNotNull(material)
        assertEquals(10.0, material!!.stockOnHand, 0.0001)
        assertTrue(syncDao.actions.none { it.actionType == SyncActionType.RECORD_STOCK_MOVEMENT })
    }

    @Test
    fun `PARITY-RECIPE-003 output non finished good rejects auto-consume`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct().copy(itemType = "raw_material"),
                defaultMaterialProduct(),
            ),
        )
        val recipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-invalid-output",
                    productLocalId = "prd-1",
                    materialProductLocalId = "mat-1",
                    materialName = "Gula",
                    qty = 1.0,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), productDao, recipeDao, FakeSaleDao(), syncDao)

        val error = try {
            repository.finalizeSaleOffline(
                receiptNo = "TRX-RECIPE-003",
                customerLocalId = null,
                items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
                discountAmount = 0,
                paymentMethod = "cash",
                paidAmount = 10000,
                shiftLocalId = "shift-recipe-003",
            )
            throw AssertionError("Expected checkout guard rejection to be thrown")
        } catch (guard: CheckoutGuardException) {
            guard
        }
        assertEquals("PRODUCT_NOT_SELLABLE", error.code)
        val material = productDao.findByLocalId("mat-1")
        assertNotNull(material)
        assertEquals(10.0, material!!.stockOnHand, 0.0001)
        assertTrue(syncDao.actions.none { it.actionType == SyncActionType.RECORD_STOCK_MOVEMENT })
    }

    @Test
    fun `PARITY-RECIPE-004 insufficient raw material policy mirrors reject and allow-minus`() = runTest {
        val strictProductDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct(),
                defaultMaterialProduct().copy(stockOnHand = 1.0),
            ),
        )
        val strictRecipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-stock-policy-strict",
                    productLocalId = "prd-1",
                    materialProductLocalId = "mat-1",
                    materialName = "Gula",
                    qty = 2.0,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val strictSyncDao = FakeSyncActionDao()
        val strictRepository = PosRepositoryImpl(
            customerDao = FakeCustomerDao(),
            productDao = strictProductDao,
            recipeDao = strictRecipeDao,
            saleDao = FakeSaleDao(),
            syncActionDao = strictSyncDao,
            allowNegativeRawMaterialStockProvider = { false },
        )

        val strictError = assertAutoConsumeError {
            strictRepository.finalizeSaleOffline(
                receiptNo = "TRX-RECIPE-004-STRICT",
                customerLocalId = null,
                items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
                discountAmount = 0,
                paymentMethod = "cash",
                paidAmount = 10000,
                shiftLocalId = "shift-recipe-004-strict",
            )
        }
        assertEquals("AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL", strictError.code)
        val strictMaterial = strictProductDao.findByLocalId("mat-1")
        assertNotNull(strictMaterial)
        assertEquals(1.0, strictMaterial!!.stockOnHand, 0.0001)
        assertTrue(strictSyncDao.actions.none { it.actionType == SyncActionType.RECORD_STOCK_MOVEMENT })

        val permissiveProductDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct(),
                defaultMaterialProduct().copy(stockOnHand = 1.0),
            ),
        )
        val permissiveRecipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-stock-policy-permissive",
                    productLocalId = "prd-1",
                    materialProductLocalId = "mat-1",
                    materialName = "Gula",
                    qty = 2.0,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val permissiveSyncDao = FakeSyncActionDao()
        val permissiveRepository = PosRepositoryImpl(
            customerDao = FakeCustomerDao(),
            productDao = permissiveProductDao,
            recipeDao = permissiveRecipeDao,
            saleDao = FakeSaleDao(),
            syncActionDao = permissiveSyncDao,
            allowNegativeRawMaterialStockProvider = { true },
        )

        permissiveRepository.finalizeSaleOffline(
            receiptNo = "TRX-RECIPE-004-PERMISSIVE",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 1.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 10000,
            shiftLocalId = "shift-recipe-004-permissive",
        )

        val permissiveMaterial = permissiveProductDao.findByLocalId("mat-1")
        assertNotNull(permissiveMaterial)
        assertEquals(-1.0, permissiveMaterial!!.stockOnHand, 0.0001)
        assertTrue(permissiveSyncDao.actions.any { it.actionType == SyncActionType.RECORD_STOCK_MOVEMENT })
    }

    @Test
    fun `runtime-scenario add-on materials are auto-consumed when selling recipe output`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                ProductEntity(
                    localId = "prd-kopi-susu",
                    serverId = null,
                    name = "Kopi Susu",
                    categoryName = "Minuman",
                    itemType = "finished_good",
                    productMode = "bom",
                    barcode = null,
                    price = 22000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
                ProductEntity(
                    localId = "mat-gula",
                    serverId = null,
                    name = "Gula",
                    categoryName = "Bahan Baku",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 1000,
                    stockOnHand = 20.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
                ProductEntity(
                    localId = "mat-susu",
                    serverId = null,
                    name = "Susu",
                    categoryName = "Bahan Baku",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 3000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
                ProductEntity(
                    localId = "mat-kopi",
                    serverId = null,
                    name = "Kopi Bubuk",
                    categoryName = "Bahan Baku",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 5000,
                    stockOnHand = 5.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val recipeDao = FakeRecipeDao(
            items = listOf(
                RecipeItemEntity(
                    localId = "recipe-gula",
                    productLocalId = "prd-kopi-susu",
                    materialProductLocalId = "mat-gula",
                    materialName = "Gula",
                    qty = 2.0,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
                RecipeItemEntity(
                    localId = "recipe-susu",
                    productLocalId = "prd-kopi-susu",
                    materialProductLocalId = "mat-susu",
                    materialName = "Susu",
                    qty = 1.0,
                    unit = "ml",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
                RecipeItemEntity(
                    localId = "recipe-kopi",
                    productLocalId = "prd-kopi-susu",
                    materialProductLocalId = "mat-kopi",
                    materialName = "Kopi Bubuk",
                    qty = 0.5,
                    unit = "gram",
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(FakeCustomerDao(), productDao, recipeDao, FakeSaleDao(), syncDao)

        repository.finalizeSaleOffline(
            receiptNo = "TRX-RUNTIME-RECIPE-001",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-kopi-susu", qty = 1.0, unitPrice = 22000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 22000,
            shiftLocalId = "shift-runtime-001",
        )

        assertEquals(18.0, productDao.findByLocalId("mat-gula")!!.stockOnHand, 0.0001)
        assertEquals(9.0, productDao.findByLocalId("mat-susu")!!.stockOnHand, 0.0001)
        assertEquals(4.5, productDao.findByLocalId("mat-kopi")!!.stockOnHand, 0.0001)

        val recipeMovements = syncDao.actions.filter {
            it.actionType == SyncActionType.RECORD_STOCK_MOVEMENT &&
                it.payloadJson.contains("\"reason\":\"sale_recipe_input\"")
        }
        assertEquals(3, recipeMovements.size)
        assertTrue(recipeMovements.any { it.payloadJson.contains("\"product_local_id\":\"mat-gula\"") })
        assertTrue(recipeMovements.any { it.payloadJson.contains("\"product_local_id\":\"mat-susu\"") })
        assertTrue(recipeMovements.any { it.payloadJson.contains("\"product_local_id\":\"mat-kopi\"") })
    }

    @Test
    fun `PARITY-FIFO-001 sale issue follows oldest layer order`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct().copy(stockOnHand = 5.0),
                defaultMaterialProduct(),
            ),
        )
        val layerDao = FakeInventoryLayerDao(
            initialLayers = listOf(
                InventoryLayerEntity(
                    localId = "layer-oldest",
                    productLocalId = "prd-1",
                    qtyOriginal = 2.0,
                    qtyAvailable = 2.0,
                    unitCost = 10000.0,
                    sourceType = "receive",
                    sourceRef = "po-1",
                    receivedAt = 1L,
                    updatedAtLocal = 1L,
                ),
                InventoryLayerEntity(
                    localId = "layer-newer",
                    productLocalId = "prd-1",
                    qtyOriginal = 3.0,
                    qtyAvailable = 3.0,
                    unitCost = 12000.0,
                    sourceType = "receive",
                    sourceRef = "po-2",
                    receivedAt = 2L,
                    updatedAtLocal = 2L,
                ),
            ),
        )
        val allocationDao = FakeSaleFifoAllocationDao()
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(
            customerDao = FakeCustomerDao(),
            productDao = productDao,
            recipeDao = FakeRecipeDao(),
            saleDao = FakeSaleDao(),
            syncActionDao = syncDao,
            inventoryLayerDao = layerDao,
            saleFifoAllocationDao = allocationDao,
        )

        repository.finalizeSaleOffline(
            receiptNo = "TRX-FIFO-001",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 4.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 40000,
            shiftLocalId = "shift-fifo-001",
        )

        val allocations = allocationDao.rows.sortedBy { it.allocationOrder }
        assertEquals(2, allocations.size)
        assertEquals("layer-oldest", allocations[0].inventoryLayerLocalId)
        assertEquals(2.0, allocations[0].qtyIssued, 0.0001)
        assertEquals("layer-newer", allocations[1].inventoryLayerLocalId)
        assertEquals(2.0, allocations[1].qtyIssued, 0.0001)

        val newestLayer = layerDao.findByLocalId("layer-newer")
        assertNotNull(newestLayer)
        assertEquals(1.0, newestLayer!!.qtyAvailable, 0.0001)
    }

    @Test
    fun `PARITY-FIFO-002 void reversal restores previously consumed layers`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct().copy(stockOnHand = 5.0),
                defaultMaterialProduct(),
            ),
        )
        val layerDao = FakeInventoryLayerDao(
            initialLayers = listOf(
                InventoryLayerEntity(
                    localId = "layer-void-1",
                    productLocalId = "prd-1",
                    qtyOriginal = 5.0,
                    qtyAvailable = 5.0,
                    unitCost = 9000.0,
                    sourceType = "receive",
                    sourceRef = "po-void-1",
                    receivedAt = 1L,
                    updatedAtLocal = 1L,
                ),
            ),
        )
        val allocationDao = FakeSaleFifoAllocationDao()
        val repository = PosRepositoryImpl(
            customerDao = FakeCustomerDao(),
            productDao = productDao,
            recipeDao = FakeRecipeDao(),
            saleDao = FakeSaleDao(),
            syncActionDao = FakeSyncActionDao(),
            inventoryLayerDao = layerDao,
            saleFifoAllocationDao = allocationDao,
        )

        val saleId = repository.finalizeSaleOffline(
            receiptNo = "TRX-FIFO-002",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 3.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 30000,
            shiftLocalId = "shift-fifo-002",
        )

        val beforeReverseLayer = layerDao.findByLocalId("layer-void-1")
        assertNotNull(beforeReverseLayer)
        assertEquals(2.0, beforeReverseLayer!!.qtyAvailable, 0.0001)

        val reversalPayload = repository.reverseSaleFifoAllocationsOffline(
            saleLocalId = saleId,
            reversalRef = "VOID-TRX-FIFO-002",
            now = 10L,
        )

        val afterReverseLayer = layerDao.findByLocalId("layer-void-1")
        assertNotNull(afterReverseLayer)
        assertEquals(5.0, afterReverseLayer!!.qtyAvailable, 0.0001)
        assertTrue(reversalPayload.contains("\"layer_local_id\":\"layer-void-1\""))
        assertTrue(allocationDao.rows.all { it.reversedAt != null })
    }

    @Test
    fun `PARITY-FIFO-003 sale spanning three layers keeps strict fifo order and payload split`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct().copy(stockOnHand = 7.0),
                defaultMaterialProduct(),
            ),
        )
        val layerDao = FakeInventoryLayerDao(
            initialLayers = listOf(
                InventoryLayerEntity(
                    localId = "layer-a",
                    productLocalId = "prd-1",
                    qtyOriginal = 1.0,
                    qtyAvailable = 1.0,
                    unitCost = 9000.0,
                    sourceType = "receive",
                    sourceRef = "po-a",
                    receivedAt = 1L,
                    updatedAtLocal = 1L,
                ),
                InventoryLayerEntity(
                    localId = "layer-b",
                    productLocalId = "prd-1",
                    qtyOriginal = 2.0,
                    qtyAvailable = 2.0,
                    unitCost = 10000.0,
                    sourceType = "receive",
                    sourceRef = "po-b",
                    receivedAt = 2L,
                    updatedAtLocal = 2L,
                ),
                InventoryLayerEntity(
                    localId = "layer-c",
                    productLocalId = "prd-1",
                    qtyOriginal = 4.0,
                    qtyAvailable = 4.0,
                    unitCost = 12000.0,
                    sourceType = "receive",
                    sourceRef = "po-c",
                    receivedAt = 3L,
                    updatedAtLocal = 3L,
                ),
            ),
        )
        val allocationDao = FakeSaleFifoAllocationDao()
        val syncDao = FakeSyncActionDao()
        val repository = PosRepositoryImpl(
            customerDao = FakeCustomerDao(),
            productDao = productDao,
            recipeDao = FakeRecipeDao(),
            saleDao = FakeSaleDao(),
            syncActionDao = syncDao,
            inventoryLayerDao = layerDao,
            saleFifoAllocationDao = allocationDao,
        )

        repository.finalizeSaleOffline(
            receiptNo = "TRX-FIFO-003",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 6.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 60000,
            shiftLocalId = "shift-fifo-003",
        )

        val allocations = allocationDao.rows.sortedBy { it.allocationOrder }
        assertEquals(3, allocations.size)
        assertEquals("layer-a", allocations[0].inventoryLayerLocalId)
        assertEquals(1.0, allocations[0].qtyIssued, 0.0001)
        assertEquals("layer-b", allocations[1].inventoryLayerLocalId)
        assertEquals(2.0, allocations[1].qtyIssued, 0.0001)
        assertEquals("layer-c", allocations[2].inventoryLayerLocalId)
        assertEquals(3.0, allocations[2].qtyIssued, 0.0001)

        assertEquals(0.0, layerDao.findByLocalId("layer-a")!!.qtyAvailable, 0.0001)
        assertEquals(0.0, layerDao.findByLocalId("layer-b")!!.qtyAvailable, 0.0001)
        assertEquals(1.0, layerDao.findByLocalId("layer-c")!!.qtyAvailable, 0.0001)

        val finalizePayload = syncDao.actions.first { it.actionType == SyncActionType.FINALIZE_SALE }.payloadJson
        assertTrue(finalizePayload.contains("\"fifo_allocations\""))
        assertTrue(finalizePayload.contains("\"layer_local_id\":\"layer-a\""))
        assertTrue(finalizePayload.contains("\"layer_local_id\":\"layer-b\""))
        assertTrue(finalizePayload.contains("\"layer_local_id\":\"layer-c\""))
    }

    @Test
    fun `PARITY-FIFO-004 replaying void reversal does not double-restore layer quantities`() = runTest {
        val productDao = FakeProductDao(
            initialProducts = listOf(
                defaultFinishedProduct().copy(stockOnHand = 4.0),
                defaultMaterialProduct(),
            ),
        )
        val layerDao = FakeInventoryLayerDao(
            initialLayers = listOf(
                InventoryLayerEntity(
                    localId = "layer-replay",
                    productLocalId = "prd-1",
                    qtyOriginal = 4.0,
                    qtyAvailable = 4.0,
                    unitCost = 10000.0,
                    sourceType = "receive",
                    sourceRef = "po-replay",
                    receivedAt = 1L,
                    updatedAtLocal = 1L,
                ),
            ),
        )
        val allocationDao = FakeSaleFifoAllocationDao()
        val repository = PosRepositoryImpl(
            customerDao = FakeCustomerDao(),
            productDao = productDao,
            recipeDao = FakeRecipeDao(),
            saleDao = FakeSaleDao(),
            syncActionDao = FakeSyncActionDao(),
            inventoryLayerDao = layerDao,
            saleFifoAllocationDao = allocationDao,
        )

        val saleId = repository.finalizeSaleOffline(
            receiptNo = "TRX-FIFO-004",
            customerLocalId = null,
            items = listOf(CheckoutItem(productLocalId = "prd-1", qty = 2.0, unitPrice = 10000)),
            discountAmount = 0,
            paymentMethod = "cash",
            paidAmount = 20000,
            shiftLocalId = "shift-fifo-004",
        )

        val first = repository.reverseSaleFifoAllocationsOffline(
            saleLocalId = saleId,
            reversalRef = "VOID-REPLAY-1",
            now = 50L,
        )
        val afterFirst = layerDao.findByLocalId("layer-replay")
        assertNotNull(afterFirst)
        assertEquals(4.0, afterFirst!!.qtyAvailable, 0.0001)
        assertTrue(first.contains("\"layer_local_id\":\"layer-replay\""))

        val second = repository.reverseSaleFifoAllocationsOffline(
            saleLocalId = saleId,
            reversalRef = "VOID-REPLAY-2",
            now = 60L,
        )
        val afterSecond = layerDao.findByLocalId("layer-replay")
        assertNotNull(afterSecond)
        assertEquals(4.0, afterSecond!!.qtyAvailable, 0.0001)
        assertEquals("[]", second)
    }

    private fun defaultFinishedProduct(): ProductEntity {
        return ProductEntity(
            localId = "prd-1",
            serverId = null,
            name = "Produk Test",
            categoryName = "Demo",
            itemType = "finished_good",
            productMode = "standard",
            barcode = null,
            price = 10000,
            stockOnHand = 10.0,
            syncStatus = SyncStatus.SYNCED,
            version = 1,
            updatedAtLocal = 1L,
            updatedAtServer = 1L,
        )
    }

    private fun defaultMaterialProduct(): ProductEntity {
        return ProductEntity(
            localId = "mat-1",
            serverId = null,
            name = "Bahan Test",
            categoryName = "Bahan",
            itemType = "raw_material",
            productMode = "standard",
            barcode = null,
            price = 1000,
            stockOnHand = 10.0,
            syncStatus = SyncStatus.SYNCED,
            version = 1,
            updatedAtLocal = 1L,
            updatedAtServer = 1L,
        )
    }

    private suspend fun assertAutoConsumeError(block: suspend () -> Unit): AutoConsumeParityException {
        return try {
            block()
            throw AssertionError("Expected AutoConsumeParityException to be thrown")
        } catch (error: AutoConsumeParityException) {
            error
        }
    }

    private class FakeProductDao(
        initialProducts: List<ProductEntity> = listOf(
            ProductEntity(
                localId = "prd-1",
                serverId = null,
                name = "Produk Test",
                categoryName = "Demo",
                itemType = "finished_good",
                productMode = "standard",
                barcode = null,
                price = 10000,
                stockOnHand = 10.0,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            ),
            ProductEntity(
                localId = "mat-1",
                serverId = null,
                name = "Bahan Test",
                categoryName = "Bahan",
                itemType = "raw_material",
                productMode = "standard",
                barcode = null,
                price = 1000,
                stockOnHand = 10.0,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            ),
        ),
    ) : ProductDao {
        private val products = MutableStateFlow(initialProducts)

        override fun observeProducts(): Flow<List<ProductEntity>> = products

        override fun observeProductsByKeyword(keyword: String): Flow<List<ProductEntity>> {
            val filtered = products.value.filter { it.name.contains(keyword, ignoreCase = true) }
            return flowOf(filtered)
        }

        override suspend fun upsert(product: ProductEntity) {
            products.value = products.value.filterNot { it.localId == product.localId } + product
        }

        override suspend fun upsertAll(products: List<ProductEntity>) {
            this.products.value = products
        }

        override suspend fun findByLocalId(localId: String): ProductEntity? {
            return products.value.firstOrNull { it.localId == localId }
        }

        override suspend fun countProducts(): Long = products.value.size.toLong()
    }

    private class FakeRecipeDao(
        items: List<RecipeItemEntity> = emptyList(),
    ) : RecipeDao {
        private val rows = MutableStateFlow(items)

        override fun observeAll(): Flow<List<RecipeItemEntity>> = rows

        override suspend fun findByProductLocalId(productLocalId: String): List<RecipeItemEntity> {
            return rows.value.filter { it.productLocalId == productLocalId }
        }

        override suspend fun deleteByProductLocalId(productLocalId: String) {
            rows.value = rows.value.filterNot { it.productLocalId == productLocalId }
        }

        override suspend fun upsertAll(items: List<RecipeItemEntity>) {
            val next = rows.value.filterNot { current ->
                items.any { incoming -> incoming.localId == current.localId }
            } + items
            rows.value = next
        }
    }

    private class FakeInventoryLayerDao(
        initialLayers: List<InventoryLayerEntity> = emptyList(),
    ) : InventoryLayerDao {
        private val layers = mutableListOf<InventoryLayerEntity>().apply { addAll(initialLayers) }

        override suspend fun upsert(layer: InventoryLayerEntity) {
            val index = layers.indexOfFirst { it.localId == layer.localId }
            if (index >= 0) {
                layers[index] = layer
            } else {
                layers += layer
            }
        }

        override suspend fun upsertAll(layers: List<InventoryLayerEntity>) {
            layers.forEach { upsert(it) }
        }

        override suspend fun findAvailableLayersByProduct(productLocalId: String): List<InventoryLayerEntity> {
            return layers
                .filter { it.productLocalId == productLocalId && it.qtyAvailable > 0.0 }
                .sortedWith(compareBy<InventoryLayerEntity> { it.receivedAt }.thenBy { it.localId })
        }

        override suspend fun findByLocalId(layerLocalId: String): InventoryLayerEntity? {
            return layers.firstOrNull { it.localId == layerLocalId }
        }
    }

    private class FakeSaleFifoAllocationDao : SaleFifoAllocationDao {
        val rows = mutableListOf<SaleFifoAllocationEntity>()

        override suspend fun insertAll(allocations: List<SaleFifoAllocationEntity>) {
            rows += allocations
        }

        override suspend fun findActiveBySaleLocalId(saleLocalId: String): List<SaleFifoAllocationEntity> {
            return rows.filter { it.saleLocalId == saleLocalId && it.reversedAt == null }
                .sortedWith(compareBy<SaleFifoAllocationEntity> { it.allocationOrder }.thenBy { it.createdAt })
        }

        override suspend fun markReversed(allocationLocalId: String, reversedAt: Long, reversalRef: String) {
            val index = rows.indexOfFirst { it.localId == allocationLocalId }
            if (index >= 0) {
                rows[index] = rows[index].copy(
                    reversedAt = reversedAt,
                    reversalRef = reversalRef,
                )
            }
        }
    }

    private class FakeCustomerDao : CustomerDao {
        override fun observeCustomers(): Flow<List<CustomerEntity>> = flowOf(
            listOf(
                CustomerEntity(
                    localId = "cust-1",
                    serverId = null,
                    name = "Pelanggan Test",
                    phone = null,
                    address = null,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )

        override suspend fun upsertAll(customers: List<CustomerEntity>) = Unit

        override suspend fun countCustomers(): Long = 1L
    }

    private class FakeSaleDao : SaleDao {
        var lastSale: SaleEntity? = null
        var lastItems: List<SaleItemEntity> = emptyList()
        var lastPayment: PaymentEntity? = null

        override suspend fun upsertSale(sale: SaleEntity) {
            lastSale = sale
        }

        override suspend fun insertSaleItems(items: List<SaleItemEntity>) {
            lastItems = items
        }

        override suspend fun upsertPayment(payment: PaymentEntity) {
            lastPayment = payment
        }

        override suspend fun countSales(): Long = if (lastSale == null) 0 else 1

        override fun observeRecentSales(limit: Int): Flow<List<SaleEntity>> {
            return flowOf(listOfNotNull(lastSale))
        }

        override fun observeRecentSaleItems(limit: Int): Flow<List<SaleItemEntity>> {
            return flowOf(lastItems)
        }

        override fun observeRecentSaleItemsWithProductName(limit: Int): Flow<List<SaleItemWithProductNameRow>> {
            val rows = lastItems.map { item ->
                SaleItemWithProductNameRow(
                    saleLocalId = item.saleLocalId,
                    productLocalId = item.productLocalId,
                    qty = item.qty,
                    lineTotal = item.lineTotal,
                    productName = null,
                )
            }
            return flowOf(rows)
        }

        override fun observeRecentPayments(limit: Int): Flow<List<PaymentEntity>> {
            return flowOf(listOfNotNull(lastPayment))
        }

        override suspend fun getSaleByLocalId(saleLocalId: String): SaleEntity? {
            return lastSale?.takeIf { it.localId == saleLocalId }
        }

        override suspend fun getLatestSaleByReceiptNo(receiptNo: String): SaleEntity? {
            return lastSale?.takeIf { it.receiptNo == receiptNo }
        }

        override suspend fun sumTotalAmountBetween(openedAt: Long, closedAt: Long): Long {
            val sale = lastSale ?: return 0L
            return if (sale.finalizedAt in openedAt..closedAt) sale.totalAmount else 0L
        }

        override suspend fun insertFinalizedSale(
            sale: SaleEntity,
            items: List<SaleItemEntity>,
            payment: PaymentEntity,
        ) {
            lastSale = sale
            lastItems = items
            lastPayment = payment
        }
    }

    private class FakeSyncActionDao : SyncActionDao {
        val actions = mutableListOf<SyncActionEntity>()

        override suspend fun enqueue(action: SyncActionEntity) {
            actions.add(action)
        }

        override suspend fun findDueActions(now: Long, limit: Int): List<SyncActionEntity> {
            return actions.filter { it.nextRetryTime <= now }.take(limit)
        }

        override suspend fun claimForProcessing(actionId: String, lockedUntil: Long): Int {
            return if (actions.any { it.actionId == actionId }) 1 else 0
        }

        override suspend fun delete(actionId: String) {
            actions.removeAll { it.actionId == actionId }
        }

        override suspend fun markFailed(actionId: String, nextRetryTime: Long, errorMessage: String) {
            val index = actions.indexOfFirst { it.actionId == actionId }
            if (index >= 0) {
                val current = actions[index]
                actions[index] = current.copy(
                    retryCount = current.retryCount + 1,
                    nextRetryTime = nextRetryTime,
                    actionStatus = OutboxStatus.FAILED,
                    lastError = errorMessage,
                )
            }
        }

        override suspend fun countByIdempotencyKey(idempotencyKey: String): Int {
            return actions.count { it.idempotencyKey == idempotencyKey }
        }

        override fun observePendingCount(): Flow<Int> = flowOf(actions.count { it.actionStatus != OutboxStatus.FAILED })

        override fun observeFailedCount(): Flow<Int> = flowOf(actions.count { it.actionStatus == OutboxStatus.FAILED })

        override fun observeActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> {
            return flowOf(emptyList())
        }

        override fun observeFailedActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> {
            return flowOf(emptyList())
        }
    }
}
