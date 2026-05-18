package com.umkm.offline.core.database.dao

import androidx.room.ColumnInfo
import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import com.umkm.offline.core.database.entity.PaymentEntity
import com.umkm.offline.core.database.entity.SaleEntity
import com.umkm.offline.core.database.entity.SaleItemEntity
import kotlinx.coroutines.flow.Flow

data class SaleItemWithProductNameRow(
    @ColumnInfo(name = "saleLocalId") val saleLocalId: String,
    @ColumnInfo(name = "productLocalId") val productLocalId: String,
    @ColumnInfo(name = "qty") val qty: Double,
    @ColumnInfo(name = "lineTotal") val lineTotal: Long,
    @ColumnInfo(name = "productName") val productName: String?,
)

@Dao
interface SaleDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertSale(sale: SaleEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSaleItems(items: List<SaleItemEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertPayment(payment: PaymentEntity)

    @Transaction
    suspend fun insertFinalizedSale(
        sale: SaleEntity,
        items: List<SaleItemEntity>,
        payment: PaymentEntity,
    ) {
        upsertSale(sale)
        insertSaleItems(items)
        upsertPayment(payment)
    }

    @Query("SELECT COUNT(*) FROM sales")
    suspend fun countSales(): Long

    @Query("SELECT * FROM sales ORDER BY finalizedAt DESC LIMIT :limit")
    fun observeRecentSales(limit: Int): Flow<List<SaleEntity>>

    @Query("SELECT sale_items.* FROM sale_items INNER JOIN sales ON sales.localId = sale_items.saleLocalId ORDER BY sales.finalizedAt DESC, sale_items.localId DESC LIMIT :limit")
    fun observeRecentSaleItems(limit: Int): Flow<List<SaleItemEntity>>

    @Query("SELECT sale_items.saleLocalId AS saleLocalId, sale_items.productLocalId AS productLocalId, sale_items.qty AS qty, sale_items.lineTotal AS lineTotal, products.name AS productName FROM sale_items INNER JOIN sales ON sales.localId = sale_items.saleLocalId LEFT JOIN products ON products.localId = sale_items.productLocalId ORDER BY sales.finalizedAt DESC, sale_items.localId DESC LIMIT :limit")
    fun observeRecentSaleItemsWithProductName(limit: Int): Flow<List<SaleItemWithProductNameRow>>

    @Query("SELECT payments.* FROM payments INNER JOIN sales ON sales.localId = payments.saleLocalId ORDER BY sales.finalizedAt DESC LIMIT :limit")
    fun observeRecentPayments(limit: Int): Flow<List<PaymentEntity>>

    @Query("SELECT * FROM sales WHERE localId = :saleLocalId LIMIT 1")
    suspend fun getSaleByLocalId(saleLocalId: String): SaleEntity?

    @Query("SELECT * FROM sales WHERE receiptNo = :receiptNo ORDER BY finalizedAt DESC LIMIT 1")
    suspend fun getLatestSaleByReceiptNo(receiptNo: String): SaleEntity?

    @Query("SELECT COALESCE(SUM(totalAmount), 0) FROM sales WHERE finalizedAt >= :openedAt AND finalizedAt <= :closedAt")
    suspend fun sumTotalAmountBetween(openedAt: Long, closedAt: Long): Long
}
