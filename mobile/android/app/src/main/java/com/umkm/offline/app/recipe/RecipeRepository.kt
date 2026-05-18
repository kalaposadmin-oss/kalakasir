package com.umkm.offline.app.recipe

import android.util.Log
import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.RecipeDao
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.entity.RecipeItemEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import java.util.UUID

class RecipeRepository(
    private val recipeDao: RecipeDao,
    private val productDao: ProductDao,
    private val syncActionDao: SyncActionDao,
) {

    suspend fun replaceRecipeOffline(
        productLocalId: String,
        lines: List<RecipeDraftLine>,
    ) {
        val now = System.currentTimeMillis()
        val normalizedProductId = productLocalId.trim()
        require(normalizedProductId.isNotBlank()) { "Produk wajib dipilih." }

        val normalizedLines = lines
            .map { line ->
                RecipeDraftLine(
                    materialProductLocalId = line.materialProductLocalId?.trim()?.ifBlank { null },
                    materialName = line.materialName.trim(),
                    qty = line.qty,
                    unit = line.unit.trim(),
                )
            }
            .filter { it.materialName.isNotBlank() && it.qty > 0.0 && it.unit.isNotBlank() }

        require(normalizedLines.isNotEmpty()) { "Minimal satu bahan resep wajib diisi." }
        normalizedLines.forEach { line ->
            require(line.materialProductLocalId != normalizedProductId) { "Produk output tidak boleh menjadi bahan untuk dirinya sendiri." }
        }

        val outputProduct = productDao.findByLocalId(normalizedProductId)
            ?: throw IllegalArgumentException("Produk output tidak ditemukan: $normalizedProductId")
        val outputType = normalizedItemType(outputProduct.itemType, outputProduct.productMode)
        require(outputType == ITEM_TYPE_FINISHED_GOOD) {
            "Produk output resep harus bertipe finished_good."
        }

        val duplicateMaterialIds = normalizedLines
            .mapNotNull { it.materialProductLocalId }
            .groupingBy { it }
            .eachCount()
            .filterValues { count -> count > 1 }
        require(duplicateMaterialIds.isEmpty()) { "Bahan duplikat tidak diperbolehkan dalam satu resep." }

        normalizedLines.mapNotNull { it.materialProductLocalId }.forEach { materialId ->
            val material = productDao.findByLocalId(materialId)
            require(material != null) { "Komponen bahan tidak ditemukan: $materialId" }
            val materialType = normalizedItemType(material.itemType, material.productMode)
            require(materialType == ITEM_TYPE_RAW_MATERIAL) {
                "Komponen resep harus bertipe raw_material: $materialId"
            }
        }

        debugLog("replaceRecipeOffline product=$normalizedProductId output_type=$outputType lines=${normalizedLines.size}")

        recipeDao.deleteByProductLocalId(normalizedProductId)

        val rows = normalizedLines.map { line ->
            RecipeItemEntity(
                localId = UUID.randomUUID().toString(),
                productLocalId = normalizedProductId,
                materialProductLocalId = line.materialProductLocalId,
                materialName = line.materialName,
                qty = line.qty,
                unit = line.unit,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = 1L,
                updatedAtLocal = now,
                updatedAtServer = null,
            )
        }

        if (rows.isNotEmpty()) {
            recipeDao.upsertAll(rows)
        }

        debugLog("recipe_rows_saved product=$normalizedProductId count=${rows.size} materials=${rows.mapNotNull { it.materialProductLocalId }}")

        val payloadItems = normalizedLines.joinToString(",") { line ->
            "{\"product_id\":${jsonStringOrNull(line.materialProductLocalId)},\"name\":\"${escapeJson(line.materialName)}\",\"qty\":${formatQty(line.qty)},\"unit\":\"${escapeJson(line.unit)}\",\"material_product_local_id\":${jsonStringOrNull(line.materialProductLocalId)},\"material_name\":\"${escapeJson(line.materialName)}\"}"
        }
        val payload = "{\"product_local_id\":\"${escapeJson(normalizedProductId)}\",\"product_mode\":\"bom\",\"bom_items\":[${payloadItems}],\"recipe_items\":[${payloadItems}]}"

        syncActionDao.enqueue(
            SyncActionEntity(
                actionId = UUID.randomUUID().toString(),
                entityType = EntityType.RECIPE,
                entityId = normalizedProductId,
                actionType = SyncActionType.UPSERT_RECIPE,
                payloadJson = payload,
                idempotencyKey = UUID.randomUUID().toString(),
                retryCount = 0,
                nextRetryTime = now,
                actionStatus = OutboxStatus.PENDING,
                lockedUntil = null,
                lastError = null,
                createdAt = now,
            ),
        )
    }

    private fun escapeJson(value: String): String {
        return value.replace("\\", "\\\\").replace("\"", "\\\"")
    }

    private fun jsonStringOrNull(value: String?): String {
        return value?.let { "\"${escapeJson(it)}\"" } ?: "null"
    }

    private fun formatQty(value: Double): String {
        return String.format(java.util.Locale.US, "%.4f", value)
    }

    private fun normalizedItemType(itemType: String?, productMode: String?): String {
        val normalizedMode = productMode?.trim()?.lowercase().orEmpty()
        val normalizedType = itemType?.trim()?.lowercase().orEmpty()
        return when {
            normalizedMode in setOf("add_on", "addon", "add-on") -> ITEM_TYPE_RAW_MATERIAL
            normalizedType in setOf("add_on", "addon", "add-on") -> ITEM_TYPE_RAW_MATERIAL
            else -> normalizedType
        }
    }

    private fun debugLog(message: String) {
        runCatching { Log.d("RecipeRepository", message) }
    }

    private companion object {
        const val ITEM_TYPE_RAW_MATERIAL = "raw_material"
        const val ITEM_TYPE_FINISHED_GOOD = "finished_good"
    }
}

data class RecipeDraftLine(
    val materialProductLocalId: String?,
    val materialName: String,
    val qty: Double,
    val unit: String,
)
