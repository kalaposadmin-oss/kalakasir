package com.umkm.offline.feature.pos.domain

import kotlinx.coroutines.flow.Flow

data class PosProduct(
    val localId: String,
    val name: String,
    val categoryName: String,
    val price: Long,
    val itemType: String? = null,
    val productMode: String? = null,
    val stockOnHand: Double = 0.0,
)

data class PosCustomer(
    val localId: String,
    val name: String,
)

data class CheckoutItem(
    val productLocalId: String,
    val qty: Double,
    val unitPrice: Long,
)

interface PosRepository {
    fun observeProducts(keyword: String = ""): Flow<List<PosProduct>>
    fun observeCustomers(): Flow<List<PosCustomer>>

    suspend fun finalizeSaleOffline(
        receiptNo: String,
        customerLocalId: String?,
        items: List<CheckoutItem>,
        discountAmount: Long,
        paymentMethod: String,
        paidAmount: Long,
        shiftLocalId: String?,
    ): String
}
