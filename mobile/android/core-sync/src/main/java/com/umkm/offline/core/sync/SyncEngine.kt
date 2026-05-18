package com.umkm.offline.core.sync

import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.AuditEventDao
import com.umkm.offline.core.database.dao.InventoryLayerDao
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.StockMovementDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.SyncCursorDao
import com.umkm.offline.core.database.dao.SyncEffectDao
import com.umkm.offline.core.database.entity.AuditEventEntity
import com.umkm.offline.core.database.entity.InventoryLayerEntity
import com.umkm.offline.core.database.entity.StockMovementEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import com.umkm.offline.core.database.entity.SyncCursorEntity
import com.umkm.offline.core.database.entity.SyncEffectEntity
import com.umkm.offline.core.network.SyncApi
import org.json.JSONArray
import org.json.JSONObject

class SyncEngine(
    private val syncActionDao: SyncActionDao,
    private val syncCursorDao: SyncCursorDao,
    private val syncApi: SyncApi,
    private val purchaseDao: PurchaseDao? = null,
    private val productDao: ProductDao? = null,
    private val stockMovementDao: StockMovementDao? = null,
    private val inventoryLayerDao: InventoryLayerDao? = null,
    private val auditEventDao: AuditEventDao? = null,
    private val syncEffectDao: SyncEffectDao? = null,
) {
    suspend fun pushOutbox(now: Long, batchSize: Int = 50): Int {
        val actions = syncActionDao.findDueActions(now = now, limit = batchSize)
        var successCount = 0

        actions.forEach { action ->
            val claimed = syncActionDao.claimForProcessing(
                actionId = action.actionId,
                lockedUntil = now + PROCESSING_LOCK_MS,
            )
            if (claimed == 0) {
                return@forEach
            }

            runCatching {
                val ackPayload = syncApi.pushAction(
                    idempotencyKey = action.idempotencyKey,
                    payload = mapOf(
                        "action_id" to action.actionId,
                        "entity_type" to action.entityType.name,
                        "entity_id" to action.entityId,
                        "action_type" to action.actionType.name,
                        "payload" to decodePayloadJson(action.payloadJson),
                        "payload_raw" to action.payloadJson,
                    ),
                )
                if (!isAckAccepted(ackPayload)) {
                    throw IllegalStateException("ACK_REJECTED_BY_SERVER")
                }
                ackPayload
            }.onSuccess { ackPayload ->
                applyReceiveProjectionOnAck(
                    action = action,
                    ackPayload = ackPayload,
                )
                applyReturnStockProjectionOnAck(
                    action = action,
                    ackPayload = ackPayload,
                )
                syncActionDao.delete(action.actionId)
                successCount += 1
            }.onFailure {
                val nextRetry = now + backoffDelay(action.retryCount)
                syncActionDao.markFailed(
                    actionId = action.actionId,
                    nextRetryTime = nextRetry,
                    errorMessage = it.message ?: "UNKNOWN_SYNC_ERROR",
                )
            }
        }

        return successCount
    }

    suspend fun pullServerChanges(): Map<String, Any?> {
        val cursor = syncCursorDao.findByKey(SYNC_CURSOR_KEY)?.cursorToken
        val response = syncApi.pullChanges(mapOf("cursor" to cursor))

        val nextCursor = response["next_cursor"] as? String
        if (!nextCursor.isNullOrBlank()) {
            syncCursorDao.upsert(
                SyncCursorEntity(
                    key = SYNC_CURSOR_KEY,
                    cursorToken = nextCursor,
                    updatedAt = System.currentTimeMillis(),
                )
            )
        }

        return response
    }

    private fun backoffDelay(retryCount: Int): Long {
        val step = retryCount.coerceAtMost(6)
        return 15_000L * (1 shl step)
    }

    private suspend fun applyReceiveProjectionOnAck(
        action: SyncActionEntity,
        ackPayload: Map<String, Any?>,
    ) {
        if (action.actionType != SyncActionType.RECORD_STOCK_MOVEMENT) {
            return
        }
        if (action.entityType != EntityType.PURCHASE) {
            return
        }

        val purchaseData = purchaseDao ?: return
        val products = productDao ?: return
        val effects = syncEffectDao ?: return

        val effectKey = "purchase-receive-ack:${action.actionId}"
        val inserted = effects.insertIgnore(
            SyncEffectEntity(
                effectKey = effectKey,
                createdAt = System.currentTimeMillis(),
            ),
        )
        if (inserted == -1L) {
            return
        }

        try {
            val confirmedItems = extractConfirmedReceiveItems(
                ackPayload = ackPayload,
                outboundPayload = decodePayloadJson(action.payloadJson),
            )
            if (confirmedItems.isEmpty()) {
                return
            }

            val purchaseItems = purchaseData.findPurchaseItemsByPurchaseLocalId(action.entityId)
            if (purchaseItems.isEmpty()) {
                return
            }

            val receivedByProductServerId = linkedMapOf<String, Double>()
            confirmedItems.forEach { item ->
                val current = receivedByProductServerId[item.productId] ?: 0.0
                receivedByProductServerId[item.productId] = current + item.qty
            }

            val stockDeltaByProductLocalId = linkedMapOf<String, Double>()

            val updatedItems = purchaseItems.mapNotNull { item ->
                val productServerId = products.findByLocalId(item.productLocalId)?.serverId?.trim().orEmpty()
                if (productServerId.isBlank()) {
                    return@mapNotNull null
                }
                val receiveDelta = receivedByProductServerId[productServerId] ?: return@mapNotNull null

                val currentReceived = item.receivedQty ?: 0.0
                val nextReceived = (currentReceived + receiveDelta).coerceAtMost(item.qty)
                val nextRemaining = (item.qty - nextReceived).coerceAtLeast(0.0)
                val acceptedDelta = (nextReceived - currentReceived).coerceAtLeast(0.0)

                if (acceptedDelta > 0.0) {
                    val currentDelta = stockDeltaByProductLocalId[item.productLocalId] ?: 0.0
                    stockDeltaByProductLocalId[item.productLocalId] = currentDelta + acceptedDelta
                }

                item.copy(
                    receivedQty = nextReceived,
                    remainingQty = nextRemaining,
                )
            }

            if (updatedItems.isNotEmpty()) {
                purchaseData.insertPurchaseItems(updatedItems)
            }

            if (stockDeltaByProductLocalId.isNotEmpty()) {
                val appliedAt = System.currentTimeMillis()
                val updatedProducts = stockDeltaByProductLocalId.mapNotNull { (productLocalId, delta) ->
                    if (delta <= 0.0) {
                        return@mapNotNull null
                    }
                    val product = products.findByLocalId(productLocalId) ?: return@mapNotNull null
                    product.copy(stockOnHand = (product.stockOnHand + delta).coerceAtLeast(0.0))
                }
                if (updatedProducts.isNotEmpty()) {
                    products.upsertAll(updatedProducts)
                }

                val ledgerRows = updatedProducts.mapNotNull { product ->
                    val delta = stockDeltaByProductLocalId[product.localId] ?: return@mapNotNull null
                    if (delta <= 0.0) {
                        return@mapNotNull null
                    }
                    StockMovementEntity(
                        localId = "ack:purchase-receive:${action.actionId}:${product.localId}",
                        serverId = null,
                        productLocalId = product.localId,
                        direction = "in",
                        qty = delta,
                        reason = "purchase_receive_ack",
                        movedAt = appliedAt,
                        syncStatus = SyncStatus.SYNCED,
                        version = 1,
                        updatedAtLocal = appliedAt,
                        updatedAtServer = null,
                    )
                }
                if (ledgerRows.isNotEmpty()) {
                    stockMovementDao?.upsertAll(ledgerRows)
                }

                // PR6: valuation mirror on ACK only when backend confirms cost per item.
                val valuationAckItems = extractConfirmedReceiveItemsFromAckOnly(ackPayload)
                if (valuationAckItems.isNotEmpty()) {
                    val productLocalIdByServerId = linkedMapOf<String, String>()
                    purchaseItems.forEach { item ->
                        val productServerId = products.findByLocalId(item.productLocalId)?.serverId?.trim().orEmpty()
                        if (productServerId.isNotBlank()) {
                            productLocalIdByServerId[productServerId] = item.productLocalId
                        }
                    }

                    val groupedLayers = linkedMapOf<String, Triple<String, Double, Double>>()
                    valuationAckItems.forEach { item ->
                        val unitCost = item.unitCost ?: return@forEach
                        if (unitCost <= 0.0) {
                            return@forEach
                        }
                        val productLocalId = productLocalIdByServerId[item.productId] ?: return@forEach
                        val key = "$productLocalId#${costToken(unitCost)}"
                        val existing = groupedLayers[key]
                        if (existing == null) {
                            groupedLayers[key] = Triple(productLocalId, unitCost, item.qty)
                        } else {
                            groupedLayers[key] = Triple(existing.first, existing.second, existing.third + item.qty)
                        }
                    }

                    val layerRows = groupedLayers.values.map { (productLocalId, unitCost, qty) ->
                        InventoryLayerEntity(
                            localId = "ack:purchase-receive-layer:${action.actionId}:$productLocalId:${costToken(unitCost)}",
                            productLocalId = productLocalId,
                            qtyOriginal = qty,
                            qtyAvailable = qty,
                            unitCost = unitCost,
                            sourceType = "purchase_receive_ack",
                            sourceRef = action.actionId,
                            receivedAt = appliedAt,
                            updatedAtLocal = appliedAt,
                        )
                    }
                    if (layerRows.isNotEmpty()) {
                        inventoryLayerDao?.upsertAll(layerRows)
                    }
                }
            }

            appendAccountingMirrorOnAckSafely(
                action = action,
                ackPayload = ackPayload,
                operation = "purchase_receive",
            )
        } catch (error: Throwable) {
            effects.deleteByKey(effectKey)
            throw error
        }
    }

    private suspend fun applyReturnStockProjectionOnAck(
        action: SyncActionEntity,
        ackPayload: Map<String, Any?>,
    ) {
        if (action.actionType != SyncActionType.RECORD_PURCHASE_RETURN) {
            return
        }
        if (action.entityType != EntityType.PURCHASE) {
            return
        }

        val purchaseData = purchaseDao ?: return
        val products = productDao ?: return
        val effects = syncEffectDao ?: return

        val effectKey = "purchase-return-stock-ack:${action.actionId}"
        val inserted = effects.insertIgnore(
            SyncEffectEntity(
                effectKey = effectKey,
                createdAt = System.currentTimeMillis(),
            ),
        )
        if (inserted == -1L) {
            return
        }

        try {
            val confirmedItems = extractConfirmedReturnItems(
                ackPayload = ackPayload,
            )
            if (confirmedItems.isEmpty()) {
                return
            }

            val purchaseItems = purchaseData.findPurchaseItemsByPurchaseLocalId(action.entityId)
            if (purchaseItems.isEmpty()) {
                return
            }

            val returnQtyByProductServerId = linkedMapOf<String, Double>()
            confirmedItems.forEach { item ->
                val current = returnQtyByProductServerId[item.productId] ?: 0.0
                returnQtyByProductServerId[item.productId] = current + item.qty
            }

            val returnQtyByProductLocalId = linkedMapOf<String, Double>()
            purchaseItems.forEach { item ->
                val productServerId = products.findByLocalId(item.productLocalId)?.serverId?.trim().orEmpty()
                if (productServerId.isBlank()) {
                    return@forEach
                }
                val returnQty = returnQtyByProductServerId[productServerId] ?: return@forEach
                if (returnQty <= 0.0) {
                    return@forEach
                }
                val current = returnQtyByProductLocalId[item.productLocalId] ?: 0.0
                returnQtyByProductLocalId[item.productLocalId] = current + returnQty
            }

            val appliedDecreaseByProductLocalId = linkedMapOf<String, Double>()
            val updatedProducts = returnQtyByProductLocalId.mapNotNull { (productLocalId, returnQty) ->
                if (returnQty <= 0.0) {
                    return@mapNotNull null
                }
                val product = products.findByLocalId(productLocalId) ?: return@mapNotNull null
                val appliedDecrease = returnQty.coerceAtMost(product.stockOnHand).coerceAtLeast(0.0)
                if (appliedDecrease <= 0.0) {
                    return@mapNotNull null
                }
                appliedDecreaseByProductLocalId[productLocalId] = appliedDecrease
                product.copy(stockOnHand = (product.stockOnHand - appliedDecrease).coerceAtLeast(0.0))
            }

            if (updatedProducts.isNotEmpty()) {
                val appliedAt = System.currentTimeMillis()
                products.upsertAll(updatedProducts)

                val ledgerRows = updatedProducts.mapNotNull { product ->
                    val appliedDecrease = appliedDecreaseByProductLocalId[product.localId] ?: return@mapNotNull null
                    if (appliedDecrease <= 0.0) {
                        return@mapNotNull null
                    }
                    StockMovementEntity(
                        localId = "ack:purchase-return:${action.actionId}:${product.localId}",
                        serverId = null,
                        productLocalId = product.localId,
                        direction = "out",
                        qty = appliedDecrease,
                        reason = "purchase_return_ack",
                        movedAt = appliedAt,
                        syncStatus = SyncStatus.SYNCED,
                        version = 1,
                        updatedAtLocal = appliedAt,
                        updatedAtServer = null,
                    )
                }
                if (ledgerRows.isNotEmpty()) {
                    stockMovementDao?.upsertAll(ledgerRows)
                }

                // PR6: valuation mirror for return consumes from oldest active layers (FIFO) on ACK only.
                val layerDao = inventoryLayerDao
                if (layerDao != null) {
                    val updatedLayerByLocalId = linkedMapOf<String, InventoryLayerEntity>()
                    for ((productLocalId, appliedQty) in appliedDecreaseByProductLocalId) {
                        var remaining = appliedQty.coerceAtLeast(0.0)
                        if (remaining <= 0.0) {
                            continue
                        }

                        val layers = layerDao.findAvailableLayersByProduct(productLocalId)
                        for (layer in layers) {
                            if (remaining <= 0.0) {
                                break
                            }
                            val consume = remaining.coerceAtMost(layer.qtyAvailable).coerceAtLeast(0.0)
                            if (consume <= 0.0) {
                                continue
                            }
                            remaining -= consume
                            updatedLayerByLocalId[layer.localId] = layer.copy(
                                qtyAvailable = (layer.qtyAvailable - consume).coerceAtLeast(0.0),
                                updatedAtLocal = appliedAt,
                            )
                        }
                    }

                    if (updatedLayerByLocalId.isNotEmpty()) {
                        layerDao.upsertAll(updatedLayerByLocalId.values.toList())
                    }
                }
            }

            appendAccountingMirrorOnAckSafely(
                action = action,
                ackPayload = ackPayload,
                operation = "purchase_return",
            )
        } catch (error: Throwable) {
            effects.deleteByKey(effectKey)
            throw error
        }
    }

    companion object {
        private const val PROCESSING_LOCK_MS = 60_000L
        private const val SYNC_CURSOR_KEY = "default"
    }

    private data class ConfirmedReceiveItem(
        val productId: String,
        val qty: Double,
        val unitCost: Double? = null,
    )

    private data class ConfirmedReturnItem(
        val productId: String,
        val qty: Double,
    )

    private fun decodePayloadJson(payloadJson: String): Any {
        return runCatching {
            val trimmed = payloadJson.trim()
            when {
                trimmed.startsWith("{") -> jsonObjectToMap(JSONObject(trimmed))
                trimmed.startsWith("[") -> jsonArrayToList(JSONArray(trimmed))
                else -> payloadJson
            }
        }.getOrDefault(payloadJson)
    }

    private fun jsonObjectToMap(json: JSONObject): Map<String, Any?> {
        val output = linkedMapOf<String, Any?>()
        val keys = json.keys()
        while (keys.hasNext()) {
            val key = keys.next()
            output[key] = decodeJsonValue(json.get(key))
        }
        return output
    }

    private fun jsonArrayToList(array: JSONArray): List<Any?> {
        return buildList {
            for (i in 0 until array.length()) {
                add(decodeJsonValue(array.get(i)))
            }
        }
    }

    private fun decodeJsonValue(value: Any?): Any? {
        return when (value) {
            null -> null
            JSONObject.NULL -> null
            is JSONObject -> jsonObjectToMap(value)
            is JSONArray -> jsonArrayToList(value)
            else -> value
        }
    }

    private fun extractConfirmedReceiveItems(
        ackPayload: Map<String, Any?>,
        outboundPayload: Any,
    ): List<ConfirmedReceiveItem> {
        val ackItems = listOf(
            ackPayload["items"],
            (ackPayload["data"] as? Map<*, *>)?.get("items"),
            (ackPayload["result"] as? Map<*, *>)?.get("items"),
            (ackPayload["payload"] as? Map<*, *>)?.get("items"),
        ).firstNotNullOfOrNull { decodeConfirmedReceiveItemsFromAny(it) }.orEmpty()

        if (ackItems.isNotEmpty()) {
            return ackItems
        }

        return decodeConfirmedReceiveItemsFromAny(outboundPayload).orEmpty()
    }

    private fun extractConfirmedReturnItems(
        ackPayload: Map<String, Any?>,
    ): List<ConfirmedReturnItem> {
        return listOf(
            ackPayload["items"],
            (ackPayload["data"] as? Map<*, *>)?.get("items"),
            (ackPayload["result"] as? Map<*, *>)?.get("items"),
            (ackPayload["payload"] as? Map<*, *>)?.get("items"),
        ).firstNotNullOfOrNull { decodeConfirmedReturnItemsFromAny(it) }.orEmpty()
    }

    private fun extractConfirmedReceiveItemsFromAckOnly(
        ackPayload: Map<String, Any?>,
    ): List<ConfirmedReceiveItem> {
        return listOf(
            ackPayload["items"],
            (ackPayload["data"] as? Map<*, *>)?.get("items"),
            (ackPayload["result"] as? Map<*, *>)?.get("items"),
            (ackPayload["payload"] as? Map<*, *>)?.get("items"),
        ).firstNotNullOfOrNull { decodeConfirmedReceiveItemsFromAny(it) }.orEmpty()
    }

    private fun decodeConfirmedReceiveItemsFromAny(raw: Any?): List<ConfirmedReceiveItem>? {
        decodeConfirmedReceiveItems(raw)?.takeIf { it.isNotEmpty() }?.let { return it }

        val map = raw as? Map<*, *> ?: return null
        val nestedCandidates = listOf(
            map["items"],
            map["payload"],
            (map["payload"] as? Map<*, *>)?.get("items"),
            map["data"],
            (map["data"] as? Map<*, *>)?.get("items"),
            map["result"],
            (map["result"] as? Map<*, *>)?.get("items"),
        )

        nestedCandidates.forEach { candidate ->
            decodeConfirmedReceiveItems(candidate)?.takeIf { it.isNotEmpty() }?.let { return it }
            if (candidate is String) {
                decodeConfirmedReceiveItemsFromAny(decodePayloadJson(candidate))?.takeIf { it.isNotEmpty() }?.let { return it }
            }
        }

        return null
    }

    private fun decodeConfirmedReturnItemsFromAny(raw: Any?): List<ConfirmedReturnItem>? {
        decodeConfirmedReturnItems(raw)?.takeIf { it.isNotEmpty() }?.let { return it }

        val map = raw as? Map<*, *> ?: return null
        val nestedCandidates = listOf(
            map["items"],
            map["payload"],
            (map["payload"] as? Map<*, *>)?.get("items"),
            map["data"],
            (map["data"] as? Map<*, *>)?.get("items"),
            map["result"],
            (map["result"] as? Map<*, *>)?.get("items"),
        )

        nestedCandidates.forEach { candidate ->
            decodeConfirmedReturnItems(candidate)?.takeIf { it.isNotEmpty() }?.let { return it }
            if (candidate is String) {
                decodeConfirmedReturnItemsFromAny(decodePayloadJson(candidate))?.takeIf { it.isNotEmpty() }?.let { return it }
            }
        }

        return null
    }

    private fun decodeConfirmedReceiveItems(raw: Any?): List<ConfirmedReceiveItem>? {
        val rows = raw as? List<*> ?: return null
        return rows.mapNotNull { row ->
            val item = row as? Map<*, *> ?: return@mapNotNull null
            val productId = (item["product_id"] as? String)?.trim().orEmpty()
            val qty = when (val value = item["qty"]) {
                is Number -> value.toDouble()
                is String -> value.toDoubleOrNull() ?: 0.0
                else -> 0.0
            }
            val unitCost = when (val value = item["unit_cost"] ?: item["cost"] ?: item["price"]) {
                is Number -> value.toDouble()
                is String -> value.toDoubleOrNull()
                else -> null
            }
            if (productId.isBlank() || qty <= 0.0) {
                return@mapNotNull null
            }
            ConfirmedReceiveItem(productId = productId, qty = qty, unitCost = unitCost)
        }
    }

    private fun decodeConfirmedReturnItems(raw: Any?): List<ConfirmedReturnItem>? {
        val rows = raw as? List<*> ?: return null
        return rows.mapNotNull { row ->
            val item = row as? Map<*, *> ?: return@mapNotNull null
            val productId = (item["product_id"] as? String)?.trim().orEmpty()
            val qty = when (val value = item["qty"]) {
                is Number -> value.toDouble()
                is String -> value.toDoubleOrNull() ?: 0.0
                else -> 0.0
            }
            if (productId.isBlank() || qty <= 0.0) {
                return@mapNotNull null
            }
            ConfirmedReturnItem(productId = productId, qty = qty)
        }
    }

    private suspend fun appendAccountingMirrorOnAck(
        action: SyncActionEntity,
        ackPayload: Map<String, Any?>,
        operation: String,
    ) {
        val auditDao = auditEventDao ?: return
        val entries = extractJournalEntries(ackPayload)
        if (entries.isEmpty()) {
            return
        }

        val journalIds = entries
            .mapNotNull { row ->
                when (val value = row["journal_id"] ?: row["id"]) {
                    is String -> value.trim().ifBlank { null }
                    is Number -> value.toString()
                    else -> null
                }
            }
            .distinct()
            .take(3)
            .joinToString("|")

        val message = "op=$operation;action=${action.actionId};entries=${entries.size};journals=$journalIds"
        auditDao.insert(
            AuditEventEntity(
                category = "accounting_mirror",
                message = message,
                createdAt = System.currentTimeMillis(),
            )
        )
    }

    private suspend fun appendAccountingMirrorOnAckSafely(
        action: SyncActionEntity,
        ackPayload: Map<String, Any?>,
        operation: String,
    ) {
        runCatching {
            appendAccountingMirrorOnAck(
                action = action,
                ackPayload = ackPayload,
                operation = operation,
            )
        }
    }

    private fun extractJournalEntries(ackPayload: Map<String, Any?>): List<Map<String, Any?>> {
        val candidates = listOf(
            ackPayload["journal_entries"],
            ackPayload["journals"],
            (ackPayload["data"] as? Map<*, *>)?.get("journal_entries"),
            (ackPayload["data"] as? Map<*, *>)?.get("journals"),
            (ackPayload["result"] as? Map<*, *>)?.get("journal_entries"),
            (ackPayload["result"] as? Map<*, *>)?.get("journals"),
            (ackPayload["payload"] as? Map<*, *>)?.get("journal_entries"),
            (ackPayload["payload"] as? Map<*, *>)?.get("journals"),
        )

        candidates.forEach { candidate ->
            val parsed = decodeJournalEntriesFromAny(candidate)
            if (parsed.isNotEmpty()) {
                return parsed
            }
        }
        return emptyList()
    }

    private fun decodeJournalEntriesFromAny(raw: Any?): List<Map<String, Any?>> {
        when (raw) {
            is List<*> -> {
                return raw.mapNotNull { row ->
                    val map = row as? Map<*, *> ?: return@mapNotNull null
                    map.entries.associate { (key, value) -> key.toString() to value }
                }
            }
            is String -> {
                return decodeJournalEntriesFromAny(decodePayloadJson(raw))
            }
            is Map<*, *> -> {
                val nestedCandidates = listOf(raw["entries"], raw["items"], raw["journal_entries"], raw["journals"])
                nestedCandidates.forEach { nested ->
                    val parsed = decodeJournalEntriesFromAny(nested)
                    if (parsed.isNotEmpty()) {
                        return parsed
                    }
                }
            }
        }

        return emptyList()
    }

    private fun isAckAccepted(ackPayload: Map<String, Any?>): Boolean {
        val status = listOf(
            ackPayload["status"],
            (ackPayload["data"] as? Map<*, *>)?.get("status"),
            (ackPayload["result"] as? Map<*, *>)?.get("status"),
            (ackPayload["payload"] as? Map<*, *>)?.get("status"),
        ).firstNotNullOfOrNull { (it as? String)?.trim()?.lowercase() }

        if (status in setOf("failed", "error", "rejected", "invalid")) {
            return false
        }

        val success = listOf(
            ackPayload["success"],
            ackPayload["ok"],
            ackPayload["acknowledged"],
            (ackPayload["data"] as? Map<*, *>)?.get("success"),
            (ackPayload["result"] as? Map<*, *>)?.get("success"),
            (ackPayload["payload"] as? Map<*, *>)?.get("success"),
        ).firstNotNullOfOrNull {
            when (it) {
                is Boolean -> it
                is Number -> it.toInt() != 0
                is String -> it.equals("true", ignoreCase = true) || it == "1"
                else -> null
            }
        }

        return success != false
    }

    private fun costToken(value: Double): String {
        return String.format(java.util.Locale.US, "%.6f", value)
    }

}
