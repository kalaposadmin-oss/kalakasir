package com.umkm.offline.app.recipe

import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.RecipeDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.SyncActionTypeCountRow
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.RecipeItemEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class RecipeRepositoryTest {

    @Test
    fun replaceRecipeOffline_savesRecipeRows_andEnqueuesUpsertRecipeAction() = runTest {
        val recipeDao = FakeRecipeDao()
        val productDao = FakeProductDao()
        val syncActionDao = FakeSyncActionDao()
        val repository = RecipeRepository(recipeDao, productDao, syncActionDao)

        repository.replaceRecipeOffline(
            productLocalId = "prod-fg-1",
            lines = listOf(
                RecipeDraftLine(
                    materialProductLocalId = "prod-rm-1",
                    materialName = "Gula",
                    qty = 1.5,
                    unit = "gram",
                ),
            ),
        )

        val rows = recipeDao.findByProductLocalId("prod-fg-1")
        assertEquals(1, rows.size)
        assertEquals("Gula", rows.first().materialName)

        assertEquals(1, syncActionDao.actions.size)
        val action = syncActionDao.actions.first()
        assertEquals(EntityType.RECIPE, action.entityType)
        assertEquals(SyncActionType.UPSERT_RECIPE, action.actionType)
        assertTrue(action.payloadJson.contains("\"bom_items\""))
        assertTrue(action.payloadJson.contains("\"product_mode\":\"bom\""))
    }

    @Test(expected = IllegalArgumentException::class)
    fun replaceRecipeOffline_rejectsUnknownMaterialProduct() = runTest {
        val repository = RecipeRepository(FakeRecipeDao(), FakeProductDao(emptyMap()), FakeSyncActionDao())

        repository.replaceRecipeOffline(
            productLocalId = "prod-fg-1",
            lines = listOf(
                RecipeDraftLine(
                    materialProductLocalId = "unknown-material",
                    materialName = "Tidak Ada",
                    qty = 1.0,
                    unit = "gram",
                ),
            ),
        )
    }

    @Test(expected = IllegalArgumentException::class)
    fun replaceRecipeOffline_rejectsNonFinishedGoodOutputProduct() = runTest {
        val products = mapOf(
            "prod-fg-1" to FakeProductMeta(itemType = "raw_material", productMode = "add_on"),
            "prod-rm-1" to FakeProductMeta(itemType = "raw_material", productMode = "add_on"),
        )
        val repository = RecipeRepository(FakeRecipeDao(), FakeProductDao(products), FakeSyncActionDao())

        repository.replaceRecipeOffline(
            productLocalId = "prod-fg-1",
            lines = listOf(
                RecipeDraftLine(
                    materialProductLocalId = "prod-rm-1",
                    materialName = "Gula",
                    qty = 1.0,
                    unit = "gram",
                ),
            ),
        )
    }

    @Test(expected = IllegalArgumentException::class)
    fun replaceRecipeOffline_rejectsNonRawMaterialInputProduct() = runTest {
        val products = mapOf(
            "prod-fg-1" to FakeProductMeta(itemType = "finished_good", productMode = "bom"),
            "prod-rm-1" to FakeProductMeta(itemType = "service", productMode = "standard"),
        )
        val repository = RecipeRepository(FakeRecipeDao(), FakeProductDao(products), FakeSyncActionDao())

        repository.replaceRecipeOffline(
            productLocalId = "prod-fg-1",
            lines = listOf(
                RecipeDraftLine(
                    materialProductLocalId = "prod-rm-1",
                    materialName = "Jasa Helper",
                    qty = 1.0,
                    unit = "pcs",
                ),
            ),
        )
    }

    private data class FakeProductMeta(
        val itemType: String,
        val productMode: String,
    )

    private class FakeRecipeDao : RecipeDao {
        private val rows = MutableStateFlow<List<RecipeItemEntity>>(emptyList())

        override fun observeAll(): Flow<List<RecipeItemEntity>> = rows

        override suspend fun findByProductLocalId(productLocalId: String): List<RecipeItemEntity> {
            return rows.value.filter { it.productLocalId == productLocalId }
        }

        override suspend fun deleteByProductLocalId(productLocalId: String) {
            rows.value = rows.value.filterNot { it.productLocalId == productLocalId }
        }

        override suspend fun upsertAll(items: List<RecipeItemEntity>) {
            rows.value = rows.value + items
        }
    }

    private class FakeProductDao(
        private val products: Map<String, FakeProductMeta> = mapOf(
            "prod-fg-1" to FakeProductMeta(itemType = "finished_good", productMode = "bom"),
            "prod-rm-1" to FakeProductMeta(itemType = "raw_material", productMode = "add_on"),
        ),
    ) : ProductDao {
        override fun observeProducts(): Flow<List<ProductEntity>> = flowOf(emptyList())

        override fun observeProductsByKeyword(keyword: String): Flow<List<ProductEntity>> = flowOf(emptyList())

        override suspend fun upsert(product: ProductEntity) = Unit

        override suspend fun upsertAll(products: List<ProductEntity>) = Unit

        override suspend fun findByLocalId(localId: String): ProductEntity? {
            val meta = products[localId]
            return if (meta != null) {
                ProductEntity(
                    localId = localId,
                    serverId = null,
                    name = localId,
                    categoryName = "Demo",
                    itemType = meta.itemType,
                    productMode = meta.productMode,
                    barcode = null,
                    price = 0,
                    stockOnHand = 0.0,
                    syncStatus = SyncStatus.SYNCED,
                    version = 1,
                    updatedAtLocal = 1L,
                    updatedAtServer = null,
                )
            } else {
                null
            }
        }

        override suspend fun countProducts(): Long = products.size.toLong()
    }

    private class FakeSyncActionDao : SyncActionDao {
        val actions = mutableListOf<SyncActionEntity>()

        override suspend fun enqueue(action: SyncActionEntity) {
            actions.add(action)
        }

        override suspend fun findDueActions(now: Long, limit: Int): List<SyncActionEntity> = emptyList()

        override suspend fun claimForProcessing(actionId: String, lockedUntil: Long): Int = 0

        override suspend fun delete(actionId: String) = Unit

        override suspend fun markFailed(actionId: String, nextRetryTime: Long, errorMessage: String) = Unit

        override suspend fun countByIdempotencyKey(idempotencyKey: String): Int = 0

        override fun observePendingCount(): Flow<Int> = flowOf(actions.count { it.actionStatus != OutboxStatus.FAILED })

        override fun observeFailedCount(): Flow<Int> = flowOf(actions.count { it.actionStatus == OutboxStatus.FAILED })

        override fun observeActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> = flowOf(emptyList())

        override fun observeFailedActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> = flowOf(emptyList())
    }
}
