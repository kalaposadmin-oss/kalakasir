package com.umkm.offline.core.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.PurchaseReturnItemEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface PurchaseDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertPurchase(purchase: PurchaseEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPurchaseItems(items: List<PurchaseItemEntity>)

    @Transaction
    suspend fun insertFinalizedPurchase(
        purchase: PurchaseEntity,
        items: List<PurchaseItemEntity>,
    ) {
        upsertPurchase(purchase)
        insertPurchaseItems(items)
    }

    @Query("SELECT COUNT(*) FROM purchases")
    suspend fun countPurchases(): Long

    @Query("SELECT * FROM purchases WHERE localId = :purchaseLocalId LIMIT 1")
    suspend fun findPurchaseByLocalId(purchaseLocalId: String): PurchaseEntity?

    @Query("SELECT * FROM purchases ORDER BY purchasedAt DESC LIMIT :limit")
    fun observeRecentPurchases(limit: Int): Flow<List<PurchaseEntity>>

    @Query("SELECT * FROM purchase_items WHERE purchaseLocalId = :purchaseLocalId ORDER BY localId DESC")
    fun observeItemsByPurchase(purchaseLocalId: String): Flow<List<PurchaseItemEntity>>

    @Query("SELECT * FROM purchase_items WHERE purchaseLocalId = :purchaseLocalId")
    suspend fun findPurchaseItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseItemEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPurchaseReturnItems(items: List<PurchaseReturnItemEntity>)

    @Query("SELECT * FROM purchase_return_items WHERE purchaseLocalId = :purchaseLocalId")
    suspend fun findPurchaseReturnItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseReturnItemEntity>
}
