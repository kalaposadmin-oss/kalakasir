package com.umkm.offline.core.sync

import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.StockMovementDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.SyncActionTypeCountRow
import com.umkm.offline.core.database.dao.SyncCursorDao
import com.umkm.offline.core.database.dao.SyncEffectDao
import com.umkm.offline.core.database.dao.InventoryLayerDao
import com.umkm.offline.core.database.dao.AuditEventDao
import com.umkm.offline.core.database.entity.AuditEventEntity
import com.umkm.offline.core.database.entity.InventoryLayerEntity
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.PurchaseReturnItemEntity
import com.umkm.offline.core.database.entity.StockMovementEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import com.umkm.offline.core.database.entity.SyncCursorEntity
import com.umkm.offline.core.database.entity.SyncEffectEntity
import com.umkm.offline.core.network.SyncApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class SyncEngineTest {

    @Test
    fun pushOutbox_retryThenSuccess_flowWorks() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val api = FlakySyncApi(failCount = 1)
        val engine = SyncEngine(actionDao, cursorDao, api)

        val action = SyncActionEntity(
            actionId = "act-1",
            entityType = EntityType.SALE,
            entityId = "sale-1",
            actionType = SyncActionType.FINALIZE_SALE,
            payloadJson = "{\"sale\":\"1\"}",
            idempotencyKey = "idem-1",
            retryCount = 0,
            nextRetryTime = 1000L,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = 1000L,
        )
        actionDao.enqueue(action)

        val firstAttempt = engine.pushOutbox(now = 1000L)
        assertEquals(0, firstAttempt)
        assertEquals(1, actionDao.actions.size)
        assertEquals(OutboxStatus.FAILED, actionDao.actions.first().actionStatus)
        assertTrue((actionDao.actions.first().lastError ?: "").isNotBlank())

        val retryAction = actionDao.actions.first()
        actionDao.actions[0] = retryAction.copy(nextRetryTime = 1000L)
        val secondAttempt = engine.pushOutbox(now = 1000L)
        assertEquals(1, secondAttempt)
        assertTrue(actionDao.actions.isEmpty())
    }

    @Test
    fun pullServerChanges_storesNextCursor() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val api = object : SyncApi {
            override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
                return emptyMap()
            }

            override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf("next_cursor" to "cursor-2", "changes" to emptyList<Map<String, Any?>>())
            }
        }

        cursorDao.upsert(SyncCursorEntity("default", "cursor-1", 1L))
        val engine = SyncEngine(actionDao, cursorDao, api)

        val response = engine.pullServerChanges()

        assertEquals("cursor-2", response["next_cursor"])
        assertEquals("cursor-2", cursorDao.findByKey("default")?.cursorToken)
    }

    private class FlakySyncApi(
        private var failCount: Int,
    ) : SyncApi {
        override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
            if (failCount > 0) {
                failCount -= 1
                throw IllegalStateException("temporary failure")
            }
            return emptyMap()
        }

        override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
            return mapOf("next_cursor" to null)
        }
    }

    @Test
    fun pushOutbox_receiveAckProjection_updatesOnceAndSkipsReplay() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val purchaseDao = FakePurchaseDao(
            purchaseItems = mutableListOf(
                PurchaseItemEntity(
                    localId = "item-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-local-1",
                    qty = 5.0,
                    unitCost = 10_000,
                    lineTotal = 50_000,
                ),
            ),
        )
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-local-1",
                    serverId = "prod-server-1",
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 20_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val effectDao = FakeSyncEffectDao()
        val stockMovementDao = FakeStockMovementDao()
        val inventoryLayerDao = FakeInventoryLayerDao()
        val auditEventDao = FakeAuditEventDao()
        val api = object : SyncApi {
            override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf(
                    "items" to listOf(
                        mapOf(
                            "product_id" to "prod-server-1",
                            "qty" to 2.0,
                            "unit_cost" to 10_000.0,
                        ),
                    ),
                    "journal_entries" to listOf(
                        mapOf("journal_id" to "jrnl-rx-1", "debit" to 20_000.0, "credit" to 20_000.0),
                    ),
                )
            }

            override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf("next_cursor" to null)
            }
        }

        val engine = SyncEngine(
            syncActionDao = actionDao,
            syncCursorDao = cursorDao,
            syncApi = api,
            purchaseDao = purchaseDao,
            productDao = productDao,
            stockMovementDao = stockMovementDao,
            inventoryLayerDao = inventoryLayerDao,
            auditEventDao = auditEventDao,
            syncEffectDao = effectDao,
        )

        val action = SyncActionEntity(
            actionId = "act-rx-1",
            entityType = EntityType.PURCHASE,
            entityId = "purchase-1",
            actionType = SyncActionType.RECORD_STOCK_MOVEMENT,
            payloadJson = "{\"items\":[{\"product_id\":\"prod-server-1\",\"qty\":2.0}]}",
            idempotencyKey = "idem-rx-1",
            retryCount = 0,
            nextRetryTime = 1000L,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = 1000L,
        )

        actionDao.enqueue(action)
        assertEquals(1, engine.pushOutbox(now = 1000L))
        val first = purchaseDao.purchaseItems.single()
        val firstProduct = productDao.findByLocalId("prod-local-1")
        val firstMovement = stockMovementDao.findByLocalId("ack:purchase-receive:act-rx-1:prod-local-1")
        val firstLayer = inventoryLayerDao.findByLocalId("ack:purchase-receive-layer:act-rx-1:prod-local-1:10000.000000")
        assertEquals(2.0, first.receivedQty ?: 0.0, 0.000001)
        assertEquals(3.0, first.remainingQty ?: 0.0, 0.000001)
        assertEquals(12.0, firstProduct?.stockOnHand ?: 0.0, 0.000001)
        assertEquals("in", firstMovement?.direction)
        assertEquals(2.0, firstMovement?.qty ?: 0.0, 0.000001)
        assertEquals(2.0, firstLayer?.qtyAvailable ?: 0.0, 0.000001)
        assertEquals(1, auditEventDao.rows.size)
        assertTrue(auditEventDao.rows.first().message.contains("action=act-rx-1"))

        actionDao.enqueue(action)
        assertEquals(1, engine.pushOutbox(now = 1000L))
        val second = purchaseDao.purchaseItems.single()
        val secondProduct = productDao.findByLocalId("prod-local-1")
        val secondMovement = stockMovementDao.findByLocalId("ack:purchase-receive:act-rx-1:prod-local-1")
        assertEquals(2.0, second.receivedQty ?: 0.0, 0.000001)
        assertEquals(3.0, second.remainingQty ?: 0.0, 0.000001)
        assertEquals(12.0, secondProduct?.stockOnHand ?: 0.0, 0.000001)
        assertEquals(1, stockMovementDao.rows.size)
        assertEquals(2.0, secondMovement?.qty ?: 0.0, 0.000001)
        assertEquals(1, inventoryLayerDao.rows.size)
        assertEquals(1, auditEventDao.rows.size)
    }

    @Test
    fun pushOutbox_returnAckStockDecrease_updatesOnceAndSkipsReplay() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val purchaseDao = FakePurchaseDao(
            purchaseItems = mutableListOf(
                PurchaseItemEntity(
                    localId = "item-ret-1",
                    purchaseLocalId = "purchase-1",
                    productLocalId = "prod-local-1",
                    qty = 5.0,
                    unitCost = 10_000,
                    lineTotal = 50_000,
                ),
            ),
        )
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-local-1",
                    serverId = "prod-server-1",
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 20_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val effectDao = FakeSyncEffectDao()
        val stockMovementDao = FakeStockMovementDao()
        val inventoryLayerDao = FakeInventoryLayerDao(
            rows = mutableListOf(
                InventoryLayerEntity(
                    localId = "layer-open-1",
                    productLocalId = "prod-local-1",
                    qtyOriginal = 5.0,
                    qtyAvailable = 5.0,
                    unitCost = 10_000.0,
                    sourceType = "seed",
                    sourceRef = "seed-ref",
                    receivedAt = 1L,
                    updatedAtLocal = 1L,
                ),
            ),
        )
        val auditEventDao = FakeAuditEventDao()
        val api = object : SyncApi {
            override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf(
                    "items" to listOf(
                        mapOf(
                            "product_id" to "prod-server-1",
                            "qty" to 3.0,
                        ),
                    ),
                    "data" to mapOf(
                        "journals" to listOf(
                            mapOf("id" to "jrnl-ret-1", "debit" to 30_000.0, "credit" to 30_000.0),
                        ),
                    ),
                )
            }

            override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf("next_cursor" to null)
            }
        }

        val engine = SyncEngine(
            syncActionDao = actionDao,
            syncCursorDao = cursorDao,
            syncApi = api,
            purchaseDao = purchaseDao,
            productDao = productDao,
            stockMovementDao = stockMovementDao,
            inventoryLayerDao = inventoryLayerDao,
            auditEventDao = auditEventDao,
            syncEffectDao = effectDao,
        )

        val action = SyncActionEntity(
            actionId = "act-ret-1",
            entityType = EntityType.PURCHASE,
            entityId = "purchase-1",
            actionType = SyncActionType.RECORD_PURCHASE_RETURN,
            payloadJson = "{\"items\":[{\"product_id\":\"prod-server-1\",\"qty\":3.0}]}",
            idempotencyKey = "idem-ret-1",
            retryCount = 0,
            nextRetryTime = 1000L,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = 1000L,
        )

        actionDao.enqueue(action)
        assertEquals(1, engine.pushOutbox(now = 1000L))
        val firstProduct = productDao.findByLocalId("prod-local-1")
        val firstMovement = stockMovementDao.findByLocalId("ack:purchase-return:act-ret-1:prod-local-1")
        val firstLayer = inventoryLayerDao.findByLocalId("layer-open-1")
        assertEquals(7.0, firstProduct?.stockOnHand ?: 0.0, 0.000001)
        assertEquals("out", firstMovement?.direction)
        assertEquals(3.0, firstMovement?.qty ?: 0.0, 0.000001)
        assertEquals(2.0, firstLayer?.qtyAvailable ?: 0.0, 0.000001)
        assertEquals(1, auditEventDao.rows.size)
        assertTrue(auditEventDao.rows.first().message.contains("action=act-ret-1"))

        actionDao.enqueue(action)
        assertEquals(1, engine.pushOutbox(now = 1000L))
        val secondProduct = productDao.findByLocalId("prod-local-1")
        val secondMovement = stockMovementDao.findByLocalId("ack:purchase-return:act-ret-1:prod-local-1")
        assertEquals(7.0, secondProduct?.stockOnHand ?: 0.0, 0.000001)
        assertEquals(1, stockMovementDao.rows.size)
        assertEquals(3.0, secondMovement?.qty ?: 0.0, 0.000001)
        assertEquals(2.0, inventoryLayerDao.findByLocalId("layer-open-1")?.qtyAvailable ?: 0.0, 0.000001)
        assertEquals(1, auditEventDao.rows.size)
    }

    @Test
    fun pushOutbox_returnAckValuationConsumption_isCappedByAppliedStockDecrease() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val purchaseDao = FakePurchaseDao(
            purchaseItems = mutableListOf(
                PurchaseItemEntity(
                    localId = "item-ret-cap-1",
                    purchaseLocalId = "purchase-cap-1",
                    productLocalId = "prod-local-1",
                    qty = 10.0,
                    unitCost = 10_000,
                    lineTotal = 100_000,
                ),
            ),
        )
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-local-1",
                    serverId = "prod-server-1",
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 20_000,
                    stockOnHand = 2.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val effectDao = FakeSyncEffectDao()
        val stockMovementDao = FakeStockMovementDao()
        val inventoryLayerDao = FakeInventoryLayerDao(
            rows = mutableListOf(
                InventoryLayerEntity(
                    localId = "layer-cap-1",
                    productLocalId = "prod-local-1",
                    qtyOriginal = 5.0,
                    qtyAvailable = 5.0,
                    unitCost = 10_000.0,
                    sourceType = "seed",
                    sourceRef = "seed-ref",
                    receivedAt = 1L,
                    updatedAtLocal = 1L,
                ),
            ),
        )

        val api = object : SyncApi {
            override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf(
                    "items" to listOf(
                        mapOf(
                            "product_id" to "prod-server-1",
                            "qty" to 10.0,
                        ),
                    ),
                )
            }

            override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf("next_cursor" to null)
            }
        }

        val engine = SyncEngine(
            syncActionDao = actionDao,
            syncCursorDao = cursorDao,
            syncApi = api,
            purchaseDao = purchaseDao,
            productDao = productDao,
            stockMovementDao = stockMovementDao,
            inventoryLayerDao = inventoryLayerDao,
            syncEffectDao = effectDao,
        )

        val action = SyncActionEntity(
            actionId = "act-ret-cap-1",
            entityType = EntityType.PURCHASE,
            entityId = "purchase-cap-1",
            actionType = SyncActionType.RECORD_PURCHASE_RETURN,
            payloadJson = "{\"items\":[{\"product_id\":\"prod-server-1\",\"qty\":10.0}]}",
            idempotencyKey = "idem-ret-cap-1",
            retryCount = 0,
            nextRetryTime = 1000L,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = 1000L,
        )

        actionDao.enqueue(action)
        assertEquals(1, engine.pushOutbox(now = 1000L))

        val productAfter = productDao.findByLocalId("prod-local-1")
        val layerAfter = inventoryLayerDao.findByLocalId("layer-cap-1")
        val movement = stockMovementDao.findByLocalId("ack:purchase-return:act-ret-cap-1:prod-local-1")

        // Stock decrease is capped by on-hand (2.0), so layer consumption must mirror 2.0 only.
        assertEquals(0.0, productAfter?.stockOnHand ?: 0.0, 0.000001)
        assertEquals(2.0, movement?.qty ?: 0.0, 0.000001)
        assertEquals(3.0, layerAfter?.qtyAvailable ?: 0.0, 0.000001)
    }

    @Test
    fun pushOutbox_receiveAckProjection_stillSucceedsWhenAccountingMirrorFails() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val purchaseDao = FakePurchaseDao(
            purchaseItems = mutableListOf(
                PurchaseItemEntity(
                    localId = "item-safe-1",
                    purchaseLocalId = "purchase-safe-1",
                    productLocalId = "prod-local-1",
                    qty = 5.0,
                    unitCost = 10_000,
                    lineTotal = 50_000,
                ),
            ),
        )
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-local-1",
                    serverId = "prod-server-1",
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 20_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val effectDao = FakeSyncEffectDao()
        val stockMovementDao = FakeStockMovementDao()
        val inventoryLayerDao = FakeInventoryLayerDao()
        val failingAuditDao = ThrowingAuditEventDao()
        val api = object : SyncApi {
            override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf(
                    "items" to listOf(
                        mapOf(
                            "product_id" to "prod-server-1",
                            "qty" to 2.0,
                            "unit_cost" to 10_000.0,
                        ),
                    ),
                    "journal_entries" to listOf(
                        mapOf("journal_id" to "jrnl-safe-1"),
                    ),
                )
            }

            override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf("next_cursor" to null)
            }
        }

        val engine = SyncEngine(
            syncActionDao = actionDao,
            syncCursorDao = cursorDao,
            syncApi = api,
            purchaseDao = purchaseDao,
            productDao = productDao,
            stockMovementDao = stockMovementDao,
            inventoryLayerDao = inventoryLayerDao,
            auditEventDao = failingAuditDao,
            syncEffectDao = effectDao,
        )

        val action = SyncActionEntity(
            actionId = "act-safe-1",
            entityType = EntityType.PURCHASE,
            entityId = "purchase-safe-1",
            actionType = SyncActionType.RECORD_STOCK_MOVEMENT,
            payloadJson = "{\"items\":[{\"product_id\":\"prod-server-1\",\"qty\":2.0}]}",
            idempotencyKey = "idem-safe-1",
            retryCount = 0,
            nextRetryTime = 1000L,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = 1000L,
        )

        actionDao.enqueue(action)
        assertEquals(1, engine.pushOutbox(now = 1000L))
        assertTrue(actionDao.actions.isEmpty())

        val updatedItem = purchaseDao.purchaseItems.single()
        val updatedProduct = productDao.findByLocalId("prod-local-1")
        val movement = stockMovementDao.findByLocalId("ack:purchase-receive:act-safe-1:prod-local-1")
        val layer = inventoryLayerDao.findByLocalId("ack:purchase-receive-layer:act-safe-1:prod-local-1:10000.000000")

        assertEquals(2.0, updatedItem.receivedQty ?: 0.0, 0.000001)
        assertEquals(3.0, updatedItem.remainingQty ?: 0.0, 0.000001)
        assertEquals(12.0, updatedProduct?.stockOnHand ?: 0.0, 0.000001)
        assertEquals("in", movement?.direction)
        assertEquals(2.0, movement?.qty ?: 0.0, 0.000001)
        assertEquals(2.0, layer?.qtyAvailable ?: 0.0, 0.000001)
    }

    @Test
    fun pushOutbox_receiveAckExplicitFailure_doesNotApplyProjectionAndMarksFailed() = runTest {
        val actionDao = FakeSyncActionDao()
        val cursorDao = FakeSyncCursorDao()
        val purchaseDao = FakePurchaseDao(
            purchaseItems = mutableListOf(
                PurchaseItemEntity(
                    localId = "item-fail-1",
                    purchaseLocalId = "purchase-fail-1",
                    productLocalId = "prod-local-1",
                    qty = 5.0,
                    receivedQty = 0.0,
                    remainingQty = 5.0,
                    unitCost = 10_000,
                    lineTotal = 50_000,
                ),
            ),
        )
        val productDao = FakeProductDao(
            products = listOf(
                ProductEntity(
                    localId = "prod-local-1",
                    serverId = "prod-server-1",
                    name = "Bahan A",
                    categoryName = "Bahan",
                    itemType = "raw_material",
                    productMode = "add_on",
                    barcode = null,
                    price = 20_000,
                    stockOnHand = 10.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = 1L,
                ),
            ),
        )
        val effectDao = FakeSyncEffectDao()
        val stockMovementDao = FakeStockMovementDao()
        val inventoryLayerDao = FakeInventoryLayerDao()

        val api = object : SyncApi {
            override suspend fun pushAction(idempotencyKey: String, payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf(
                    "status" to "failed",
                    "success" to false,
                    "items" to listOf(
                        mapOf(
                            "product_id" to "prod-server-1",
                            "qty" to 2.0,
                            "unit_cost" to 10_000.0,
                        ),
                    ),
                )
            }

            override suspend fun pullChanges(payload: Map<String, Any?>): Map<String, Any?> {
                return mapOf("next_cursor" to null)
            }
        }

        val engine = SyncEngine(
            syncActionDao = actionDao,
            syncCursorDao = cursorDao,
            syncApi = api,
            purchaseDao = purchaseDao,
            productDao = productDao,
            stockMovementDao = stockMovementDao,
            inventoryLayerDao = inventoryLayerDao,
            syncEffectDao = effectDao,
        )

        val action = SyncActionEntity(
            actionId = "act-fail-1",
            entityType = EntityType.PURCHASE,
            entityId = "purchase-fail-1",
            actionType = SyncActionType.RECORD_STOCK_MOVEMENT,
            payloadJson = "{\"items\":[{\"product_id\":\"prod-server-1\",\"qty\":2.0}]}",
            idempotencyKey = "idem-fail-1",
            retryCount = 0,
            nextRetryTime = 1000L,
            actionStatus = OutboxStatus.PENDING,
            lockedUntil = null,
            lastError = null,
            createdAt = 1000L,
        )

        actionDao.enqueue(action)
        assertEquals(0, engine.pushOutbox(now = 1000L))

        val updatedItem = purchaseDao.purchaseItems.single()
        val updatedProduct = productDao.findByLocalId("prod-local-1")

        assertEquals(0.0, updatedItem.receivedQty ?: 0.0, 0.000001)
        assertEquals(5.0, updatedItem.remainingQty ?: 0.0, 0.000001)
        assertEquals(10.0, updatedProduct?.stockOnHand ?: 0.0, 0.000001)
        assertEquals(0, stockMovementDao.rows.size)
        assertEquals(0, inventoryLayerDao.rows.size)

        assertEquals(1, actionDao.actions.size)
        assertEquals(OutboxStatus.FAILED, actionDao.actions.first().actionStatus)
        assertEquals(1, actionDao.actions.first().retryCount)
        assertTrue((actionDao.actions.first().lastError ?: "").isNotBlank())
    }

    private class FakeSyncCursorDao : SyncCursorDao {
        private var cursor: SyncCursorEntity? = null

        override suspend fun upsert(cursor: SyncCursorEntity) {
            this.cursor = cursor
        }

        override suspend fun findByKey(key: String): SyncCursorEntity? {
            return cursor?.takeIf { it.key == key }
        }
    }

    private class FakeSyncActionDao : SyncActionDao {
        val actions = mutableListOf<SyncActionEntity>()

        override suspend fun enqueue(action: SyncActionEntity) {
            actions.removeAll { it.actionId == action.actionId }
            actions.add(action)
        }

        override suspend fun findDueActions(now: Long, limit: Int): List<SyncActionEntity> {
            return actions
                .filter { it.nextRetryTime <= now && (it.lockedUntil == null || it.lockedUntil!! <= now) }
                .take(limit)
        }

        override suspend fun claimForProcessing(actionId: String, lockedUntil: Long): Int {
            val index = actions.indexOfFirst { it.actionId == actionId }
            if (index < 0) {
                return 0
            }

            val current = actions[index]
            if (current.actionStatus != OutboxStatus.PENDING && current.actionStatus != OutboxStatus.FAILED) {
                return 0
            }

            actions[index] = current.copy(actionStatus = OutboxStatus.PROCESSING, lockedUntil = lockedUntil)
            return 1
        }

        override suspend fun delete(actionId: String) {
            actions.removeAll { it.actionId == actionId }
        }

        override suspend fun markFailed(actionId: String, nextRetryTime: Long, errorMessage: String) {
            val index = actions.indexOfFirst { it.actionId == actionId }
            if (index < 0) {
                return
            }
            val current = actions[index]
            actions[index] = current.copy(
                actionStatus = OutboxStatus.FAILED,
                retryCount = current.retryCount + 1,
                nextRetryTime = nextRetryTime,
                lastError = errorMessage,
                lockedUntil = null,
            )
        }

        override suspend fun countByIdempotencyKey(idempotencyKey: String): Int {
            return actions.count { it.idempotencyKey == idempotencyKey }
        }

        override fun observePendingCount(): Flow<Int> {
            return flowOf(actions.count { it.actionStatus == OutboxStatus.PENDING || it.actionStatus == OutboxStatus.PROCESSING })
        }

        override fun observeFailedCount(): Flow<Int> {
            return flowOf(actions.count { it.actionStatus == OutboxStatus.FAILED })
        }

        override fun observeActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> {
            return flowOf(emptyList())
        }

        override fun observeFailedActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> {
            return flowOf(emptyList())
        }
    }

    private class FakePurchaseDao(
        val purchaseItems: MutableList<PurchaseItemEntity>,
    ) : PurchaseDao {
        override suspend fun upsertPurchase(purchase: PurchaseEntity) = Unit

        override suspend fun insertPurchaseItems(items: List<PurchaseItemEntity>) {
            items.forEach { incoming ->
                val index = purchaseItems.indexOfFirst { it.localId == incoming.localId }
                if (index >= 0) {
                    purchaseItems[index] = incoming
                } else {
                    purchaseItems.add(incoming)
                }
            }
        }

        override suspend fun insertFinalizedPurchase(purchase: PurchaseEntity, items: List<PurchaseItemEntity>) = Unit

        override suspend fun countPurchases(): Long = 0

        override suspend fun findPurchaseByLocalId(purchaseLocalId: String): PurchaseEntity? = null

        override fun observeRecentPurchases(limit: Int): Flow<List<PurchaseEntity>> = flowOf(emptyList())

        override fun observeItemsByPurchase(purchaseLocalId: String): Flow<List<PurchaseItemEntity>> = flowOf(
            purchaseItems.filter { it.purchaseLocalId == purchaseLocalId },
        )

        override suspend fun findPurchaseItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseItemEntity> {
            return purchaseItems.filter { it.purchaseLocalId == purchaseLocalId }
        }

        override suspend fun insertPurchaseReturnItems(items: List<PurchaseReturnItemEntity>) = Unit

        override suspend fun findPurchaseReturnItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseReturnItemEntity> = emptyList()
    }

    private class FakeProductDao(
        products: List<ProductEntity>,
    ) : ProductDao {
        private val rows = products.toMutableList()

        override fun observeProducts(): Flow<List<ProductEntity>> = flowOf(rows.toList())

        override fun observeProductsByKeyword(keyword: String): Flow<List<ProductEntity>> = flowOf(rows.toList())

        override suspend fun upsert(product: ProductEntity) {
            val index = rows.indexOfFirst { it.localId == product.localId }
            if (index >= 0) {
                rows[index] = product
            } else {
                rows.add(product)
            }
        }

        override suspend fun upsertAll(products: List<ProductEntity>) {
            products.forEach { upsert(it) }
        }

        override suspend fun findByLocalId(localId: String): ProductEntity? {
            return rows.firstOrNull { it.localId == localId }
        }

        override suspend fun countProducts(): Long = rows.size.toLong()
    }

    private class FakeSyncEffectDao : SyncEffectDao {
        private val keys = mutableSetOf<String>()

        override suspend fun insertIgnore(effect: SyncEffectEntity): Long {
            val added = keys.add(effect.effectKey)
            return if (added) 1L else -1L
        }

        override suspend fun deleteByKey(effectKey: String) {
            keys.remove(effectKey)
        }
    }

    private class FakeStockMovementDao : StockMovementDao {
        val rows = mutableListOf<StockMovementEntity>()

        override suspend fun upsertAll(movements: List<StockMovementEntity>) {
            movements.forEach { movement ->
                val index = rows.indexOfFirst { it.localId == movement.localId }
                if (index >= 0) {
                    rows[index] = movement
                } else {
                    rows.add(movement)
                }
            }
        }

        override suspend fun findByLocalId(localId: String): StockMovementEntity? {
            return rows.firstOrNull { it.localId == localId }
        }
    }

    private class FakeInventoryLayerDao(
        val rows: MutableList<InventoryLayerEntity> = mutableListOf(),
    ) : InventoryLayerDao {
        override suspend fun upsert(layer: InventoryLayerEntity) {
            val index = rows.indexOfFirst { it.localId == layer.localId }
            if (index >= 0) {
                rows[index] = layer
            } else {
                rows.add(layer)
            }
        }

        override suspend fun upsertAll(layers: List<InventoryLayerEntity>) {
            layers.forEach { upsert(it) }
        }

        override suspend fun findAvailableLayersByProduct(productLocalId: String): List<InventoryLayerEntity> {
            return rows
                .filter { it.productLocalId == productLocalId && it.qtyAvailable > 0.0 }
                .sortedWith(compareBy<InventoryLayerEntity> { it.receivedAt }.thenBy { it.localId })
        }

        override suspend fun findByLocalId(layerLocalId: String): InventoryLayerEntity? {
            return rows.firstOrNull { it.localId == layerLocalId }
        }
    }

    private class FakeAuditEventDao : AuditEventDao {
        val rows = mutableListOf<AuditEventEntity>()

        override suspend fun insert(event: AuditEventEntity) {
            rows.add(event)
        }

        override fun observeRecent(limit: Int): Flow<List<AuditEventEntity>> {
            return flowOf(rows.takeLast(limit).reversed())
        }
    }

    private class ThrowingAuditEventDao : AuditEventDao {
        override suspend fun insert(event: AuditEventEntity) {
            throw IllegalStateException("audit insert failed")
        }

        override fun observeRecent(limit: Int): Flow<List<AuditEventEntity>> {
            return flowOf(emptyList())
        }
    }
}
