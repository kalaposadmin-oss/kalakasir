package com.umkm.offline.feature.pos.presentation

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.umkm.offline.core.common.ConnectivityState
import com.umkm.offline.feature.pos.data.AutoConsumeParityException
import com.umkm.offline.feature.pos.data.CheckoutGuardException
import com.umkm.offline.feature.pos.domain.CheckoutItem
import com.umkm.offline.feature.pos.domain.FinalizeSaleUseCase
import com.umkm.offline.feature.pos.domain.PosRepository
import com.umkm.offline.feature.pos.usecase.ShiftGuardContext
import com.umkm.offline.feature.pos.usecase.ShiftGuardFailureReason
import com.umkm.offline.feature.pos.usecase.ShiftGuardResult
import com.umkm.offline.feature.pos.usecase.ShiftGuardUseCase
import com.umkm.offline.feature.pos.usecase.CheckoutValidationContext
import com.umkm.offline.feature.pos.usecase.CheckoutValidationFailureReason
import com.umkm.offline.feature.pos.usecase.CheckoutValidationItem
import com.umkm.offline.feature.pos.usecase.CheckoutValidationResult
import com.umkm.offline.feature.pos.usecase.ValidateCheckoutContextUseCase
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class PosViewModel(
    private val repository: PosRepository,
    private val finalizeSaleUseCase: FinalizeSaleUseCase,
    private val shiftGuardUseCase: ShiftGuardUseCase = ShiftGuardUseCase(),
    private val validateCheckoutContextUseCase: ValidateCheckoutContextUseCase = ValidateCheckoutContextUseCase(),
) : ViewModel() {

    private val _uiState = MutableStateFlow(PosUiState())
    val uiState: StateFlow<PosUiState> = _uiState.asStateFlow()
    private val searchKeywordFlow = MutableStateFlow("")

    init {
        observeProducts()
        observeCustomers()
    }

    @OptIn(ExperimentalCoroutinesApi::class)
    private fun observeProducts() {
        viewModelScope.launch {
            searchKeywordFlow
                .flatMapLatest { keyword -> repository.observeProducts(keyword) }
                .collect { products ->
                    _uiState.update {
                        it.copy(
                            products = products.map { p ->
                                PosProductItemUi(
                                    localId = p.localId,
                                    name = p.name,
                                    categoryName = p.categoryName,
                                    price = p.price,
                                    itemType = p.itemType,
                                    productMode = p.productMode,
                                    stockOnHand = p.stockOnHand,
                                )
                            },
                        )
                    }
                }
        }
    }

    private fun observeCustomers() {
        viewModelScope.launch {
            repository.observeCustomers().collect { customers ->
                _uiState.update { state ->
                    val nextSelectedCustomer = when {
                        state.selectedCustomerLocalId == null -> customers.firstOrNull()?.localId
                        customers.any { it.localId == state.selectedCustomerLocalId } -> state.selectedCustomerLocalId
                        else -> customers.firstOrNull()?.localId
                    }

                    state.copy(
                        customers = customers.map { PosCustomerUi(localId = it.localId, name = it.name) },
                        selectedCustomerLocalId = nextSelectedCustomer,
                    )
                }
            }
        }
    }

    fun setConnectivity(state: ConnectivityState) {
        _uiState.update { it.copy(connectivityState = state) }
    }

    fun setActiveShiftLocalId(shiftLocalId: String?) {
        _uiState.update { it.copy(activeShiftLocalId = shiftLocalId) }
    }

    fun setActiveShiftContext(
        shiftLocalId: String?,
        shiftStatus: String?,
        shiftOwnerUserLocalId: String?,
        shiftBranchLocalId: String? = null,
        shiftRegisterLocalId: String? = null,
    ) {
        _uiState.update {
            it.copy(
                activeShiftLocalId = shiftLocalId,
                activeShiftStatus = shiftStatus,
                activeShiftOwnerUserLocalId = shiftOwnerUserLocalId,
                activeShiftBranchLocalId = shiftBranchLocalId,
                activeShiftRegisterLocalId = shiftRegisterLocalId,
            )
        }
    }

    fun setActorContext(
        actorUserLocalId: String?,
        actorBranchLocalId: String? = null,
        actorRegisterLocalId: String? = null,
    ) {
        _uiState.update {
            it.copy(
                actorUserLocalId = actorUserLocalId,
                actorBranchLocalId = actorBranchLocalId,
                actorRegisterLocalId = actorRegisterLocalId,
            )
        }
    }

    fun onSearchChanged(value: String) {
        _uiState.update { it.copy(searchKeyword = value.trim()) }
        searchKeywordFlow.value = value.trim()
    }

    fun addToCart(productId: String) {
        val currentState = uiState.value
        val filteredProducts = filteredProducts(currentState)
        val product = filteredProducts.find { it.localId == productId } ?: return
        _uiState.update { state ->
            val existing = state.cartItems.find { it.productLocalId == product.localId }
            if (existing == null) {
                state.copy(
                    cartItems = state.cartItems + CartItemUi(
                        productLocalId = product.localId,
                        name = product.name,
                        categoryName = product.categoryName,
                        qty = 1.0,
                        unitPrice = product.price,
                        lineTotal = product.price,
                    ),
                    message = "Produk ditambahkan ke cart.",
                    errorMessage = null,
                )
            } else {
                val nextQty = existing.qty + 1.0
                state.copy(
                    cartItems = state.cartItems.map {
                        if (it.productLocalId == existing.productLocalId) {
                            it.copy(qty = nextQty, lineTotal = (nextQty * it.unitPrice).toLong())
                        } else {
                            it
                        }
                    },
                    message = "Qty produk diperbarui.",
                    errorMessage = null,
                )
            }
        }
    }

    fun selectCategory(categoryName: String) {
        _uiState.update { it.copy(selectedCategory = categoryName) }
    }

    fun selectCustomer(customerLocalId: String?) {
        _uiState.update { it.copy(selectedCustomerLocalId = customerLocalId) }
    }

    fun removeFromCart(productId: String) {
        _uiState.update { state ->
            state.copy(cartItems = state.cartItems.filterNot { it.productLocalId == productId })
        }
    }

    fun openPaymentStep() {
        if (uiState.value.cartItems.isEmpty()) {
            _uiState.update { it.copy(errorMessage = "Cart masih kosong.") }
            return
        }
        _uiState.update { it.copy(checkoutStep = CheckoutStep.PAYMENT, errorMessage = null) }
    }

    fun backToCart() {
        _uiState.update { it.copy(checkoutStep = CheckoutStep.CART, errorMessage = null) }
    }

    fun selectPaymentMethod(method: String) {
        _uiState.update { it.copy(paymentMethod = method) }
    }

    fun cartTotal(): Long {
        return uiState.value.cartItems.sumOf { it.lineTotal }
    }

    fun finalizeSaleOffline(paidAmount: Long, discountAmount: Long = 0L) {
        if (uiState.value.isSaving) {
            return
        }

        val shiftGuardResult = shiftGuardUseCase(
            ShiftGuardContext(
                shiftLocalId = uiState.value.activeShiftLocalId,
                shiftStatus = uiState.value.activeShiftStatus,
                shiftOwnerUserLocalId = uiState.value.activeShiftOwnerUserLocalId,
                shiftBranchLocalId = uiState.value.activeShiftBranchLocalId,
                shiftRegisterLocalId = uiState.value.activeShiftRegisterLocalId,
                actorUserLocalId = uiState.value.actorUserLocalId,
                actorBranchLocalId = uiState.value.actorBranchLocalId,
                actorRegisterLocalId = uiState.value.actorRegisterLocalId,
            ),
        )
        if (shiftGuardResult is ShiftGuardResult.Rejected) {
            _uiState.update { it.copy(errorMessage = shiftGuardMessage(shiftGuardResult.reason)) }
            return
        }

        val cartItems = uiState.value.cartItems
        val subtotalAmount = cartItems.sumOf { it.lineTotal }
        val normalizedDiscount = discountAmount.coerceAtLeast(0L).coerceAtMost(subtotalAmount)
        val totalAmount = subtotalAmount - normalizedDiscount
        val checkoutValidationResult = validateCheckoutContextUseCase(
            CheckoutValidationContext(
                items = cartItems.map {
                    CheckoutValidationItem(
                        productLocalId = it.productLocalId,
                        qty = it.qty,
                        unitPrice = it.unitPrice,
                        lineTotal = it.lineTotal,
                    )
                },
                discountAmount = normalizedDiscount,
                expectedSubtotal = subtotalAmount,
                expectedTotal = totalAmount,
                paidAmount = paidAmount,
                paymentMethod = uiState.value.paymentMethod,
                shiftLocalId = uiState.value.activeShiftLocalId,
                actorUserLocalId = uiState.value.actorUserLocalId,
                actorBranchLocalId = uiState.value.actorBranchLocalId,
                actorRegisterLocalId = uiState.value.actorRegisterLocalId,
            ),
        )
        if (checkoutValidationResult is CheckoutValidationResult.Rejected) {
            _uiState.update { it.copy(errorMessage = checkoutValidationMessage(checkoutValidationResult.reason)) }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null) }
            val receiptNo = "TRX-${System.currentTimeMillis()}"
            val selectedCustomerName = uiState.value.customers
                .firstOrNull { it.localId == uiState.value.selectedCustomerLocalId }
                ?.name
                ?: "Umum"
            val usedPaymentMethod = uiState.value.paymentMethod
            val checkoutItems = cartItems.map {
                CheckoutItem(
                    productLocalId = it.productLocalId,
                    qty = it.qty,
                    unitPrice = it.unitPrice,
                )
            }
            val changeAmount = paidAmount - totalAmount

            runCatching {
                finalizeSaleUseCase(
                    receiptNo = receiptNo,
                    customerLocalId = uiState.value.selectedCustomerLocalId,
                    items = checkoutItems,
                    discountAmount = normalizedDiscount,
                    paymentMethod = usedPaymentMethod,
                    paidAmount = paidAmount,
                    shiftLocalId = uiState.value.activeShiftLocalId,
                )
            }.onSuccess { saleId ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        lastSaleId = saleId,
                        lastReceipt = SaleReceiptUi(
                            saleLocalId = saleId,
                            receiptNo = receiptNo,
                            customerName = selectedCustomerName,
                            items = cartItems.map { cartItem ->
                                ReceiptItemUi(
                                    name = cartItem.name,
                                    qty = cartItem.qty,
                                    lineTotal = cartItem.lineTotal,
                                )
                            },
                            totalAmount = totalAmount,
                            discountAmount = normalizedDiscount,
                            paymentMethodLabel = paymentMethodLabel(usedPaymentMethod),
                            syncStatusLabel = "PENDING_SYNC",
                            paidAmount = paidAmount,
                            changeAmount = changeAmount,
                        ),
                        cartItems = emptyList(),
                        checkoutStep = CheckoutStep.CART,
                        message = "Transaksi tersimpan lokal. Receipt siap untuk review kasir.",
                    )
                }
            }.onFailure { error ->
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        errorMessage = mapCheckoutFailureMessage(error),
                    )
                }
            }
        }
    }

    private fun shiftGuardMessage(reason: ShiftGuardFailureReason): String {
        return when (reason) {
            ShiftGuardFailureReason.SHIFT_NOT_OPEN -> "Shift belum aktif. Buka shift dulu sebelum checkout."
            ShiftGuardFailureReason.SHIFT_USER_MISMATCH -> "Akun kasir tidak sesuai dengan pemilik shift aktif."
            ShiftGuardFailureReason.SHIFT_BRANCH_MISMATCH -> "Cabang kasir tidak sesuai dengan shift aktif."
            ShiftGuardFailureReason.SHIFT_REGISTER_MISMATCH -> "Register kasir tidak sesuai dengan shift aktif."
        }
    }

    private fun checkoutValidationMessage(reason: CheckoutValidationFailureReason): String {
        return when (reason) {
            CheckoutValidationFailureReason.CHECKOUT_ITEMS_EMPTY -> "Cart masih kosong."
            CheckoutValidationFailureReason.CHECKOUT_TOTAL_MISMATCH -> "Total checkout tidak valid. Coba ulangi perhitungan cart."
            CheckoutValidationFailureReason.CHECKOUT_PAYMENT_MISMATCH -> "Data pembayaran tidak valid."
            CheckoutValidationFailureReason.CHECKOUT_UNDERPAID -> "Nominal bayar kurang dari total tagihan."
            CheckoutValidationFailureReason.CHECKOUT_MISSING_CONTEXT -> "Konteks kasir/shift belum lengkap."
            CheckoutValidationFailureReason.CHECKOUT_INVALID_ITEM_QTY -> "Qty produk tidak valid."
            CheckoutValidationFailureReason.CHECKOUT_INVALID_ITEM_PRICE -> "Harga jual belum diisi"
        }
    }

    private fun mapCheckoutFailureMessage(error: Throwable): String {
        return when (error) {
            is CheckoutGuardException -> when (error.code) {
                "STOCK_INSUFFICIENT" -> "Stok tidak cukup"
                "FIFO_LAYER_INSUFFICIENT" -> "Stok FIFO tidak cukup"
                "INVALID_SELL_PRICE" -> "Harga jual belum diisi"
                "RECIPE_MATERIAL_INSUFFICIENT" -> "Bahan resep tidak cukup"
                "PRODUCT_NOT_READY", "PRODUCT_NOT_SELLABLE" -> "Produk belum siap dijual"
                else -> error.message
            }

            is AutoConsumeParityException -> when (error.code) {
                "AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL" -> "Bahan resep tidak cukup"
                "RECIPE_OUTPUT_TYPE_INVALID", "RECIPE_INPUT_TYPE_INVALID" -> "Produk belum siap dijual"
                else -> error.message
            }

            else -> error.message
        } ?: "Checkout gagal diproses."
    }

    fun categories(): List<String> {
        val categories = uiState.value.products.map { it.categoryName }.distinct().sorted()
        return listOf(ALL_CATEGORIES) + categories
    }

    fun filteredProducts(): List<PosProductItemUi> {
        return filteredProducts(uiState.value)
    }

    private fun filteredProducts(state: PosUiState): List<PosProductItemUi> {
        if (state.selectedCategory == ALL_CATEGORIES) {
            return state.products
        }
        return state.products.filter { it.categoryName == state.selectedCategory }
    }

    private fun paymentMethodLabel(method: String): String {
        return when (method) {
            "cash" -> "Tunai"
            "qris" -> "QRIS"
            "debit" -> "Kartu Debit"
            "transfer" -> "Transfer Bank"
            else -> method
        }
    }
}
