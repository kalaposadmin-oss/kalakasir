package com.umkm.offline.feature.pos.presentation

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items as gridItems
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.AccountBalance
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.PersonAdd
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.Print
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.ViewAgenda
import androidx.compose.material.icons.filled.ViewModule
import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private val PosPrimary = Color(0xFF7BAE9E)
private val PosAccentBlue = Color(0xFF94B3C5)
private val PosBackground = Color(0xFFF8FAF9)
private val PosCard = Color.White

private data class HistoryTransactionUi(
    val receiptNo: String,
    val paymentMethod: String,
    val total: Long,
    val status: String,
    val timestamp: Long,
)

@Composable
fun PosScreen(
    state: PosUiState,
    products: List<PosProductItemUi>,
    categories: List<String>,
    cartTotal: Long,
    canUseCustomerFeature: Boolean,
    isWriteLocked: Boolean,
    onKeywordChanged: (String) -> Unit,
    onCategorySelected: (String) -> Unit,
    onCustomerSelected: (String?) -> Unit,
    onAddProduct: (String) -> Unit,
    onRemoveFromCart: (String) -> Unit,
    onOpenPayment: () -> Unit,
    onBackToCart: () -> Unit,
    onPaymentMethodSelected: (String) -> Unit,
    onConfirmPayment: (Long, Long) -> Unit,
    onOpenReceiptDetail: (String, String) -> Unit,
) {
    var showCartSheet by remember { mutableStateOf(false) }
    var showHistoryScreen by remember { mutableStateOf(false) }
    var showSuccessScreen by remember { mutableStateOf(false) }
    var observedLastSaleId by remember { mutableStateOf<String?>(null) }
    val transactionHistory = remember { mutableStateListOf<HistoryTransactionUi>() }

    LaunchedEffect(state.lastSaleId, state.lastReceipt) {
        if (state.lastSaleId != null && state.lastSaleId != observedLastSaleId && state.lastReceipt != null) {
            observedLastSaleId = state.lastSaleId
            showSuccessScreen = true
            showCartSheet = false
            transactionHistory.add(
                0,
                HistoryTransactionUi(
                    receiptNo = state.lastReceipt.receiptNo,
                    paymentMethod = state.lastReceipt.paymentMethodLabel,
                    total = state.lastReceipt.totalAmount,
                    status = "Sukses",
                    timestamp = System.currentTimeMillis(),
                ),
            )
        }
    }

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = PosBackground,
    ) {
        when {
            showHistoryScreen -> {
                PosHistoryScreen(history = transactionHistory, onBack = { showHistoryScreen = false })
            }

            showSuccessScreen && state.lastReceipt != null -> {
                PosSuccessScreen(
                    receipt = state.lastReceipt,
                    onNewTransaction = { showSuccessScreen = false },
                    onSeeDetail = {
                        showSuccessScreen = false
                        onOpenReceiptDetail(
                            state.lastReceipt.saleLocalId,
                            state.lastReceipt.receiptNo,
                        )
                    },
                )
            }

            state.checkoutStep == CheckoutStep.PAYMENT -> {
                StyledPaymentScreen(
                    state = state,
                    cartTotal = cartTotal,
                    isWriteLocked = isWriteLocked,
                    onBack = onBackToCart,
                    onPaymentMethodSelected = onPaymentMethodSelected,
                    onConfirmPayment = onConfirmPayment,
                )
            }

            else -> {
                PosCatalogScreen(
                    state = state,
                    products = products,
                    categories = categories,
                    cartTotal = cartTotal,
                    canUseCustomerFeature = canUseCustomerFeature,
                    isWriteLocked = isWriteLocked,
                    onKeywordChanged = onKeywordChanged,
                    onCategorySelected = onCategorySelected,
                    onCustomerSelected = onCustomerSelected,
                    onAddProduct = onAddProduct,
                    onRemoveFromCart = onRemoveFromCart,
                    onOpenPayment = onOpenPayment,
                    onToggleCart = { showCartSheet = !showCartSheet },
                    showCartSheet = showCartSheet,
                )
            }
        }
    }
}

@Composable
private fun PosCatalogScreen(
    state: PosUiState,
    products: List<PosProductItemUi>,
    categories: List<String>,
    cartTotal: Long,
    canUseCustomerFeature: Boolean,
    isWriteLocked: Boolean,
    onKeywordChanged: (String) -> Unit,
    onCategorySelected: (String) -> Unit,
    onCustomerSelected: (String?) -> Unit,
    onAddProduct: (String) -> Unit,
    onRemoveFromCart: (String) -> Unit,
    onOpenPayment: () -> Unit,
    onToggleCart: () -> Unit,
    showCartSheet: Boolean,
) {
    val cartQty = state.cartItems.sumOf { it.qty.toInt() }
    var compactView by remember { mutableStateOf(false) }
    val hasFloatingCart = cartQty > 0
    val gridBottomPadding = if (hasFloatingCart) 128.dp else 12.dp

    Box(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.fillMaxSize()) {
            PosCatalogHeader(
                state = state,
                categories = categories,
                cartQty = cartQty,
                compactView = compactView,
                onKeywordChanged = onKeywordChanged,
                onCategorySelected = onCategorySelected,
                onToggleCart = onToggleCart,
                onToggleViewMode = { compactView = !compactView },
            )

            if (products.isEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f)
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    contentAlignment = androidx.compose.ui.Alignment.Center,
                ) {
                    Card(
                        shape = MaterialTheme.shapes.large,
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                    ) {
                        Column(
                            modifier = Modifier.padding(horizontal = 20.dp, vertical = 18.dp),
                            horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            Icon(Icons.Default.Search, contentDescription = null, tint = Color(0xFF94A3B8))
                            Text(
                                text = "Produk tidak ditemukan",
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFF334155),
                                fontSize = 14.sp,
                            )
                            Text(
                                text = "Coba kata kunci atau kategori lain.",
                                color = Color(0xFF94A3B8),
                                fontSize = 12.sp,
                            )
                        }
                    }
                }
            } else if (compactView) {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f)
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    contentPadding = PaddingValues(bottom = gridBottomPadding),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(count = products.size) { productIndex ->
                        val product = products[productIndex]
                        val cartItem = state.cartItems.firstOrNull { it.productLocalId == product.localId }
                        val highlight = cartItem != null

                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { onAddProduct(product.localId) },
                            shape = MaterialTheme.shapes.large,
                            colors = CardDefaults.cardColors(
                                containerColor = if (highlight) PosPrimary.copy(alpha = 0.06f) else Color.White,
                            ),
                            border = androidx.compose.foundation.BorderStroke(
                                width = 1.dp,
                                color = if (highlight) PosPrimary.copy(alpha = 0.35f) else Color(0xFFE2E8F0),
                            ),
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(horizontal = 14.dp, vertical = 14.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                            ) {
                                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                                    Text(
                                        text = product.name,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Color(0xFF334155),
                                        fontSize = 14.sp,
                                    )
                                    Text(
                                        text = rupiah(product.price),
                                        fontWeight = FontWeight.Bold,
                                        color = PosPrimary,
                                        fontSize = 12.sp,
                                    )
                                }

                                Row(
                                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                                    verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                                ) {
                                    if (cartItem != null) {
                                        Box(
                                            modifier = Modifier
                                                .size(28.dp)
                                                .clip(androidx.compose.foundation.shape.CircleShape)
                                                .background(PosPrimary),
                                        ) {
                                            Text(
                                                text = cartItem.qty.toInt().toString(),
                                                color = Color.White,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 11.sp,
                                                modifier = Modifier.align(androidx.compose.ui.Alignment.Center),
                                            )
                                        }
                                    }
                                    Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFF94A3B8))
                                }
                            }
                        }
                    }
                }
            } else {
                LazyVerticalGrid(
                    columns = GridCells.Fixed(2),
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f)
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    contentPadding = PaddingValues(bottom = gridBottomPadding),
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    gridItems(products) { product ->
                        val cartItem = state.cartItems.firstOrNull { it.productLocalId == product.localId }
                        val highlight = cartItem != null

                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .heightIn(min = 224.dp)
                                .clickable { onAddProduct(product.localId) },
                            shape = MaterialTheme.shapes.large,
                            colors = CardDefaults.cardColors(
                                containerColor = if (highlight) PosPrimary.copy(alpha = 0.08f) else PosCard,
                            ),
                            border = androidx.compose.foundation.BorderStroke(
                                width = 1.dp,
                                color = if (highlight) PosPrimary.copy(alpha = 0.5f) else Color(0xFFE2E8F0),
                            ),
                        ) {
                            Column(modifier = Modifier.fillMaxWidth()) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(124.dp)
                                        .padding(horizontal = 12.dp, vertical = 10.dp)
                                        .clip(MaterialTheme.shapes.medium)
                                        .background(Color(0xFFF1F5F9)),
                                ) {
                                    Icon(
                                        imageVector = if (product.categoryName == "Minuman") Icons.Default.Payments else Icons.AutoMirrored.Filled.ReceiptLong,
                                        contentDescription = null,
                                        tint = PosPrimary,
                                        modifier = Modifier
                                            .size(34.dp)
                                            .align(androidx.compose.ui.Alignment.Center),
                                    )

                                    if (cartItem != null) {
                                        Box(
                                            modifier = Modifier
                                                .padding(8.dp)
                                                .size(24.dp)
                                                .clip(MaterialTheme.shapes.small)
                                                .background(PosPrimary)
                                                .align(androidx.compose.ui.Alignment.TopEnd),
                                        ) {
                                            Text(
                                                text = cartItem.qty.toInt().toString(),
                                                color = Color.White,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 11.sp,
                                                modifier = Modifier.align(androidx.compose.ui.Alignment.Center),
                                            )
                                        }
                                    }
                                }

                                Column(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp)) {
                                    Text(
                                        text = product.name,
                                        maxLines = 2,
                                        overflow = TextOverflow.Ellipsis,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Color(0xFF334155),
                                        minLines = 2,
                                    )
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text(
                                        text = product.categoryName,
                                        color = Color(0xFF94A3B8),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Medium,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis,
                                    )
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Text(
                                        text = rupiah(product.price),
                                        fontWeight = FontWeight.Bold,
                                        color = PosPrimary,
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }

        state.message?.takeIf { it.isNotBlank() }?.let { msg ->
            Box(
                modifier = Modifier
                    .align(androidx.compose.ui.Alignment.BottomCenter)
                    .padding(bottom = if (hasFloatingCart) 120.dp else 24.dp),
            ) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0xEE0F172A)),
                    shape = MaterialTheme.shapes.extraLarge,
                ) {
                    Text(
                        text = msg,
                        color = Color.White,
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 10.dp),
                    )
                }
            }
        }

        state.errorMessage?.takeIf { it.isNotBlank() }?.let { err ->
            Box(
                modifier = Modifier
                    .align(androidx.compose.ui.Alignment.BottomCenter)
                    .padding(bottom = if (hasFloatingCart) 168.dp else 72.dp),
            ) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFB91C1C)),
                    shape = MaterialTheme.shapes.extraLarge,
                ) {
                    Text(
                        text = err,
                        color = Color.White,
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 10.dp),
                        fontWeight = FontWeight.SemiBold,
                    )
                }
            }
        }

        if (hasFloatingCart) {
            Card(
                modifier = Modifier
                    .align(androidx.compose.ui.Alignment.BottomCenter)
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White),
                shape = MaterialTheme.shapes.large,
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                ) {
                    Column {
                        Text(
                            text = "$cartQty Item",
                            fontSize = 11.sp,
                            color = Color(0xFF94A3B8),
                            fontWeight = FontWeight.Bold,
                        )
                        Text(text = rupiah(cartTotal), color = PosPrimary, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    }
                    Spacer(modifier = Modifier.weight(1f))

                    Card(
                        modifier = Modifier
                            .widthIn(min = 148.dp)
                            .clickable(enabled = !isWriteLocked && state.cartItems.isNotEmpty()) { onOpenPayment() },
                        colors = CardDefaults.cardColors(containerColor = if (isWriteLocked) Color(0xFFE2E8F0) else PosPrimary),
                        shape = MaterialTheme.shapes.extraLarge,
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 20.dp, vertical = 12.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                        ) {
                            Text(
                                text = if (isWriteLocked) "Periode Dikunci" else "Lanjut Bayar",
                                color = if (isWriteLocked) Color(0xFF64748B) else Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 12.sp,
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Icon(
                                Icons.Default.ChevronRight,
                                contentDescription = null,
                                tint = if (isWriteLocked) Color(0xFF94A3B8) else Color.White,
                            )
                        }
                    }
                }
            }
        }

        if (showCartSheet) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color(0x66000000))
                    .clickable { onToggleCart() },
            )

            Card(
                modifier = Modifier
                    .align(androidx.compose.ui.Alignment.BottomCenter)
                    .fillMaxWidth()
                    .fillMaxHeight(0.86f),
                shape = androidx.compose.foundation.shape.RoundedCornerShape(topStart = 36.dp, topEnd = 36.dp),
                colors = CardDefaults.cardColors(containerColor = PosCard),
            ) {
                CartBottomSheet(
                    state = state,
                    cartTotal = cartTotal,
                    canUseCustomerFeature = canUseCustomerFeature,
                    isWriteLocked = isWriteLocked,
                    onCustomerSelected = onCustomerSelected,
                    onAddProduct = onAddProduct,
                    onRemoveFromCart = onRemoveFromCart,
                    onOpenPayment = {
                        onToggleCart()
                        onOpenPayment()
                    },
                    onClose = onToggleCart,
                )
            }
        }
    }
}

@Composable
private fun PosCatalogHeader(
    state: PosUiState,
    categories: List<String>,
    cartQty: Int,
    compactView: Boolean,
    onKeywordChanged: (String) -> Unit,
    onCategorySelected: (String) -> Unit,
    onToggleCart: () -> Unit,
    onToggleViewMode: () -> Unit,
) {
    Card(
        shape = androidx.compose.foundation.shape.RoundedCornerShape(bottomStart = 18.dp, bottomEnd = 18.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
            ) {
                Text(text = "Kasir", fontWeight = FontWeight.Bold, fontSize = 20.sp)

                Row(
                    verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    HeaderIconButton(icon = Icons.Default.QrCodeScanner)
                    Box {
                        HeaderIconButton(icon = Icons.Default.ShoppingCart, onClick = onToggleCart)
                        if (cartQty > 0) {
                            Box(
                                modifier = Modifier
                                    .size(16.dp)
                                    .clip(androidx.compose.foundation.shape.CircleShape)
                                    .background(PosPrimary)
                                    .align(androidx.compose.ui.Alignment.TopEnd),
                            ) {
                                Text(
                                    text = cartQty.toString(),
                                    color = Color.White,
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.Bold,
                                    modifier = Modifier.align(androidx.compose.ui.Alignment.Center),
                                )
                            }
                        }
                    }
                }
            }

            OutlinedTextField(
                value = state.searchKeyword,
                onValueChange = onKeywordChanged,
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = Color(0xFF94A3B8)) },
                placeholder = { Text("Cari produk...") },
                singleLine = true,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp),
                shape = MaterialTheme.shapes.large,
            )

            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                item {
                    Card(
                        modifier = Modifier.clickable(onClick = onToggleViewMode),
                        colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)),
                        border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0)),
                        shape = MaterialTheme.shapes.extraLarge,
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
                            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(5.dp),
                        ) {
                            Icon(
                                imageVector = if (compactView) Icons.Default.ViewAgenda else Icons.Default.ViewModule,
                                contentDescription = null,
                                tint = PosPrimary,
                                modifier = Modifier.size(14.dp),
                            )
                            Text(
                                text = if (compactView) "Mode Nama" else "Mode Gambar",
                                color = PosPrimary,
                                fontWeight = FontWeight.Bold,
                                fontSize = 11.sp,
                            )
                        }
                    }
                }
                items(count = categories.size) { categoryIndex ->
                    val category = categories[categoryIndex]
                    val isSelected = category == state.selectedCategory
                    Card(
                        modifier = Modifier.clickable { onCategorySelected(category) },
                        colors = CardDefaults.cardColors(
                            containerColor = if (isSelected) PosPrimary else Color.White,
                        ),
                        border = androidx.compose.foundation.BorderStroke(1.dp, if (isSelected) PosPrimary else Color(0xFFE2E8F0)),
                        shape = MaterialTheme.shapes.extraLarge,
                    ) {
                        Text(
                            text = category,
                            color = if (isSelected) Color.White else Color(0xFF64748B),
                            fontSize = 12.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.SemiBold,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 7.dp),
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun HeaderIconButton(icon: ImageVector, onClick: () -> Unit = {}) {
    Box(
        modifier = Modifier
            .size(42.dp)
            .clip(androidx.compose.foundation.shape.CircleShape)
            .clickable(onClick = onClick)
            .background(Color(0xFFF1F5F9)),
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Color(0xFF64748B),
            modifier = Modifier.align(androidx.compose.ui.Alignment.Center),
        )
    }
}

@Composable
private fun CartBottomSheet(
    state: PosUiState,
    cartTotal: Long,
    canUseCustomerFeature: Boolean,
    isWriteLocked: Boolean,
    onCustomerSelected: (String?) -> Unit,
    onAddProduct: (String) -> Unit,
    onRemoveFromCart: (String) -> Unit,
    onOpenPayment: () -> Unit,
    onClose: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 20.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Box(
            modifier = Modifier
                .padding(top = 10.dp)
                .align(androidx.compose.ui.Alignment.CenterHorizontally)
                .height(5.dp)
                .width(56.dp)
                .clip(androidx.compose.foundation.shape.CircleShape)
                .background(Color(0xFFCBD5E1)),
        )

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                HeaderIconButton(icon = Icons.Default.Close, onClick = onClose)
                Text(text = "Keranjang (${state.cartItems.sumOf { it.qty.toInt() }})", fontWeight = FontWeight.Bold, fontSize = 24.sp)
            }
            HeaderIconButton(icon = Icons.Default.MoreHoriz)
        }

        LazyColumn(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(count = state.cartItems.size) { itemIndex ->
                val item = state.cartItems[itemIndex]
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    Box(
                        modifier = Modifier
                            .size(62.dp)
                            .clip(MaterialTheme.shapes.medium)
                            .background(Color(0xFFF1F5F9)),
                    ) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ReceiptLong,
                            contentDescription = null,
                            tint = PosPrimary,
                            modifier = Modifier
                                .size(26.dp)
                                .align(androidx.compose.ui.Alignment.Center),
                        )
                    }

                    Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text(text = item.name, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                Text(text = rupiah(item.unitPrice), color = PosPrimary, fontWeight = FontWeight.Bold)
                            }
                            Box(modifier = Modifier.clickable { onRemoveFromCart(item.productLocalId) }) {
                                Icon(Icons.Default.Delete, contentDescription = null, tint = Color(0xFFFB7185))
                            }
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End,
                            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                        ) {
                            Row(
                                modifier = Modifier
                                    .clip(MaterialTheme.shapes.extraLarge)
                                    .background(Color(0xFFF8FAFC))
                                    .padding(3.dp),
                                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(34.dp)
                                        .clip(androidx.compose.foundation.shape.CircleShape)
                                        .background(Color.White)
                                        .clickable { onRemoveFromCart(item.productLocalId) },
                                ) {
                                    Icon(Icons.Default.Remove, contentDescription = null, tint = PosPrimary, modifier = Modifier.align(androidx.compose.ui.Alignment.Center))
                                }
                                Text(
                                    text = item.qty.toInt().toString(),
                                    fontWeight = FontWeight.Bold,
                                    color = Color(0xFF334155),
                                    modifier = Modifier.padding(horizontal = 10.dp),
                                )
                                Box(
                                    modifier = Modifier
                                        .size(34.dp)
                                        .clip(androidx.compose.foundation.shape.CircleShape)
                                        .background(PosPrimary)
                                        .clickable { onAddProduct(item.productLocalId) },
                                ) {
                                    Icon(Icons.Default.Add, contentDescription = null, tint = Color.White, modifier = Modifier.align(androidx.compose.ui.Alignment.Center))
                                }
                            }
                        }
                    }
                }
            }
        }

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .clickable(enabled = canUseCustomerFeature) { onCustomerSelected(null) },
            colors = CardDefaults.cardColors(
                containerColor = if (canUseCustomerFeature) Color(0xFFF8FAFC) else Color(0xFFF1F5F9),
            ),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 14.dp, vertical = 13.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                    Icon(
                        Icons.Default.PersonAdd,
                        contentDescription = null,
                        tint = if (canUseCustomerFeature) PosPrimary else Color(0xFF94A3B8),
                    )
                    Text(
                        text = if (canUseCustomerFeature) "Tambah Pelanggan" else "Tambah Pelanggan (Paket M/P)",
                        color = if (canUseCustomerFeature) Color(0xFF475569) else Color(0xFF94A3B8),
                        fontWeight = FontWeight.SemiBold,
                    )
                }
                Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFF94A3B8))
            }
        }

        HorizontalDivider(color = Color(0xFFE2E8F0))

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(text = "Subtotal", color = Color(0xFF64748B))
            Text(text = rupiah(cartTotal), fontWeight = FontWeight.Medium)
        }

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(text = "Total Tagihan", fontWeight = FontWeight.SemiBold)
            Text(text = rupiah(cartTotal), fontWeight = FontWeight.Black, fontSize = 28.sp, color = PosPrimary)
        }

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 12.dp)
                .clickable(enabled = !state.isSaving && state.cartItems.isNotEmpty() && !isWriteLocked) { onOpenPayment() },
            colors = CardDefaults.cardColors(containerColor = if (isWriteLocked) Color(0xFFCBD5E1) else PosPrimary),
            shape = MaterialTheme.shapes.large,
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 14.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
            ) {
                Text(
                    text = if (isWriteLocked) "Periode Terkunci" else if (state.isSaving) "Menyimpan..." else "Bayar ${rupiah(cartTotal)}",
                    color = if (isWriteLocked) Color(0xFF64748B) else Color.White,
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp,
                )
            }
        }
    }
}

@Composable
private fun StyledPaymentScreen(
    state: PosUiState,
    cartTotal: Long,
    isWriteLocked: Boolean,
    onBack: () -> Unit,
    onPaymentMethodSelected: (String) -> Unit,
    onConfirmPayment: (Long, Long) -> Unit,
) {
    val paymentMethods = listOf(
        "cash" to "Tunai" to Icons.Default.Payments,
        "qris" to "QRIS" to Icons.Default.QrCode2,
        "transfer" to "Transfer Bank" to Icons.Default.AccountBalance,
        "debit" to "Kartu Debit" to Icons.Default.AccountBalanceWallet,
    )
    var paidInput by remember(cartTotal) {
        mutableStateOf(if (cartTotal == 0L) "" else (cartTotal + 5000).toString())
    }
    var discountInput by remember(cartTotal) {
        mutableStateOf("0")
    }
    val discountAmount = (discountInput.toLongOrNull() ?: 0L).coerceAtLeast(0L).coerceAtMost(cartTotal)
    val payableTotal = (cartTotal - discountAmount).coerceAtLeast(0L)
    val paid = paidInput.toLongOrNull() ?: 0L
    val change = (paid - payableTotal).coerceAtLeast(0L)
    val quickAmounts = listOf(
        "50rb" to 50_000L,
        "100rb" to 100_000L,
        "Uang Pas" to payableTotal,
    )
    val isUnderpaid = paid in 1 until payableTotal
    val canConfirm = !state.isSaving && !isWriteLocked && paid >= payableTotal

    Box(
        modifier = Modifier
            .fillMaxSize()
            .testTag("payment_screen"),
    ) {
        val scrollState = rememberScrollState()

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
                .padding(bottom = 112.dp),
        ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
        ) {
            HeaderIconButton(icon = Icons.AutoMirrored.Filled.ArrowBack, onClick = onBack)
            Spacer(modifier = Modifier.width(6.dp))
            Text(text = "Pembayaran", fontWeight = FontWeight.Bold, fontSize = 20.sp)
        }

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp)
                .animateContentSize(),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            if (isWriteLocked) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF7ED)),
                    shape = MaterialTheme.shapes.medium,
                ) {
                    Text(
                        text = "Lock period aktif. Konfirmasi pembayaran dinonaktifkan.",
                        color = Color(0xFF9A3412),
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 12.sp,
                        modifier = Modifier.padding(10.dp),
                    )
                }
            }

            state.errorMessage?.takeIf { it.isNotBlank() }?.let { err ->
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2)),
                    shape = MaterialTheme.shapes.medium,
                ) {
                    Text(
                        text = err,
                        color = Color(0xFFB91C1C),
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 12.sp,
                        modifier = Modifier.padding(10.dp),
                    )
                }
            }

            Column(
                modifier = Modifier.fillMaxWidth(),
                horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally,
            ) {
                Text(text = "Total Tagihan", color = Color(0xFF64748B), fontWeight = FontWeight.Medium)
                Text(text = rupiah(payableTotal), fontSize = 34.sp, fontWeight = FontWeight.ExtraBold)
            }

            OutlinedTextField(
                value = discountInput,
                onValueChange = { input ->
                    discountInput = input.filter { it.isDigit() }.take(12).ifBlank { "0" }
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .testTag("discount_amount_input"),
                prefix = { Text("Rp ") },
                label = { Text("Diskon nominal") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                shape = MaterialTheme.shapes.large,
            )

            Card(colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 14.dp, vertical = 10.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(text = "Subtotal", color = Color(0xFF64748B))
                        Text(text = rupiah(cartTotal), fontWeight = FontWeight.SemiBold)
                    }
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(text = "Diskon", color = Color(0xFF64748B))
                        Text(text = "- ${rupiah(discountAmount)}", fontWeight = FontWeight.SemiBold)
                    }
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(text = "Total Bayar", fontWeight = FontWeight.Bold)
                        Text(text = rupiah(payableTotal), fontWeight = FontWeight.ExtraBold, color = PosPrimary)
                    }
                }
            }

            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                paymentMethods.chunked(2).forEach { rowItems ->
                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        rowItems.forEach { method ->
                            val code = method.first.first
                            val label = method.first.second
                            val icon = method.second
                            val isSelected = state.paymentMethod == code

                            Card(
                                modifier = Modifier
                                    .weight(1f)
                                    .clickable { onPaymentMethodSelected(code) },
                                colors = CardDefaults.cardColors(
                                    containerColor = if (isSelected) PosPrimary.copy(alpha = 0.12f) else PosCard,
                                ),
                                border = androidx.compose.foundation.BorderStroke(
                                    if (isSelected) 2.dp else 1.dp,
                                    if (isSelected) PosPrimary else Color(0xFFE2E8F0),
                                ),
                            ) {
                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(vertical = 14.dp),
                                    horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.spacedBy(6.dp),
                                ) {
                                    Icon(icon, contentDescription = null, tint = if (isSelected) PosPrimary else Color(0xFF94A3B8))
                                    Text(text = label, color = if (isSelected) PosPrimary else Color(0xFF475569), fontWeight = if (isSelected) FontWeight.Bold else FontWeight.SemiBold)
                                }
                            }
                        }
                        if (rowItems.size == 1) {
                            Spacer(modifier = Modifier.weight(1f))
                        }
                    }
                }
            }

            OutlinedTextField(
                value = paidInput,
                onValueChange = { input ->
                    paidInput = input.filter { it.isDigit() }.take(12)
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .testTag("paid_amount_input"),
                prefix = { Text("Rp ") },
                placeholder = { Text("Masukkan nominal dibayar") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                shape = MaterialTheme.shapes.large,
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                quickAmounts.forEach { amount ->
                    val label = amount.first
                    val amountValue = amount.second
                    AmountChip(
                        label = label,
                        active = paid == amountValue,
                        onClick = {
                            paidInput = amountValue.toString()
                        },
                    )
                }
            }

            if (isUnderpaid) {
                Text(
                    text = "Nominal dibayar kurang dari total tagihan.",
                    color = Color(0xFFDC2626),
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 12.sp,
                )
            }

            Card(colors = CardDefaults.cardColors(containerColor = PosPrimary), shape = MaterialTheme.shapes.large) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(text = "Kembalian", color = Color.White)
                    Text(text = rupiah(change), color = Color.White, fontWeight = FontWeight.Bold, fontSize = 24.sp)
                }
            }

            Card(
                colors = CardDefaults.cardColors(containerColor = Color.White),
                border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0)),
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                ) {
                    Row(verticalAlignment = androidx.compose.ui.Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Icon(Icons.Default.Print, contentDescription = null, tint = Color(0xFF94A3B8))
                        Text(text = "Cetak Struk", fontWeight = FontWeight.SemiBold)
                    }
                    Box(
                        modifier = Modifier
                            .width(42.dp)
                            .height(24.dp)
                            .clip(androidx.compose.foundation.shape.CircleShape)
                            .background(PosPrimary),
                    ) {
                        Box(
                            modifier = Modifier
                                .padding(2.dp)
                                .size(20.dp)
                                .clip(androidx.compose.foundation.shape.CircleShape)
                                .background(Color.White)
                                .align(androidx.compose.ui.Alignment.CenterEnd),
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(18.dp))
        }

        }

        Card(
            modifier = Modifier
                .align(androidx.compose.ui.Alignment.BottomCenter)
                .fillMaxWidth()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = 14.dp, vertical = 10.dp)
                .testTag("confirm_payment_button")
                .clickable(enabled = canConfirm) { onConfirmPayment(paid, discountAmount) },
            colors = CardDefaults.cardColors(containerColor = if (isWriteLocked) Color(0xFFCBD5E1) else PosPrimary),
            shape = MaterialTheme.shapes.large,
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 14.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
            ) {
                Text(
                    text = if (isWriteLocked) "Terkunci oleh Lock Period" else if (state.isSaving) "Menyimpan..." else if (isUnderpaid) "Nominal Belum Cukup" else "Konfirmasi Pembayaran",
                    color = if (isWriteLocked) Color(0xFF64748B) else Color.White,
                    fontWeight = FontWeight.Bold,
                    fontSize = 17.sp,
                )
                Spacer(modifier = Modifier.width(8.dp))
                Icon(
                    Icons.Default.CheckCircle,
                    contentDescription = null,
                    tint = if (isWriteLocked) Color(0xFF94A3B8) else Color.White,
                )
            }
        }
    }
}

@Composable
private fun AmountChip(label: String, active: Boolean = false, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .widthIn(min = 92.dp)
            .clickable { onClick() },
        colors = CardDefaults.cardColors(containerColor = if (active) PosPrimary.copy(alpha = 0.12f) else Color.White),
        border = androidx.compose.foundation.BorderStroke(1.dp, if (active) PosPrimary.copy(alpha = 0.5f) else Color(0xFFE2E8F0)),
    ) {
        Text(
            text = label,
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 12.dp),
            color = if (active) PosPrimary else Color(0xFF64748B),
            fontWeight = if (active) FontWeight.Bold else FontWeight.SemiBold,
            fontSize = 13.sp,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        )
    }
}

@Composable
private fun PosSuccessScreen(
    receipt: SaleReceiptUi,
    onNewTransaction: () -> Unit,
    onSeeDetail: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .navigationBarsPadding(),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
        ) {
            HeaderIconButton(icon = Icons.Default.Close, onClick = onNewTransaction)
            Spacer(modifier = Modifier.width(6.dp))
            Text(text = "Sukses Transaksi", fontWeight = FontWeight.Bold, fontSize = 20.sp)
        }

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 18.dp)
                .padding(bottom = 18.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
            horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally,
        ) {
            Box(
                modifier = Modifier
                    .size(86.dp)
                    .clip(androidx.compose.foundation.shape.CircleShape)
                    .background(PosPrimary.copy(alpha = 0.12f)),
            ) {
                Icon(Icons.Default.CheckCircle, contentDescription = null, tint = PosPrimary, modifier = Modifier.size(56.dp).align(androidx.compose.ui.Alignment.Center))
            }

            Spacer(modifier = Modifier.height(12.dp))
            Text(text = "Transaksi Berhasil!", fontWeight = FontWeight.Bold, fontSize = 29.sp)
            Text(text = "ID: #${receipt.receiptNo}", color = Color(0xFF94A3B8), fontWeight = FontWeight.Medium)

            Spacer(modifier = Modifier.height(20.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = PosCard),
                border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0)),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Total Tagihan", color = Color(0xFF64748B))
                        Text(rupiah(receipt.totalAmount), fontWeight = FontWeight.Bold)
                    }
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Dibayar (${receipt.paymentMethodLabel})", color = Color(0xFF64748B))
                        Text(rupiah(receipt.paidAmount), fontWeight = FontWeight.SemiBold)
                    }
                    HorizontalDivider(color = Color(0xFFE2E8F0))
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Kembalian", color = PosPrimary, fontWeight = FontWeight.Bold)
                        Text(rupiah(receipt.changeAmount), color = PosPrimary, fontWeight = FontWeight.Bold, fontSize = 24.sp)
                    }
                }
            }

            Spacer(modifier = Modifier.height(14.dp))
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { onNewTransaction() },
                colors = CardDefaults.cardColors(containerColor = PosPrimary),
            ) {
                Text(
                    text = "Transaksi Baru",
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                    modifier = Modifier.fillMaxWidth().padding(vertical = 14.dp),
                )
            }

            Spacer(modifier = Modifier.height(8.dp))
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .testTag("receipt_detail_button")
                    .clickable { onSeeDetail() },
                colors = CardDefaults.cardColors(containerColor = Color.White),
                border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0)),
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 14.dp),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                ) {
                    Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null, tint = Color(0xFF475569))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(text = "Cetak / Kirim Struk", fontWeight = FontWeight.Bold, color = Color(0xFF475569))
                }
            }

            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = "Lihat Detail Transaksi",
                color = PosPrimary,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.clickable { onSeeDetail() },
            )
        }
    }
}

@Composable
private fun PosHistoryScreen(history: List<HistoryTransactionUi>, onBack: () -> Unit) {
    Column(modifier = Modifier.fillMaxSize()) {
        Card(
            colors = CardDefaults.cardColors(containerColor = Color.White),
            shape = androidx.compose.foundation.shape.RoundedCornerShape(bottomStart = 16.dp, bottomEnd = 16.dp),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
            ) {
                Row(verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                    HeaderIconButton(icon = Icons.AutoMirrored.Filled.ArrowBack, onClick = onBack)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(text = "Riwayat", fontWeight = FontWeight.Bold, fontSize = 21.sp)
                }
                HeaderIconButton(icon = Icons.Default.Search)
            }
        }

        LazyColumn(
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f),
            contentPadding = PaddingValues(start = 14.dp, top = 14.dp, end = 14.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            if (history.isEmpty()) {
                item {
                    Card(colors = CardDefaults.cardColors(containerColor = Color.White)) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(18.dp),
                            horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null, tint = Color(0xFF94A3B8))
                            Text(
                                text = "Belum ada transaksi",
                                color = Color(0xFF334155),
                                fontWeight = FontWeight.Bold,
                            )
                            Text(
                                text = "Lakukan transaksi dulu di halaman kasir.",
                                color = Color(0xFF64748B),
                                fontSize = 12.sp,
                            )
                        }
                    }
                }
            } else {
                items(count = history.size) { historyIndex ->
                    val trx = history[historyIndex]
                    val pillColor = if (trx.paymentMethod == "QRIS") PosAccentBlue else PosPrimary
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0)),
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                        ) {
                            Row(
                                modifier = Modifier.weight(1f),
                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                verticalAlignment = androidx.compose.ui.Alignment.CenterVertically,
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(42.dp)
                                        .clip(androidx.compose.foundation.shape.CircleShape)
                                        .background(pillColor.copy(alpha = 0.15f)),
                                ) {
                                    Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null, tint = pillColor, modifier = Modifier.align(androidx.compose.ui.Alignment.Center))
                                }
                                Column {
                                    Row(verticalAlignment = androidx.compose.ui.Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        Text(
                                            text = "#${trx.receiptNo}",
                                            fontWeight = FontWeight.Bold,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis,
                                        )
                                        Box(
                                            modifier = Modifier
                                                .clip(MaterialTheme.shapes.extraLarge)
                                                .background(pillColor.copy(alpha = 0.15f))
                                                .padding(horizontal = 8.dp, vertical = 3.dp),
                                        ) {
                                            Text(
                                                text = trx.paymentMethod,
                                                color = pillColor,
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                maxLines = 1,
                                                overflow = TextOverflow.Ellipsis,
                                            )
                                        }
                                    }
                                    Text(
                                        text = "${timeOf(trx.timestamp)} • ${trx.status}",
                                        fontSize = 12.sp,
                                        color = if (trx.status == "Sukses") Color(0xFF10B981) else Color(0xFFFB7185),
                                        fontWeight = FontWeight.Medium,
                                    )
                                }
                            }

                            Column(
                                modifier = Modifier.widthIn(min = 92.dp),
                                horizontalAlignment = androidx.compose.ui.Alignment.End,
                            ) {
                                Text(
                                    text = rupiah(trx.total),
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 18.sp,
                                    maxLines = 1,
                                    overflow = TextOverflow.Clip,
                                )
                                Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFFCBD5E1))
                            }
                        }
                    }
                }
            }
        }
    }
}

private fun rupiah(value: Long): String {
    val format = NumberFormat.getCurrencyInstance(Locale("id", "ID"))
    format.maximumFractionDigits = 0
    return format.format(value)
}

private fun timeOf(ts: Long): String {
    return SimpleDateFormat("HH:mm", Locale("id", "ID")).format(Date(ts))
}

@Composable
private fun ReceiptCard(receipt: SaleReceiptUi) {
    Card(modifier = Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Text(text = "Receipt Transaksi Terakhir", style = MaterialTheme.typography.titleMedium)
            Text(text = "Nomor: ${receipt.receiptNo}")
            Text(text = "Pelanggan: ${receipt.customerName}")
            Text(text = "Metode Bayar: ${receipt.paymentMethodLabel}")
            Text(text = "Status Sync: ${receipt.syncStatusLabel}")
            Text(text = "Item:")
            receipt.items.forEach { item ->
                Text(text = "- ${item.name} (${item.qty}) = ${rupiah(item.lineTotal)}")
            }
            Text(text = "Total: ${rupiah(receipt.totalAmount)}", style = MaterialTheme.typography.titleMedium)
        }
    }
}
