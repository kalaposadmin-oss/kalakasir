package com.umkm.offline.app.purchase

import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.SupplierDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.SyncActionTypeCountRow
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.PurchaseReturnItemEntity
import com.umkm.offline.core.database.entity.SupplierEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.runBlocking
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Assert.fail
import org.junit.Test

class PurchaseRepositoryTest {

    companion object {
        private const val RETURN_PURCHASE_SERVER_ID = "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
        private const val RETURN_SUPPLIER_LOCAL_ID = "sup-1"
        private const val RETURN_SUPPLIER_SERVER_ID = "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"
        private const val RETURN_PRODUCT_1_SERVER_ID = "11111111-1111-1111-1111-111111111111"
        private const val RETURN_PRODUCT_2_SERVER_ID = "22222222-2222-2222-2222-222222222222"
    }


    @Test
    fun createSupplierOffline_storesSupplierAndOutboxAction() = runBlocking {
        val supplierDao = FakeSupplierDao()
        val purchaseDao = FakePurchaseDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(supplierDao, purchaseDao, syncActionDao)

        val supplierId = repository.createSupplierOffline(
            name = "Supplier Demo",
            phone = "0812000111",
            address = "Jl. Test",
            notes = null,
        )

        assertEquals(supplierId, supplierDao.lastSupplier?.localId)
        assertEquals("Supplier Demo", supplierDao.lastSupplier?.name)
        assertEquals(SyncStatus.PENDING_SYNC, supplierDao.lastSupplier?.syncStatus)
        assertEquals(1, syncActionDao.actions.size)
        assertEquals(EntityType.SUPPLIER, syncActionDao.actions.first().entityType)
        assertEquals(SyncActionType.UPSERT_SUPPLIER, syncActionDao.actions.first().actionType)
    }

    @Test
    fun finalizePurchaseOffline_storesPurchaseAndOutboxAction() = runBlocking {
        val supplierDao = FakeSupplierDao()
        val purchaseDao = FakePurchaseDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(supplierDao, purchaseDao, syncActionDao)

        val purchaseId = repository.finalizePurchaseOffline(
            purchaseNo = "PO-TEST-001",
            supplierLocalId = "sup-1",
            items = listOf(
                PurchaseItemDraft(productLocalId = "prod-1", qty = 2.0, unitCost = 10_000),
                PurchaseItemDraft(productLocalId = "prod-2", qty = 1.0, unitCost = 5_000),
            ),
            discountAmount = 1_000,
            dueDate = null,
        )

        assertEquals(purchaseId, purchaseDao.lastPurchase?.localId)
        assertEquals(25_000L, purchaseDao.lastPurchase?.subtotal)
        assertEquals(1_000L, purchaseDao.lastPurchase?.discountAmount)
        assertEquals(24_000L, purchaseDao.lastPurchase?.totalAmount)
        assertEquals(2, purchaseDao.lastItems.size)

        assertEquals(1, syncActionDao.actions.size)
        assertEquals(EntityType.PURCHASE, syncActionDao.actions.first().entityType)
        assertEquals(SyncActionType.FINALIZE_PURCHASE, syncActionDao.actions.first().actionType)
        assertTrue(syncActionDao.actions.first().payloadJson.contains("PO-TEST-001"))
    }

    @Test
    fun finalizePurchaseOffline_rejectsNonPositiveQty() = runBlocking {
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), FakeSyncActionDao())

        try {
            repository.finalizePurchaseOffline(
                purchaseNo = "PO-ERR-001",
                supplierLocalId = null,
                items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 0.0, unitCost = 10_000)),
                discountAmount = 0,
                dueDate = null,
            )
            fail("Expected IllegalArgumentException for qty <= 0")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("Qty"))
        }
    }

    @Test
    fun finalizePurchaseOffline_rejectsNegativeUnitCost() = runBlocking {
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), FakeSyncActionDao())

        try {
            repository.finalizePurchaseOffline(
                purchaseNo = "PO-ERR-002",
                supplierLocalId = null,
                items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = -1)),
                discountAmount = 0,
                dueDate = null,
            )
            fail("Expected IllegalArgumentException for negative unit cost")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("Harga modal"))
        }
    }

    @Test
    fun finalizePurchaseOffline_rejectsBlankProductId() = runBlocking {
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), FakeSyncActionDao())

        try {
            repository.finalizePurchaseOffline(
                purchaseNo = "PO-ERR-003",
                supplierLocalId = null,
                items = listOf(PurchaseItemDraft(productLocalId = "   ", qty = 1.0, unitCost = 10_000)),
                discountAmount = 0,
                dueDate = null,
            )
            fail("Expected IllegalArgumentException for blank product id")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("Produk"))
        }
    }

    @Test
    fun finalizePurchaseOffline_rejectsBlankPurchaseNumber() = runBlocking {
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), FakeSyncActionDao())

        try {
            repository.finalizePurchaseOffline(
                purchaseNo = "   ",
                supplierLocalId = null,
                items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
                discountAmount = 0,
                dueDate = null,
            )
            fail("Expected IllegalArgumentException for blank purchase number")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("Nomor pembelian"))
        }
    }

    @Test
    fun finalizePurchaseOffline_clampsDiscountToSubtotal() = runBlocking {
        val supplierDao = FakeSupplierDao()
        val purchaseDao = FakePurchaseDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(supplierDao, purchaseDao, syncActionDao)

        repository.finalizePurchaseOffline(
            purchaseNo = "PO-DISC-001",
            supplierLocalId = null,
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            discountAmount = 10_000,
            dueDate = null,
        )

        assertEquals(5_000L, purchaseDao.lastPurchase?.subtotal)
        assertEquals(5_000L, purchaseDao.lastPurchase?.discountAmount)
        assertEquals(0L, purchaseDao.lastPurchase?.totalAmount)
    }

    @Test
    fun finalizePurchaseOffline_requiresDueDateWhenPaymentIsTempoOrUnpaid() = runBlocking {
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), FakeSyncActionDao())

        try {
            repository.finalizePurchaseOffline(
                purchaseNo = "PO-DUE-001",
                supplierLocalId = null,
                items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
                discountAmount = 0,
                dueDate = null,
                paymentMethod = "tempo",
                paymentStatus = "unpaid",
            )
            fail("Expected IllegalArgumentException when due date is missing for tempo/unpaid")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("Jatuh tempo"))
        }
    }

    @Test
    fun finalizePurchaseOffline_normalizesTempoPaidToReceivedUnpaid() = runBlocking {
        val purchaseDao = FakePurchaseDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(FakeSupplierDao(), purchaseDao, syncActionDao)

        repository.finalizePurchaseOffline(
            purchaseNo = "PO-PAY-001",
            supplierLocalId = null,
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            discountAmount = 0,
            dueDate = System.currentTimeMillis() + 86_400_000L,
            paymentMethod = "tempo",
            paymentStatus = "paid",
        )

        assertEquals("received_unpaid", purchaseDao.lastPurchase?.status)
        val payload = syncActionDao.actions.first().payloadJson
        assertTrue(payload.contains("\"payment_method\":\"tempo\""))
        assertTrue(payload.contains("\"payment_status\":\"unpaid\""))
    }

    @Test
    fun finalizePurchaseOffline_defaultsCashSourceToKasShiftForCashPayment() = runBlocking {
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), syncActionDao)

        repository.finalizePurchaseOffline(
            purchaseNo = "PO-CASH-001",
            supplierLocalId = null,
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            discountAmount = 0,
            dueDate = null,
            paymentMethod = "cash",
            paymentStatus = "paid",
            cashSource = "   ",
        )

        val payload = syncActionDao.actions.first().payloadJson
        assertTrue(payload.contains("\"cash_source\":\"kas_shift\""))
    }

    @Test
    fun finalizePurchaseOffline_includesOptionalNoteInOutboxPayload() = runBlocking {
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao(), syncActionDao)

        repository.finalizePurchaseOffline(
            purchaseNo = "PO-NOTE-001",
            supplierLocalId = null,
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            discountAmount = 0,
            dueDate = null,
            paymentMethod = "cash",
            paymentStatus = "paid",
            note = "Butuh kirim pagi",
        )

        val payload = syncActionDao.actions.first().payloadJson
        assertTrue(payload.contains("\"note\":\"Butuh kirim pagi\""))
    }

    @Test
    fun finalizePurchaseOffline_rejectsRecipeOrFinishedGoodItemAtRepositoryLayer() = runBlocking {
        val syncActionDao = FakeSyncActionDao()
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-fg-1",
                    serverId = null,
                    name = "Nasi Uduk",
                    categoryName = "Makanan",
                    itemType = "finished_good",
                    productMode = "bom",
                    barcode = null,
                    price = 25_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(),
            purchaseDao = FakePurchaseDao(),
            syncActionDao = syncActionDao,
            productDao = productDao,
        )

        try {
            repository.finalizePurchaseOffline(
                purchaseNo = "PO-REJECT-001",
                supplierLocalId = null,
                items = listOf(PurchaseItemDraft(productLocalId = "prod-fg-1", qty = 1.0, unitCost = 10_000)),
                discountAmount = 0,
                dueDate = null,
            )
            fail("Expected IllegalArgumentException for recipe/finished_good product")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("tidak bisa dibeli sebagai stok"))
        }

        assertTrue(syncActionDao.actions.isEmpty())
    }

    @Test
    fun markPurchaseReceivedOffline_updatesStatusAndEnqueuesOutbox() = runBlocking {
        val supplierDao = FakeSupplierDao()
        val purchaseDao = FakePurchaseDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(supplierDao, purchaseDao, syncActionDao)

        purchaseDao.lastPurchase = PurchaseEntity(
            localId = "purchase-1",
            serverId = null,
            purchaseNo = "PO-OPEN-001",
            supplierLocalId = null,
            subtotal = 10_000,
            discountAmount = 0,
            totalAmount = 10_000,
            status = "open",
            purchasedAt = 1L,
            dueDate = null,
            syncStatus = SyncStatus.SYNCED,
            version = 1,
            updatedAtLocal = 1L,
            updatedAtServer = 1L,
        )

        val updated = repository.markPurchaseReceivedOffline("purchase-1", receivedAt = 2L)

        assertTrue(updated)
        assertEquals("received", purchaseDao.lastPurchase?.status)
        assertEquals(SyncStatus.PENDING_SYNC, purchaseDao.lastPurchase?.syncStatus)
        assertEquals(1, syncActionDao.actions.size)
        assertEquals(SyncActionType.RECORD_STOCK_MOVEMENT, syncActionDao.actions.first().actionType)
        val payload = syncActionDao.actions.first().payloadJson
        assertTrue(payload.contains("\"transaction_date\""))
        assertTrue(!payload.contains("\"purchase_local_id\""))
        assertTrue(!payload.contains("\"status\""))
    }

    @Test
    fun markPurchaseReceivedOffline_failFastWhenReceiveItemProductMissingBackendUuid() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = null,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = null,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "open",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
        }
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-1",
                    serverId = null,
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 10_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = productDao,
        )

        try {
            repository.markPurchaseReceivedOffline(
                purchaseLocalId = "purchase-1",
                receivedAt = 2L,
                receiveItems = listOf(PurchaseReceiveItemDraft(productLocalId = "prod-1", qty = 1.0)),
            )
            fail("Expected IllegalArgumentException for missing backend UUID on receive items")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("backend UUID"))
        }

        assertEquals("open", purchaseDao.lastPurchase?.status)
        assertTrue(syncActionDao.actions.isEmpty())
    }

    @Test
    fun markPurchaseReceivedOffline_allowsEmptyBodyWhenNoDateAndNoItems() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = null,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = null,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "open",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(FakeSupplierDao(), purchaseDao, syncActionDao)

        val updated = repository.markPurchaseReceivedOffline(
            purchaseLocalId = "purchase-1",
            receivedAt = 2L,
            payloadTransactionDate = null,
            receiveItems = null,
        )

        assertTrue(updated)
        val payload = syncActionDao.actions.single().payloadJson
        assertEquals("{}", payload)
        assertTrue(!payload.contains("\"purchase_local_id\""))
        assertTrue(!payload.contains("\"status\""))
    }

    @Test
    fun markPurchaseReceivedOffline_allowsTransactionDateOnlyPayload() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = null,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = null,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "open",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(FakeSupplierDao(), purchaseDao, syncActionDao)

        val updated = repository.markPurchaseReceivedOffline(
            purchaseLocalId = "purchase-1",
            receivedAt = 2L,
            payloadTransactionDate = 2L,
        )

        assertTrue(updated)
        val payload = syncActionDao.actions.single().payloadJson
        assertTrue(payload.contains("\"transaction_date\""))
        assertTrue(!payload.contains("\"items\""))
        assertTrue(!payload.contains("\"purchase_local_id\""))
        assertTrue(!payload.contains("\"status\""))
    }

    @Test
    fun markPurchaseReceivedOffline_allowsCanonicalItemsPayloadWithBackendUuid() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = null,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = null,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "open",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
        }
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-1",
                    serverId = "11111111-1111-1111-1111-111111111111",
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 10_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = productDao,
        )

        val updated = repository.markPurchaseReceivedOffline(
            purchaseLocalId = "purchase-1",
            receivedAt = 2L,
            payloadTransactionDate = 2L,
            receiveItems = listOf(PurchaseReceiveItemDraft(productLocalId = "prod-1", qty = 2.5)),
        )

        assertTrue(updated)
        val payload = syncActionDao.actions.single().payloadJson
        assertTrue(payload.contains("\"transaction_date\""))
        assertTrue(payload.contains("\"items\""))
        assertTrue(payload.contains("\"product_id\":\"11111111-1111-1111-1111-111111111111\""))
        assertTrue(payload.contains("\"qty\":2.500000"))
        assertTrue(!payload.contains("\"purchase_local_id\""))
        assertTrue(!payload.contains("\"status\""))
    }

    @Test
    fun markPurchaseReceivedOffline_returnsFalseWhenAlreadyReceived() = runBlocking {
        val repository = PurchaseRepository(FakeSupplierDao(), FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = null,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = null,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = null,
            )
        }, FakeSyncActionDao())

        val updated = repository.markPurchaseReceivedOffline("purchase-1", receivedAt = 2L)
        assertTrue(!updated)
    }

    @Test
    fun recordPurchaseReturnOffline_updatesPurchaseAndEnqueuesOutbox() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 25_000,
                discountAmount = 0,
                totalAmount = 25_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 2.0,
                    unitCost = 10_000,
                    lineTotal = 20_000,
                ),
                PurchaseItemEntity(
                    localId = "item-2",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-2",
                    qty = 1.0,
                    unitCost = 5_000,
                    lineTotal = 5_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(
                suppliers = listOf(returnSupplier()),
            ),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(
                    returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID),
                    returnProduct(localId = "prod-2", serverId = RETURN_PRODUCT_2_SERVER_ID),
                ),
            ),
        )

        val saved = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Barang rusak",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            returnedAt = 2L,
        )

        assertTrue(saved)
        assertEquals("returned_partial", purchaseDao.lastPurchase?.status)
        assertEquals(15_000L, purchaseDao.lastPurchase?.totalAmount)
        assertEquals(1, syncActionDao.actions.size)
        assertEquals(SyncActionType.RECORD_PURCHASE_RETURN, syncActionDao.actions.first().actionType)
        assertTrue(syncActionDao.actions.first().payloadJson.contains("Barang rusak"))
    }

    @Test
    fun recordPurchaseReturnOffline_rejectsProductOutsidePurchase() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = FakeSyncActionDao(),
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        try {
            repository.recordPurchaseReturnOffline(
                purchaseLocalId = "purchase-1",
                reason = "Tidak sesuai",
                items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-x", qty = 1.0, unitCost = 10_000)),
            )
            fail("Expected IllegalArgumentException for non-existing product in purchase")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("tidak ada di purchase awal"))
        }
    }

    @Test
    fun recordPurchaseReturnOffline_rejectsWhenCumulativeQtyExceedsPurchasedQty() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "returned_partial",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = 2,
                updatedAtLocal = 2L,
                updatedAtServer = null,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 2.0,
                    unitCost = 5_000,
                    lineTotal = 10_000,
                ),
            )
            returnItems = mutableListOf(
                PurchaseReturnItemEntity(
                    localId = "ret-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.5,
                    unitCost = 5_000,
                    reason = "Retur awal",
                    returnedAt = 2L,
                ),
            )
        }
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = FakeSyncActionDao(),
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        try {
            repository.recordPurchaseReturnOffline(
                purchaseLocalId = "purchase-1",
                reason = "Retur lanjutan",
                items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            )
            fail("Expected IllegalArgumentException for cumulative return overflow")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("melebihi qty pembelian"))
        }
    }

    @Test
    fun recordPurchaseReturnOffline_marksReturnedFullAfterStagedMultiProductReturns() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 25_000,
                discountAmount = 0,
                totalAmount = 25_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 2.0,
                    unitCost = 10_000,
                    lineTotal = 20_000,
                ),
                PurchaseItemEntity(
                    localId = "item-2",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-2",
                    qty = 1.0,
                    unitCost = 5_000,
                    lineTotal = 5_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(
                    returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID),
                    returnProduct(localId = "prod-2", serverId = RETURN_PRODUCT_2_SERVER_ID),
                ),
            ),
        )

        val first = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Retur bertahap",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            returnedAt = 2L,
        )
        val second = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Retur final",
            items = listOf(
                PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000),
                PurchaseReturnItemDraft(productLocalId = "prod-2", qty = 1.0, unitCost = 5_000),
            ),
            returnedAt = 3L,
        )

        assertTrue(first)
        assertTrue(second)
        assertEquals("returned_full", purchaseDao.lastPurchase?.status)
        assertEquals(0L, purchaseDao.lastPurchase?.totalAmount)
        assertEquals(2, syncActionDao.actions.size)
    }

    @Test
    fun recordPurchaseReturnOffline_acceptsDecimalCumulativeWithoutFloatingPointFalsePositive() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 300,
                discountAmount = 0,
                totalAmount = 300,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 0.3,
                    unitCost = 1_000,
                    lineTotal = 300,
                ),
            )
        }
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = FakeSyncActionDao(),
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        val first = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Retur pecahan 1",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 0.1, unitCost = 1_000)),
            returnedAt = 2L,
        )
        val second = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Retur pecahan 2",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 0.2, unitCost = 1_000)),
            returnedAt = 3L,
        )

        assertTrue(first)
        assertTrue(second)
        assertEquals("returned_full", purchaseDao.lastPurchase?.status)
        assertEquals(0L, purchaseDao.lastPurchase?.totalAmount)
    }

    @Test
    fun recordPurchaseReturnOffline_isIdempotentForExactRetryPayload() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 2.0,
                    unitCost = 5_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        val first = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Retry-safe",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            returnedAt = 9L,
        )
        val second = repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Retry-safe",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            returnedAt = 9L,
        )

        assertTrue(first)
        assertTrue(second)
        assertEquals(5_000L, purchaseDao.lastPurchase?.totalAmount)
        assertEquals(1, purchaseDao.returnItems.size)
        assertEquals(1, syncActionDao.actions.size)
    }

    @Test
    fun purchaseFlow_finalize_receive_andStagedReturn_reachesReturnedFull() = runBlocking {
        val supplierDao = FakeSupplierDao()
        val purchaseDao = FakePurchaseDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = supplierDao,
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(
                    returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID),
                    returnProduct(localId = "prod-2", serverId = RETURN_PRODUCT_2_SERVER_ID),
                ),
            ),
        )

        val supplierId = repository.createSupplierOffline(
            name = "PT Supplier A",
            phone = "08123",
            address = "Jl. Mawar",
            notes = null,
        )
        val purchaseId = repository.finalizePurchaseOffline(
            purchaseNo = "PO-INT-001",
            supplierLocalId = supplierId,
            items = listOf(
                PurchaseItemDraft(productLocalId = "prod-1", qty = 2.0, unitCost = 10_000),
                PurchaseItemDraft(productLocalId = "prod-2", qty = 1.0, unitCost = 5_000),
            ),
            discountAmount = 0,
            dueDate = null,
        )

        supplierDao.upsert(
            returnSupplier(localId = supplierId, serverId = RETURN_SUPPLIER_SERVER_ID),
        )
        purchaseDao.lastPurchase = requireNotNull(purchaseDao.lastPurchase).copy(
            status = "open",
            serverId = RETURN_PURCHASE_SERVER_ID,
            supplierLocalId = supplierId,
        )
        val received = repository.markPurchaseReceivedOffline(purchaseId, receivedAt = 10L)
        val firstReturn = repository.recordPurchaseReturnOffline(
            purchaseLocalId = purchaseId,
            reason = "Retur 1",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            returnedAt = 11L,
        )
        val secondReturn = repository.recordPurchaseReturnOffline(
            purchaseLocalId = purchaseId,
            reason = "Retur 2",
            items = listOf(
                PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000),
                PurchaseReturnItemDraft(productLocalId = "prod-2", qty = 1.0, unitCost = 5_000),
            ),
            returnedAt = 12L,
        )

        assertTrue(received)
        assertTrue(firstReturn)
        assertTrue(secondReturn)
        assertEquals("returned_full", purchaseDao.lastPurchase?.status)
        assertEquals(0L, purchaseDao.lastPurchase?.totalAmount)
        assertEquals(
            listOf(
                SyncActionType.UPSERT_SUPPLIER,
                SyncActionType.FINALIZE_PURCHASE,
                SyncActionType.RECORD_STOCK_MOVEMENT,
                SyncActionType.RECORD_PURCHASE_RETURN,
                SyncActionType.RECORD_PURCHASE_RETURN,
            ),
            syncActionDao.actions.map { it.actionType },
        )
    }

    @Test
    fun recordPurchaseReturnOffline_enqueuesStablePayloadContract() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Payload kontrak",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            returnedAt = 123L,
        )

        val payload = syncActionDao.actions.last().payloadJson
        assertEquals(
            "{\"purchase_id\":\"aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa\",\"supplier_id\":\"bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb\",\"transaction_date\":\"1970-01-01T00:00:00.123Z\",\"items\":[{\"product_id\":\"11111111-1111-1111-1111-111111111111\",\"qty\":1.000000,\"price\":10000,\"reason\":\"Payload kontrak\"}]}",
            payload,
        )
        assertTrue(!payload.contains("\"status\""))
        assertTrue(!payload.contains("\"purchase_local_id\""))
        assertTrue(!payload.contains("\"product_local_id\""))
        assertTrue(!payload.contains("\"unit_cost\""))
        assertTrue(!payload.contains("\"source_module\""))
    }

    @Test
    fun recordPurchaseReturnOffline_failFastWhenPurchaseBackendUuidMissing() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = null,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        try {
            repository.recordPurchaseReturnOffline(
                purchaseLocalId = "purchase-1",
                reason = "Purchase UUID belum ada",
                items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
                returnedAt = 123L,
            )
            fail("Expected IllegalArgumentException for missing purchase backend UUID")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("backend UUID"))
        }

        assertEquals("received", purchaseDao.lastPurchase?.status)
        assertTrue(syncActionDao.actions.isEmpty())
    }

    @Test
    fun recordPurchaseReturnOffline_treatsSamePayloadWithinWindowAsDuplicate() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 2.0,
                    unitCost = 5_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Window duplicate",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            returnedAt = 1_000L,
        )
        repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Window duplicate",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 5_000)),
            returnedAt = 1_100L,
        )

        assertEquals(1, purchaseDao.returnItems.size)
        assertEquals(1, syncActionDao.actions.size)
    }

    @Test
    fun recordPurchaseReturnOffline_emitsObservabilityMetrics() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val metrics = mutableListOf<String>()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = FakeSyncActionDao(),
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
            onMetric = { name, _ -> metrics.add(name) },
        )

        repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Metric test",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            returnedAt = 500L,
        )
        repository.recordPurchaseReturnOffline(
            purchaseLocalId = "purchase-1",
            reason = "Metric test",
            items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            returnedAt = 600L,
        )

        assertTrue(metrics.contains("return_created"))
        assertTrue(metrics.contains("return_deduplicated"))
    }

    @Test
    fun recordPurchaseReturnOffline_failFastWhenSupplierBackendUuidMissing() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(
                suppliers = listOf(returnSupplier(serverId = null)),
            ),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = RETURN_PRODUCT_1_SERVER_ID)),
            ),
        )

        try {
            repository.recordPurchaseReturnOffline(
                purchaseLocalId = "purchase-1",
                reason = "Supplier UUID belum ada",
                items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
                returnedAt = 123L,
            )
            fail("Expected IllegalArgumentException for missing supplier backend UUID")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("backend UUID"))
        }

        assertEquals("received", purchaseDao.lastPurchase?.status)
        assertTrue(syncActionDao.actions.isEmpty())
    }

    @Test
    fun recordPurchaseReturnOffline_failFastWhenItemProductBackendUuidMissing() = runBlocking {
        val purchaseDao = FakePurchaseDao().apply {
            lastPurchase = PurchaseEntity(
                localId = "purchase-1",
                serverId = RETURN_PURCHASE_SERVER_ID,
                purchaseNo = "PO-OPEN-001",
                supplierLocalId = RETURN_SUPPLIER_LOCAL_ID,
                subtotal = 10_000,
                discountAmount = 0,
                totalAmount = 10_000,
                status = "received",
                purchasedAt = 1L,
                dueDate = null,
                syncStatus = SyncStatus.SYNCED,
                version = 1,
                updatedAtLocal = 1L,
                updatedAtServer = 1L,
            )
            lastItems = listOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    lineTotal = 10_000,
                ),
            )
        }
        val syncActionDao = FakeSyncActionDao()
        val repository = PurchaseRepository(
            supplierDao = FakeSupplierDao(suppliers = listOf(returnSupplier())),
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = FakeProductDao(
                products = listOf(returnProduct(localId = "prod-1", serverId = null)),
            ),
        )

        try {
            repository.recordPurchaseReturnOffline(
                purchaseLocalId = "purchase-1",
                reason = "Produk UUID belum ada",
                items = listOf(PurchaseReturnItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
                returnedAt = 123L,
            )
            fail("Expected IllegalArgumentException for missing product backend UUID")
        } catch (error: IllegalArgumentException) {
            assertTrue(error.message.orEmpty().contains("backend UUID"))
        }

        assertEquals("received", purchaseDao.lastPurchase?.status)
        assertTrue(syncActionDao.actions.isEmpty())
    }

    private fun returnSupplier(
        localId: String = RETURN_SUPPLIER_LOCAL_ID,
        serverId: String? = RETURN_SUPPLIER_SERVER_ID,
    ): SupplierEntity {
        return SupplierEntity(
            localId = localId,
            serverId = serverId,
            name = "Supplier Return",
            phone = null,
            address = null,
            notes = null,
            syncStatus = SyncStatus.SYNCED,
            version = 1,
            updatedAtLocal = 1L,
            updatedAtServer = 1L,
        )
    }

    private fun returnProduct(localId: String, serverId: String?): ProductEntity {
        return ProductEntity(
            localId = localId,
            serverId = serverId,
            name = "Product-$localId",
            categoryName = "Bahan",
            itemType = "raw_material",
            productMode = "add_on",
            barcode = null,
            price = 10_000,
            stockOnHand = 10.0,
            syncStatus = SyncStatus.SYNCED,
            version = 1,
            updatedAtLocal = 1L,
            updatedAtServer = 1L,
        )
    }

    private class FakeSupplierDao(
        suppliers: List<SupplierEntity> = emptyList(),
    ) : SupplierDao {
        private val suppliersFlow = MutableStateFlow<List<SupplierEntity>>(suppliers)
        var lastSupplier: SupplierEntity? = null

        override fun observeSuppliers(): Flow<List<SupplierEntity>> = suppliersFlow

        override suspend fun upsert(supplier: SupplierEntity) {
            lastSupplier = supplier
            suppliersFlow.value = suppliersFlow.value.filterNot { it.localId == supplier.localId } + supplier
        }

        override suspend fun upsertAll(suppliers: List<SupplierEntity>) {
            suppliers.forEach { upsert(it) }
        }

        override suspend fun findByLocalId(localId: String): SupplierEntity? {
            return suppliersFlow.value.firstOrNull { it.localId == localId }
        }

        override suspend fun countSuppliers(): Long = suppliersFlow.value.size.toLong()
    }

    private class FakePurchaseDao : PurchaseDao {
        var lastPurchase: PurchaseEntity? = null
        var lastItems: List<PurchaseItemEntity> = emptyList()
        var returnItems: MutableList<PurchaseReturnItemEntity> = mutableListOf()

        override suspend fun upsertPurchase(purchase: PurchaseEntity) {
            lastPurchase = purchase
        }

        override suspend fun insertPurchaseItems(items: List<PurchaseItemEntity>) {
            lastItems = items
        }

        override suspend fun insertFinalizedPurchase(purchase: PurchaseEntity, items: List<PurchaseItemEntity>) {
            lastPurchase = purchase
            lastItems = items
        }

        override suspend fun countPurchases(): Long = if (lastPurchase == null) 0 else 1

        override suspend fun findPurchaseByLocalId(purchaseLocalId: String): PurchaseEntity? {
            return lastPurchase?.takeIf { it.localId == purchaseLocalId }
        }

        override fun observeRecentPurchases(limit: Int): Flow<List<PurchaseEntity>> {
            return flowOf(listOfNotNull(lastPurchase))
        }

        override fun observeItemsByPurchase(purchaseLocalId: String): Flow<List<PurchaseItemEntity>> {
            return flowOf(lastItems.filter { it.purchaseLocalId == purchaseLocalId })
        }

        override suspend fun findPurchaseItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseItemEntity> {
            return lastItems.filter { it.purchaseLocalId == purchaseLocalId }
        }

        override suspend fun insertPurchaseReturnItems(items: List<PurchaseReturnItemEntity>) {
            returnItems.addAll(items)
        }

        override suspend fun findPurchaseReturnItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseReturnItemEntity> {
            return returnItems.filter { it.purchaseLocalId == purchaseLocalId }
        }
    }

    private class FakeProductDao(
        products: List<ProductEntity> = emptyList(),
    ) : ProductDao {
        private val productsFlow = MutableStateFlow(products)

        override fun observeProducts(): Flow<List<ProductEntity>> = productsFlow

        override fun observeProductsByKeyword(keyword: String): Flow<List<ProductEntity>> {
            val normalized = keyword.trim().lowercase()
            return flowOf(productsFlow.value.filter { it.name.lowercase().contains(normalized) })
        }

        override suspend fun upsert(product: ProductEntity) {
            productsFlow.value = productsFlow.value.filterNot { it.localId == product.localId } + product
        }

        override suspend fun upsertAll(products: List<ProductEntity>) {
            products.forEach { upsert(it) }
        }

        override suspend fun findByLocalId(localId: String): ProductEntity? {
            return productsFlow.value.firstOrNull { it.localId == localId }
        }

        override suspend fun countProducts(): Long = productsFlow.value.size.toLong()
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

        override fun observeActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> = flowOf(emptyList())

        override fun observeFailedActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> = flowOf(emptyList())
    }
}
