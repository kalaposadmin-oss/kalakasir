package com.umkm.offline.feature.pos.presentation

import com.umkm.offline.core.common.ConnectivityState

data class PosUiState(
    val connectivityState: ConnectivityState = ConnectivityState.OFFLINE,
    val activeShiftLocalId: String? = null,
    val activeShiftStatus: String? = null,
    val activeShiftOwnerUserLocalId: String? = null,
    val activeShiftBranchLocalId: String? = null,
    val activeShiftRegisterLocalId: String? = null,
    val actorUserLocalId: String? = null,
    val actorBranchLocalId: String? = null,
    val actorRegisterLocalId: String? = null,
    val searchKeyword: String = "",
    val products: List<PosProductItemUi> = emptyList(),
    val customers: List<PosCustomerUi> = emptyList(),
    val selectedCustomerLocalId: String? = null,
    val selectedCategory: String = ALL_CATEGORIES,
    val cartItems: List<CartItemUi> = emptyList(),
    val paymentMethod: String = "cash",
    val checkoutStep: CheckoutStep = CheckoutStep.CART,
    val isSaving: Boolean = false,
    val lastSaleId: String? = null,
    val lastReceipt: SaleReceiptUi? = null,
    val errorMessage: String? = null,
    val message: String? = null,
)

const val ALL_CATEGORIES = "Semua"

data class PosProductItemUi(
    val localId: String,
    val name: String,
    val categoryName: String,
    val price: Long,
    val itemType: String? = null,
    val productMode: String? = null,
    val stockOnHand: Double = 0.0,
)

data class PosCustomerUi(
    val localId: String,
    val name: String,
)

data class CartItemUi(
    val productLocalId: String,
    val name: String,
    val categoryName: String,
    val qty: Double,
    val unitPrice: Long,
    val lineTotal: Long,
)

data class SaleReceiptUi(
    val saleLocalId: String,
    val receiptNo: String,
    val customerName: String,
    val items: List<ReceiptItemUi>,
    val totalAmount: Long,
    val discountAmount: Long = 0,
    val paymentMethodLabel: String,
    val syncStatusLabel: String,
    val paidAmount: Long = totalAmount,
    val changeAmount: Long = 0,
)

data class ReceiptItemUi(
    val name: String,
    val qty: Double,
    val lineTotal: Long,
)

enum class CheckoutStep {
    CART,
    PAYMENT,
}
