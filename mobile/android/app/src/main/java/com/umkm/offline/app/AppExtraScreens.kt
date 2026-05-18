package com.umkm.offline.app

import android.annotation.SuppressLint
import android.app.Activity
import android.Manifest
import android.bluetooth.BluetoothManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.pdf.PdfDocument
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.CancellationSignal
import android.os.Handler
import android.os.Looper
import android.os.ParcelFileDescriptor
import android.print.PageRange
import android.print.PrintAttributes
import android.print.PrintDocumentAdapter
import android.print.PrintDocumentInfo
import android.print.PrintManager
import android.provider.Settings
import android.util.Log
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.HelpOutline
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.AddAPhoto
import androidx.compose.material.icons.filled.BakeryDining
import androidx.compose.material.icons.filled.CalendarToday
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Coffee
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.FilterList
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Percent
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Print
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.Save
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Sell
import androidx.compose.material.icons.filled.Stars
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.umkm.offline.core.common.ConnectivityState
import com.umkm.offline.core.database.dao.SaleItemWithProductNameRow
import com.umkm.offline.core.database.entity.PaymentEntity
import com.umkm.offline.app.purchase.PurchaseContract
import com.umkm.offline.app.purchase.SupplierSummary
import com.umkm.offline.feature.pos.presentation.PosProductItemUi
import com.umkm.offline.feature.pos.presentation.ReceiptItemUi
import com.umkm.offline.feature.pos.presentation.SaleReceiptUi
import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.math.roundToInt

private val UiPrimary = Color(0xFF7BAE9E)
private val UiBackground = Color(0xFFF8FAF9)
private val UiCard = Color.White

@Composable
fun ProductsScreen(
    products: List<PosProductItemUi>,
    recipeMaterialCandidates: List<RecipeMaterialCandidateUi>,
    canUseMaterials: Boolean,
    existingCategories: List<String>,
    recipeCountByProduct: Map<String, Int>,
    recipeLinesByProduct: Map<String, List<RecipeSeedLineUi>>,
    loadFifoLayers: suspend (String) -> List<ProductFifoLayerUi> = { emptyList() },
    onRequestExit: (() -> Unit)? = null,
    onCreateProduct: (NewProductDraft) -> Unit,
    onSaveRecipe: (String, List<RecipeSeedLineUi>) -> Unit,
) {
    var mode by remember { mutableStateOf(ProductMode.LIST) }
    var keyword by remember { mutableStateOf("") }
    var selected by remember { mutableStateOf<PosProductItemUi?>(null) }
    var editingSeed by remember { mutableStateOf<ProductEditSeedUi?>(null) }
    var stockCounter by remember { mutableStateOf(0) }
    var selectedSection by remember { mutableStateOf(ProductSection.PRODUCTS) }
    BackHandler(enabled = mode != ProductMode.LIST || onRequestExit != null) {
        if (mode != ProductMode.LIST) {
            mode = ProductMode.LIST
        } else {
            onRequestExit?.invoke()
        }
    }

    Surface(modifier = Modifier.fillMaxSize(), color = UiBackground) {
        when (mode) {
            ProductMode.LIST -> {
                val shown = products.filter {
                    val q = keyword.trim()
                    q.isBlank() || it.name.contains(q, ignoreCase = true) || it.categoryName.contains(q, ignoreCase = true)
                }
                val sectionItems = shown.filter { product ->
                    when (selectedSection) {
                        ProductSection.PRODUCTS -> true
                        ProductSection.MATERIALS -> isMaterialProduct(product)
                        ProductSection.RECIPES -> isRecipeProduct(product)
                    }
                }
                Column(modifier = Modifier.fillMaxSize()) {
                    ProductListHeader(
                        onAdd = {
                            editingSeed = null
                            stockCounter = 0
                            mode = ProductMode.ADD
                        },
                        onSearch = { keyword = it },
                    )

                    Row(
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        ProductSectionChip(
                            text = "Produk",
                            active = selectedSection == ProductSection.PRODUCTS,
                            onClick = { selectedSection = ProductSection.PRODUCTS },
                        )
                        ProductSectionChip(
                            text = if (canUseMaterials) "Bahan Baku" else "Bahan Baku (M/P)",
                            active = selectedSection == ProductSection.MATERIALS,
                            onClick = { selectedSection = ProductSection.MATERIALS },
                        )
                        ProductSectionChip(
                            text = if (canUseMaterials) "Resep" else "Resep (M/P)",
                            active = selectedSection == ProductSection.RECIPES,
                            onClick = { selectedSection = ProductSection.RECIPES },
                        )
                    }

                    if (selectedSection == ProductSection.MATERIALS && !canUseMaterials) {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF7ED)),
                        ) {
                            Text(
                                text = "Manajemen bahan baku tersedia mulai Paket Medium.",
                                color = Color(0xFF9A3412),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                modifier = Modifier.padding(12.dp),
                            )
                        }
                    }

                    if (selectedSection == ProductSection.RECIPES) {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(containerColor = Color(0xFFEFF6FF)),
                        ) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text(
                                    text = "Manajemen resep tersedia terbatas di APK.",
                                    color = Color(0xFF1D4ED8),
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold,
                                )
                                Text(
                                    text = "Bisa tambah produk mode Resep dan edit komponen dasar. Setup lanjutan tetap disarankan lewat web backoffice.",
                                    color = Color(0xFF334155),
                                    fontSize = 11.sp,
                                )
                            }
                        }
                    }

                    Text(
                        text = when (selectedSection) {
                            ProductSection.PRODUCTS -> "Semua Produk"
                            ProductSection.MATERIALS -> "Bahan Baku"
                            ProductSection.RECIPES -> "Resep Produk"
                        },
                        color = Color(0xFF94A3B8),
                        fontWeight = FontWeight.Bold,
                        fontSize = 11.sp,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )

                    LazyColumn(
                        modifier = Modifier.fillMaxWidth(),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 4.dp, end = 16.dp, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        itemsIndexed(sectionItems) { index, product ->
                            if (selectedSection == ProductSection.MATERIALS && !canUseMaterials) {
                                return@itemsIndexed
                            }
                            if (selectedSection == ProductSection.RECIPES) {
                                RecipeProductCard(
                                    product = product,
                                    recipeCount = recipeCountByProduct[product.localId] ?: 0,
                                    onClick = {
                                        selected = product
                                        mode = ProductMode.RECIPE_EDITOR
                                    },
                                )
                                return@itemsIndexed
                            }
                            val stock = stockFor(index)
                            ProductCard(
                                product = product,
                                stock = stock,
                                onClick = {
                                    selected = product
                                    mode = ProductMode.DETAIL
                                },
                            )
                        }
                    }
                }
            }

            ProductMode.ADD -> {
                AddProductScreen(
                    onBack = { mode = ProductMode.LIST },
                    stockCounter = stockCounter,
                    onIncrease = { stockCounter += 1 },
                    onDecrease = { stockCounter = (stockCounter - 1).coerceAtLeast(0) },
                    existingCategories = existingCategories,
                    recipeMaterialCandidates = recipeMaterialCandidates,
                    initialSeed = editingSeed,
                    isEditMode = editingSeed != null,
                    allowStockAdjustment = editingSeed == null,
                    onSave = { draft ->
                        onCreateProduct(draft)
                        editingSeed = null
                        mode = ProductMode.LIST
                    },
                )
            }

            ProductMode.DETAIL -> {
                selected?.let { product ->
                    ProductDetailScreen(
                        product = product,
                        stock = product.stockOnHand.roundToInt(),
                        recipeLines = recipeLinesByProduct[product.localId].orEmpty(),
                        recipeMaterialCandidates = recipeMaterialCandidates,
                        loadFifoLayers = loadFifoLayers,
                        onBack = { mode = ProductMode.LIST },
                        onEdit = {
                            val normalizedMode = normalizeProductMode(product.productMode)
                            val normalizedType = normalizeProductItemType(product.itemType, product.productMode)
                            val seedMode = when {
                                normalizedMode == "bom" -> NewProductMode.RECIPE
                                normalizedMode == "variant" -> NewProductMode.VARIANT
                                normalizedType == "raw_material" || normalizedMode in setOf("add_on", "addon", "add-on") -> NewProductMode.ADD_ON
                                else -> NewProductMode.DEFAULT
                            }
                            editingSeed = ProductEditSeedUi(
                                localId = product.localId,
                                name = product.name,
                                category = product.categoryName,
                                sellPrice = product.price,
                                costPrice = 0L,
                                openingStock = product.stockOnHand.roundToInt().coerceAtLeast(0),
                                mode = seedMode,
                                recipeLines = recipeLinesByProduct[product.localId].orEmpty(),
                            )
                            stockCounter = product.stockOnHand.roundToInt().coerceAtLeast(0)
                            mode = ProductMode.ADD
                        },
                    )
                } ?: run {
                    mode = ProductMode.LIST
                }
            }

            ProductMode.RECIPE_EDITOR -> {
                selected?.let { product ->
                    RecipeEditorScreen(
                        productName = product.name,
                        initialLines = recipeLinesByProduct[product.localId].orEmpty(),
                        onBack = { mode = ProductMode.LIST },
                        onSave = { lines ->
                            onSaveRecipe(product.localId, lines)
                            mode = ProductMode.LIST
                        },
                    )
                } ?: run {
                    mode = ProductMode.LIST
                }
            }
        }
    }
}

@Composable
private fun ProductListHeader(onAdd: () -> Unit, onSearch: (String) -> Unit) {
    var localKeyword by remember { mutableStateOf("") }

    Column(modifier = Modifier.fillMaxWidth()) {
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(bottomStart = 18.dp, bottomEnd = 18.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 14.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = "Produk", fontSize = 22.sp, fontWeight = FontWeight.Bold)
                    Text(text = "Kelola produk dan stok", color = Color(0xFF94A3B8), fontWeight = FontWeight.Medium, fontSize = 11.sp)
                }

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    CircleIconButton(icon = Icons.Default.Search)
                    CircleIconButton(icon = Icons.Default.FilterList)
                    CircleIconButton(
                        icon = Icons.Default.Add,
                        tint = Color.White,
                        background = UiPrimary,
                        modifier = Modifier.testTag("add_product_button"),
                        onClick = onAdd,
                    )
                }
            }
        }

        OutlinedTextField(
            value = localKeyword,
            onValueChange = {
                localKeyword = it
                onSearch(it)
            },
            leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
            placeholder = { Text("Cari produk atau scan barcode") },
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            singleLine = true,
            shape = RoundedCornerShape(16.dp),
        )
    }
}

@Composable
private fun ProductCard(product: PosProductItemUi, stock: Int, onClick: () -> Unit) {
    val statusColor = when {
        stock <= 2 -> Color(0xFFF59E0B)
        stock <= 5 -> Color(0xFFFB7185)
        else -> Color(0xFF10B981)
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = UiCard),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(60.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(
                        Brush.linearGradient(
                            colors = listOf(Color(0xFFE2E8F0), Color(0xFFCBD5E1)),
                        ),
                    ),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Default.Image, contentDescription = null, tint = Color(0xFF64748B))
            }

            Column(modifier = Modifier.weight(1f)) {
                Text(text = product.name, maxLines = 1, overflow = TextOverflow.Ellipsis, fontWeight = FontWeight.Bold)
                Text(text = product.categoryName, color = Color(0xFF94A3B8), fontSize = 11.sp, fontWeight = FontWeight.Medium)
                Spacer(modifier = Modifier.height(6.dp))
                Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Text(text = rupiah(product.price), color = UiPrimary, fontWeight = FontWeight.Bold)
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(statusColor.copy(alpha = 0.12f))
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box(modifier = Modifier.size(6.dp).clip(CircleShape).background(statusColor))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(text = "Stok: $stock", color = statusColor, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

@Composable
private fun RecipeProductCard(product: PosProductItemUi, recipeCount: Int, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = UiCard),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(text = product.name, maxLines = 1, overflow = TextOverflow.Ellipsis, fontWeight = FontWeight.Bold)
                Text(text = product.categoryName, color = Color(0xFF94A3B8), fontSize = 11.sp, fontWeight = FontWeight.Medium)
                Text(
                    text = if (recipeCount > 0) "$recipeCount bahan terdaftar" else "Belum ada resep",
                    color = if (recipeCount > 0) UiPrimary else Color(0xFF64748B),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
            Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFF94A3B8))
        }
    }
}

@Composable
private fun RecipeEditorScreen(
    productName: String,
    initialLines: List<RecipeSeedLineUi>,
    onBack: () -> Unit,
    onSave: (List<RecipeSeedLineUi>) -> Unit,
) {
    var validationMessage by remember { mutableStateOf<String?>(null) }
    val editorLines = remember(initialLines) {
        mutableStateListOf<RecipeEditorLineUi>().apply {
            if (initialLines.isEmpty()) {
                add(RecipeEditorLineUi())
            } else {
                addAll(
                    initialLines.map { line ->
                        RecipeEditorLineUi(
                            materialProductLocalId = line.materialProductLocalId,
                            materialName = line.materialName,
                            qty = if (line.qty > 0.0) String.format(Locale.US, "%.4f", line.qty).trimEnd('0').trimEnd('.') else "",
                            unit = line.unit,
                        )
                    },
                )
            }
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(UiBackground)
            .navigationBarsPadding(),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                Column {
                    Text(text = "Resep Produk", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    Text(text = productName, color = Color(0xFF64748B), fontSize = 12.sp)
                }
            }
            Button(
                onClick = {
                    val normalized = editorLines.mapNotNull { row ->
                        val name = row.materialName.trim()
                        val qty = row.qty.replace(',', '.').toDoubleOrNull()
                        val unit = row.unit.trim()
                        if (name.isBlank() || qty == null || qty <= 0.0 || unit.isBlank()) {
                            null
                        } else {
                            RecipeSeedLineUi(
                                materialProductLocalId = row.materialProductLocalId,
                                materialName = name,
                                qty = qty,
                                unit = unit,
                            )
                        }
                    }
                    if (normalized.isEmpty()) {
                        validationMessage = "Minimal satu bahan valid harus diisi (nama, qty, unit)."
                        return@Button
                    }
                    validationMessage = null
                    onSave(normalized)
                },
                shape = RoundedCornerShape(12.dp),
            ) {
                Icon(Icons.Default.Save, contentDescription = null)
                Spacer(modifier = Modifier.width(6.dp))
                Text("Simpan", fontWeight = FontWeight.Bold)
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 12.dp, end = 16.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                validationMessage?.let { message ->
                    Card(
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2)),
                    ) {
                        Text(
                            text = message,
                            color = Color(0xFFB91C1C),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.padding(10.dp),
                        )
                    }
                }
            }

            itemsIndexed(editorLines) { index, line ->
                Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Bahan #${index + 1}", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color(0xFF64748B))
                        OutlinedTextField(
                            value = line.materialName,
                            onValueChange = { value -> editorLines[index] = line.copy(materialName = value) },
                            label = { Text("Nama Bahan") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth(),
                        )
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            OutlinedTextField(
                                value = line.qty,
                                onValueChange = { value -> editorLines[index] = line.copy(qty = value) },
                                label = { Text("Qty") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                            )
                            OutlinedTextField(
                                value = line.unit,
                                onValueChange = { value -> editorLines[index] = line.copy(unit = value) },
                                label = { Text("Unit") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                            )
                        }

                        Row(horizontalArrangement = Arrangement.End, modifier = Modifier.fillMaxWidth()) {
                            Text(
                                text = "Hapus",
                                color = Color(0xFFDC2626),
                                fontWeight = FontWeight.SemiBold,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .clickable(enabled = editorLines.size > 1) {
                                        if (editorLines.size > 1) {
                                            editorLines.removeAt(index)
                                        }
                                    }
                                    .padding(horizontal = 8.dp, vertical = 4.dp),
                            )
                        }
                    }
                }
            }

            item {
                Button(
                    onClick = { editorLines.add(RecipeEditorLineUi()) },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                ) {
                    Icon(Icons.Default.Add, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Tambah Bahan", fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun AddProductScreen(
    onBack: () -> Unit,
    stockCounter: Int,
    onIncrease: () -> Unit,
    onDecrease: () -> Unit,
    existingCategories: List<String>,
    recipeMaterialCandidates: List<RecipeMaterialCandidateUi>,
    initialSeed: ProductEditSeedUi?,
    isEditMode: Boolean,
    allowStockAdjustment: Boolean,
    onSave: (NewProductDraft) -> Unit,
) {
    var name by remember(initialSeed?.localId) { mutableStateOf(initialSeed?.name.orEmpty()) }
    var category by remember(initialSeed?.localId) { mutableStateOf(initialSeed?.category.orEmpty()) }
    var sellPrice by remember(initialSeed?.localId) { mutableStateOf(initialSeed?.sellPrice?.takeIf { it > 0L }?.toString().orEmpty()) }
    var costPrice by remember(initialSeed?.localId) { mutableStateOf(initialSeed?.costPrice?.takeIf { it > 0L }?.toString().orEmpty()) }
    var validationError by remember { mutableStateOf<String?>(null) }
    var selectedMode by remember(initialSeed?.localId) { mutableStateOf(initialSeed?.mode ?: NewProductMode.DEFAULT) }
    val addOnNames = remember { mutableStateListOf("") }
    val variants = remember { mutableStateListOf(VariantDraftUi()) }
    val recipeLines = remember(initialSeed?.localId) {
        mutableStateListOf<RecipeEditorLineUi>().apply {
            if (initialSeed?.recipeLines.isNullOrEmpty()) {
                add(RecipeEditorLineUi())
            } else {
                addAll(
                    initialSeed?.recipeLines.orEmpty().map { line ->
                        RecipeEditorLineUi(
                            materialProductLocalId = line.materialProductLocalId,
                            materialName = line.materialName,
                            qty = if (line.qty > 0.0) String.format(Locale.US, "%.4f", line.qty).trimEnd('0').trimEnd('.') else "",
                            unit = line.unit,
                        )
                    },
                )
            }
        }
    }
    var pickerTargetRowIndex by remember { mutableStateOf<Int?>(null) }
    var showCategoryPicker by remember { mutableStateOf(false) }
    var categorySearchQuery by remember { mutableStateOf("") }
    var materialSearchQuery by remember { mutableStateOf("") }
    val candidateCostById = remember(recipeMaterialCandidates) {
        recipeMaterialCandidates.associateBy({ it.localId }, { it.unitCost.coerceAtLeast(0L) })
    }
    val filteredExistingCategories = remember(existingCategories, categorySearchQuery) {
        val keyword = categorySearchQuery.trim().lowercase()
        existingCategories
            .asSequence()
            .map { it.trim() }
            .filter { it.isNotBlank() }
            .distinctBy { it.lowercase() }
            .sortedBy { it.lowercase() }
            .filter { categoryName ->
                keyword.isBlank() || categoryName.lowercase().contains(keyword)
            }
            .toList()
    }
    val filteredRecipeMaterialCandidates = remember(recipeMaterialCandidates, materialSearchQuery) {
        val keyword = materialSearchQuery.trim().lowercase()
        recipeMaterialCandidates
            .asSequence()
            .filter { candidate ->
                keyword.isBlank() ||
                    candidate.name.lowercase().contains(keyword) ||
                    candidate.categoryName.lowercase().contains(keyword)
            }
            .toList()
    }

    LaunchedEffect(selectedMode) {
        Log.d("ProductModeUi", "selected_mode=${selectedMode.name.lowercase()}")
    }
    LaunchedEffect(recipeMaterialCandidates.size) {
        Log.d(
            "ProductModeUi",
            "recipe_candidates_loaded=${recipeMaterialCandidates.size}, filter=itemType(raw_material)|mode(add_on)",
        )
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(UiBackground)
            .then(if (isEditMode) Modifier.testTag("product_edit_screen") else Modifier),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") }
            Column {
                Text(text = if (isEditMode) "Edit Produk" else "Tambah Produk", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Text(text = if (isEditMode) "Perbarui data produk existing" else "Atur data dasar dan mode produk", color = Color(0xFF64748B), fontSize = 11.sp)
            }
        }

        Column(
            modifier = Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Card(
                shape = RoundedCornerShape(22.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White),
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    Box(
                        modifier = Modifier
                            .size(120.dp)
                            .clip(RoundedCornerShape(22.dp))
                            .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(22.dp))
                            .background(Color(0xFFF8FAFC))
                            .align(Alignment.CenterHorizontally),
                        contentAlignment = Alignment.Center,
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Default.AddAPhoto, contentDescription = null, tint = Color(0xFF94A3B8))
                            Text(text = "Foto Produk", color = Color(0xFF94A3B8), fontSize = 12.sp)
                        }
                    }

                    OutlinedTextField(value = name, onValueChange = { name = it }, label = { Text("Nama Produk") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(
                        value = category,
                        onValueChange = { category = it },
                        label = { Text("Kategori") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        trailingIcon = {
                            Text(
                                text = "Pilih",
                                color = UiPrimary,
                                fontWeight = FontWeight.Bold,
                                fontSize = 11.sp,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .clickable { showCategoryPicker = true }
                                    .testTag("category_picker")
                                    .padding(horizontal = 8.dp, vertical = 4.dp),
                            )
                        },
                    )
                    OutlinedTextField(value = sellPrice, onValueChange = { sellPrice = it }, label = { Text("Harga Jual") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                    OutlinedTextField(
                        value = if (selectedMode == NewProductMode.RECIPE) {
                            val estimated = recipeLines.sumOf { row ->
                                val qtyValue = row.qty.trim().replace(',', '.').toDoubleOrNull() ?: 0.0
                                val unitCost = row.materialProductLocalId?.let { candidateCostById[it] } ?: 0L
                                (qtyValue * unitCost).toLong()
                            }
                            estimated.toString()
                        } else {
                            costPrice
                        },
                        onValueChange = { costPrice = it },
                        label = { Text(if (selectedMode == NewProductMode.RECIPE) "HPP Estimasi (otomatis)" else "Harga Modal") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        readOnly = selectedMode == NewProductMode.RECIPE,
                        enabled = selectedMode != NewProductMode.RECIPE,
                    )
                }
            }

            Text(
                text = "MODE PRODUK",
                color = Color(0xFF94A3B8),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                NewProductMode.entries.forEach { mode ->
                    val active = mode == selectedMode
                    Text(
                        text = mode.label,
                        color = if (active) Color.White else Color(0xFF475569),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (active) UiPrimary else Color.White)
                            .border(1.dp, if (active) UiPrimary else Color(0xFFCBD5E1), RoundedCornerShape(999.dp))
                            .clickable {
                                selectedMode = mode
                                Log.d("ProductModeUi", "mode_clicked=${mode.name.lowercase()}")
                            }
                            .then(
                                when (mode) {
                                    NewProductMode.ADD_ON -> Modifier.testTag("product_mode_add_on")
                                    NewProductMode.RECIPE -> Modifier.testTag("product_mode_recipe")
                                    else -> Modifier
                                },
                            )
                            .padding(horizontal = 12.dp, vertical = 7.dp),
                    )
                }
            }

            val modeBadgeLabel = when (selectedMode) {
                NewProductMode.ADD_ON -> "Bahan Baku"
                NewProductMode.RECIPE -> "Resep"
                else -> "Produk Biasa"
            }
            val modeBadgeColor = when (selectedMode) {
                NewProductMode.ADD_ON -> Color(0xFF14532D)
                NewProductMode.RECIPE -> Color(0xFF1D4ED8)
                else -> Color(0xFF334155)
            }
            Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        text = when (selectedMode) {
                            NewProductMode.ADD_ON -> "Mode Add-On akan disimpan sebagai produk bahan baku (raw_material)."
                            NewProductMode.RECIPE -> "Mode Resep membuat produk output finished_good dengan komponen dari bahan baku."
                            else -> "Mode ini membuat produk biasa untuk dijual."
                        },
                        color = Color(0xFF475569),
                        fontSize = 12.sp,
                        modifier = Modifier.weight(1f),
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = modeBadgeLabel,
                        color = modeBadgeColor,
                        fontWeight = FontWeight.Bold,
                        fontSize = 11.sp,
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(modeBadgeColor.copy(alpha = 0.12f))
                            .padding(horizontal = 10.dp, vertical = 4.dp),
                    )
                }
            }

            if (selectedMode == NewProductMode.ADD_ON) {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Bahan Baku", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF64748B))
                        Text(
                            text = "Produk mode Add-On diperlakukan sebagai bahan baku dan otomatis menjadi kandidat di picker resep.",
                            fontSize = 12.sp,
                            color = Color(0xFF334155),
                        )
                    }
                }
            }

            if (selectedMode == NewProductMode.VARIANT) {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Varian Produk", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF64748B))
                variants.forEachIndexed { index, row ->
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        OutlinedTextField(
                            value = row.name,
                            onValueChange = { variants[index] = row.copy(name = it) },
                            label = { Text("Nama Varian") },
                            modifier = Modifier.weight(1f),
                            singleLine = true,
                        )
                        OutlinedTextField(
                            value = row.price,
                            onValueChange = { variants[index] = row.copy(price = it) },
                            label = { Text("Harga") },
                            modifier = Modifier.width(110.dp),
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        )
                        CircleIconButton(icon = Icons.Default.Remove, onClick = {
                            if (variants.size > 1) variants.removeAt(index)
                        })
                    }
                }
                Button(
                    onClick = { variants.add(VariantDraftUi()) },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                ) {
                    Icon(Icons.Default.Add, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Tambah Varian", fontWeight = FontWeight.Bold)
                }
                    }
                }
            }

            if (selectedMode == NewProductMode.RECIPE) {
                val recipeHppEstimate = recipeLines.sumOf { row ->
                    val qtyValue = row.qty.trim().replace(',', '.').toDoubleOrNull() ?: 0.0
                    val unitCost = row.materialProductLocalId?.let { candidateCostById[it] } ?: 0L
                    (qtyValue * unitCost).toLong()
                }
                val missingMaterialSelection = recipeLines.any {
                    !it.materialName.isBlank() && it.materialProductLocalId.isNullOrBlank()
                }
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = "Resep / Bahan Baku", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF64748B))
                Text(
                    text = "Output produk: ${name.ifBlank { "(isi Nama Produk dulu)" } }",
                    fontSize = 11.sp,
                    color = Color(0xFF475569),
                    fontWeight = FontWeight.SemiBold,
                )
                if (recipeMaterialCandidates.isEmpty()) {
                    Text(
                        text = "Belum ada kandidat bahan valid. Tambahkan bahan/add-on terlebih dulu.",
                        fontSize = 12.sp,
                        color = Color(0xFFB45309),
                        fontWeight = FontWeight.SemiBold,
                    )
                }
                Text(
                    text = if (missingMaterialSelection) {
                        "HPP akan dihitung otomatis setelah semua bahan dipilih dari daftar."
                    } else {
                        "HPP Estimasi Resep: ${rupiah(recipeHppEstimate)}"
                    },
                    fontSize = 12.sp,
                    color = if (missingMaterialSelection) Color(0xFFB45309) else Color(0xFF14532D),
                    fontWeight = FontWeight.Bold,
                )
                recipeLines.forEachIndexed { index, row ->
                    Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                        Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Button(
                                onClick = {
                                    pickerTargetRowIndex = index
                                    materialSearchQuery = ""
                                },
                                enabled = recipeMaterialCandidates.isNotEmpty(),
                                modifier = Modifier.fillMaxWidth().testTag("recipe_material_picker"),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEEF2FF)),
                            ) {
                                Text(
                                    text = if (row.materialProductLocalId != null) "Ganti Bahan" else "Pilih Bahan dari Daftar",
                                    color = Color(0xFF334155),
                                    fontWeight = FontWeight.Bold,
                                )
                            }
                            OutlinedTextField(
                                value = row.materialName,
                                onValueChange = {
                                    recipeLines[index] = row.copy(
                                        materialName = it,
                                        materialProductLocalId = null,
                                    )
                                },
                                label = { Text("Nama Bahan") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true,
                                readOnly = recipeMaterialCandidates.isNotEmpty(),
                                enabled = recipeMaterialCandidates.isEmpty(),
                            )
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = row.qty,
                                    onValueChange = { recipeLines[index] = row.copy(qty = it) },
                                    label = { Text("Qty") },
                                    modifier = Modifier.weight(1f),
                                    singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                )
                                OutlinedTextField(
                                    value = row.unit,
                                    onValueChange = { recipeLines[index] = row.copy(unit = it) },
                                    label = { Text("Satuan") },
                                    modifier = Modifier.weight(1f),
                                    singleLine = true,
                                )
                            }
                            Text(
                                text = "Hapus",
                                color = Color(0xFFDC2626),
                                fontWeight = FontWeight.SemiBold,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .clickable(enabled = recipeLines.size > 1) {
                                        if (recipeLines.size > 1) recipeLines.removeAt(index)
                                    }
                                    .padding(horizontal = 8.dp, vertical = 4.dp),
                            )
                        }
                    }
                }
                Button(
                    onClick = { recipeLines.add(RecipeEditorLineUi()) },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                ) {
                    Icon(Icons.Default.Add, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Tambah Bahan", fontWeight = FontWeight.Bold)
                }
                    }
                }
            }

            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                OutlinedTextField(
                    value = stockCounter.toString(),
                    onValueChange = {},
                    label = { Text("Stok Awal") },
                    readOnly = true,
                    modifier = Modifier.weight(1f),
                )
                CircleIconButton(icon = Icons.Default.Remove, onClick = { if (allowStockAdjustment) onDecrease() })
                CircleIconButton(icon = Icons.Default.Add, onClick = { if (allowStockAdjustment) onIncrease() })
                }
            }

            if (!allowStockAdjustment) {
                Text(
                    text = "Stok awal tidak dapat diubah dari mode edit produk.",
                    color = Color(0xFF64748B),
                    fontSize = 12.sp,
                )
            }

            if (!validationError.isNullOrBlank()) {
                Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2))) {
                    Text(
                        text = validationError ?: "",
                        color = Color(0xFFB91C1C),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(10.dp),
                    )
                }
            }
        }

        Button(
            onClick = {
                validationError = null
                val price = sellPrice.toLongOrNull() ?: 0L
                val manualCost = costPrice.toLongOrNull() ?: 0L
                val normalizedCategory = normalizeCategoryName(
                    rawValue = category,
                    existingCategories = existingCategories,
                )
                val recipePayload = recipeLines
                    .mapNotNull { row ->
                        val materialName = row.materialName.trim()
                        val qtyValue = row.qty.trim().toDoubleOrNull()
                        val unitValue = row.unit.trim().ifBlank { "pcs" }
                        if (materialName.isBlank() || qtyValue == null || qtyValue <= 0.0) {
                            null
                        } else {
                            RecipeSeedLineUi(
                                materialProductLocalId = row.materialProductLocalId,
                                materialName = materialName,
                                qty = qtyValue,
                                unit = unitValue,
                            )
                        }
                    }
                    val recipeComputedCost = recipePayload.sumOf { line ->
                        val materialCost = line.materialProductLocalId?.let { candidateCostById[it] } ?: 0L
                        (line.qty * materialCost).toLong()
                    }
                    val effectiveCost = if (selectedMode == NewProductMode.RECIPE) {
                        recipeComputedCost
                    } else {
                        manualCost
                    }
                if (selectedMode == NewProductMode.RECIPE) {
                    Log.d(
                        "ProductModeUi",
                        "recipe_save_attempt=1, selected_count=${recipePayload.size}, selected_ids=${recipePayload.mapNotNull { it.materialProductLocalId }}",
                    )
                        Log.d(
                            "ProductModeUi",
                            "recipe_hpp_count=${recipePayload.size}, recipe_hpp_material_total=$recipeComputedCost, recipe_hpp_final=$effectiveCost",
                        )
                }
                val addOnPayload = addOnNames.map { it.trim() }.filter { it.isNotBlank() }
                val variantPayload = variants.mapNotNull { row ->
                    val variantName = row.name.trim()
                    val variantPrice = row.price.trim().toLongOrNull()
                    if (variantName.isBlank() || variantPrice == null || variantPrice <= 0L) {
                        null
                    } else {
                        NewProductVariantDraft(name = variantName, price = variantPrice)
                    }
                }

                when {
                    name.trim().isBlank() -> {
                        validationError = "Nama produk wajib diisi."
                        return@Button
                    }
                    price <= 0L -> {
                        validationError = "Harga jual harus lebih dari 0."
                        return@Button
                    }
                    effectiveCost < 0L -> {
                        validationError = "Harga modal tidak boleh negatif."
                        return@Button
                    }
                    selectedMode == NewProductMode.RECIPE && recipeMaterialCandidates.isNotEmpty() &&
                        recipePayload.any { it.materialProductLocalId.isNullOrBlank() } -> {
                        validationError = "Pilih semua bahan resep dari daftar kandidat bahan baku."
                        return@Button
                    }
                    selectedMode == NewProductMode.VARIANT && variantPayload.isEmpty() -> {
                        validationError = "Mode Varian wajib punya minimal 1 varian valid."
                        return@Button
                    }
                    selectedMode == NewProductMode.RECIPE && recipePayload.isEmpty() -> {
                        validationError = "Mode Resep wajib punya minimal 1 bahan valid."
                        return@Button
                    }
                }

                if (isEditMode && initialSeed != null) {
                    val changedFields = mutableListOf<String>()
                    if (initialSeed.name.trim() != name.trim()) changedFields.add("name")
                    if (initialSeed.category.trim() != normalizedCategory.trim()) changedFields.add("category")
                    if (initialSeed.sellPrice != price) changedFields.add("sell_price")
                    if (initialSeed.costPrice != effectiveCost) changedFields.add("cost_price")
                    if (initialSeed.mode != selectedMode) changedFields.add("mode")
                    if (allowStockAdjustment && initialSeed.openingStock != stockCounter) changedFields.add("opening_stock")
                    if (initialSeed.recipeLines.size != recipePayload.size) changedFields.add("recipe_lines")
                    Log.d(
                        "ProductEdit",
                        "edit_attempt productLocalId=${initialSeed.localId} mode=${selectedMode.name.lowercase()} changedFields=${changedFields.ifEmpty { listOf("none") }}",
                    )
                }

                onSave(
                    NewProductDraft(
                        editingProductLocalId = initialSeed?.localId,
                        name = name.trim(),
                        category = normalizedCategory,
                        sellPrice = price,
                        costPrice = effectiveCost,
                        openingStock = if (allowStockAdjustment) stockCounter else (initialSeed?.openingStock ?: stockCounter),
                        mode = selectedMode,
                        addOns = addOnPayload,
                        variants = variantPayload,
                        recipeLines = recipePayload,
                    ),
                )
            },
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
                .height(52.dp)
                .then(if (isEditMode) Modifier.testTag("save_product_edit_button") else Modifier.testTag("save_product_button")),
            colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
            shape = RoundedCornerShape(16.dp),
        ) {
            Icon(Icons.Default.Save, contentDescription = null)
            Spacer(modifier = Modifier.width(8.dp))
            Text(text = if (isEditMode) "Simpan Perubahan" else "Simpan Produk", fontWeight = FontWeight.Bold)
        }

        if (showCategoryPicker) {
            AlertDialog(
                onDismissRequest = { showCategoryPicker = false },
                confirmButton = {
                    TextButton(onClick = { showCategoryPicker = false }) {
                        Text("Tutup")
                    }
                },
                title = { Text("Pilih Kategori") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = categorySearchQuery,
                            onValueChange = { categorySearchQuery = it },
                            label = { Text("Cari kategori") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth(),
                        )
                        LazyColumn(modifier = Modifier.heightIn(max = 280.dp)) {
                            itemsIndexed(filteredExistingCategories) { _, existingCategory ->
                                Text(
                                    text = existingCategory,
                                    color = Color(0xFF334155),
                                    fontWeight = FontWeight.SemiBold,
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .clickable {
                                            category = existingCategory
                                            showCategoryPicker = false
                                        }
                                        .padding(horizontal = 8.dp, vertical = 10.dp)
                                        .testTag("category_item"),
                                )
                            }
                            if (filteredExistingCategories.isEmpty()) {
                                item {
                                    Text(
                                        text = "Belum ada kategori cocok. Tetap bisa ketik kategori baru.",
                                        color = Color(0xFF64748B),
                                        fontSize = 12.sp,
                                    )
                                }
                            }
                        }
                    }
                },
            )
        }

        if (pickerTargetRowIndex != null) {
            AlertDialog(
                onDismissRequest = { pickerTargetRowIndex = null },
                confirmButton = {
                    TextButton(onClick = { pickerTargetRowIndex = null }) {
                        Text("Tutup")
                    }
                },
                title = { Text("Pilih Bahan Resep") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = materialSearchQuery,
                            onValueChange = { materialSearchQuery = it },
                            label = { Text("Cari bahan") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth(),
                        )
                        LazyColumn(modifier = Modifier.heightIn(max = 320.dp)) {
                            itemsIndexed(filteredRecipeMaterialCandidates) { _, candidate ->
                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .clickable {
                                            val rowIndex = pickerTargetRowIndex ?: return@clickable
                                            if (rowIndex in recipeLines.indices) {
                                                recipeLines[rowIndex] = recipeLines[rowIndex].copy(
                                                    materialProductLocalId = candidate.localId,
                                                    materialName = candidate.name,
                                                )
                                                Log.d(
                                                    "ProductModeUi",
                                                    "recipe_material_selected=${candidate.localId}, name=${candidate.name}, itemType=${candidate.itemType}, mode=${candidate.productMode}",
                                                )
                                            }
                                            pickerTargetRowIndex = null
                                        }
                                        .padding(horizontal = 8.dp, vertical = 10.dp)
                                        .testTag("recipe_material_item"),
                                ) {
                                    Text(candidate.name, fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                                    Text(
                                        "${candidate.categoryName} • Bahan Baku",
                                        color = Color(0xFF64748B),
                                        fontSize = 12.sp,
                                    )
                                }
                            }
                        }
                    }
                },
            )
        }
    }
}

@Composable
private fun ProductDetailScreen(
    product: PosProductItemUi,
    stock: Int,
    recipeLines: List<RecipeSeedLineUi>,
    recipeMaterialCandidates: List<RecipeMaterialCandidateUi>,
    loadFifoLayers: suspend (String) -> List<ProductFifoLayerUi>,
    onBack: () -> Unit,
    onEdit: () -> Unit,
) {
    val normalizedMode = normalizeProductMode(product.productMode)
    val normalizedItemType = normalizeProductItemType(product.itemType, product.productMode)
    val materialCostById = remember(recipeMaterialCandidates) {
        recipeMaterialCandidates.associateBy({ it.localId }, { it.unitCost.coerceAtLeast(0L) })
    }
    val recipeHppEstimate = remember(recipeLines, materialCostById) {
        recipeLines.sumOf { line ->
            val materialCost = line.materialProductLocalId?.let { materialCostById[it] } ?: 0L
            (line.qty * materialCost).toLong()
        }
    }
    var fifoLayers by remember(product.localId) { mutableStateOf<List<ProductFifoLayerUi>>(emptyList()) }
    var isFifoLoading by remember(product.localId) { mutableStateOf(true) }
    LaunchedEffect(product.localId) {
        isFifoLoading = true
        fifoLayers = runCatching { loadFifoLayers(product.localId) }.getOrDefault(emptyList())
        isFifoLoading = false
    }
    Column(modifier = Modifier.fillMaxSize()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") }
                Text(text = "${product.name}", fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            IconButton(onClick = onEdit) { Icon(Icons.Default.MoreHoriz, contentDescription = "More") }
        }

        Column(
            modifier = Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .testTag("product_detail_screen")
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(220.dp)
                    .clip(RoundedCornerShape(28.dp))
                    .background(Brush.linearGradient(colors = listOf(Color(0xFFD7E5E0), Color(0xFFE8EEF1)))),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Default.Inventory2, contentDescription = null, tint = UiPrimary, modifier = Modifier.size(56.dp))
            }

            Card(colors = CardDefaults.cardColors(containerColor = Color.White), shape = RoundedCornerShape(24.dp)) {
                Column(modifier = Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(text = "Harga Jual", color = Color(0xFF94A3B8), fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text(text = rupiah(product.price), fontSize = 30.sp, fontWeight = FontWeight.Black)
                    Text(text = "Kategori: ${product.categoryName}", color = UiPrimary, fontWeight = FontWeight.Bold)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        ProductMetaBadge(label = "Mode: ${modeLabel(normalizedMode)}", tag = "product_mode_badge")
                        ProductMetaBadge(label = "Type: ${itemTypeLabel(normalizedItemType)}")
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(text = "Stok Saat Ini: $stock", fontWeight = FontWeight.Bold)
                    Text(text = "HPP Estimasi: ${rupiah(recipeHppEstimate)}", color = Color(0xFF334155), fontSize = 12.sp)
                    Text(text = "SKU: ${product.localId.takeLast(8).uppercase(Locale.ROOT)}", color = Color(0xFF64748B), fontSize = 11.sp)
                }
            }

            Card(
                colors = CardDefaults.cardColors(containerColor = Color.White),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier.testTag("product_fifo_section"),
            ) {
                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(text = "Stok FIFO", fontWeight = FontWeight.Bold, color = Color(0xFF0F766E))
                    when {
                        isFifoLoading -> {
                            Text(text = "Memuat layer FIFO...", color = Color(0xFF64748B), fontSize = 12.sp)
                        }

                        fifoLayers.isEmpty() -> {
                            Text(
                                text = "Belum ada layer FIFO aktif untuk produk ini.",
                                color = Color(0xFF64748B),
                                fontSize = 12.sp,
                                modifier = Modifier.testTag("product_stock_notice"),
                            )
                        }

                        else -> {
                            fifoLayers.forEachIndexed { index, layer ->
                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .testTag("product_fifo_item"),
                                    verticalArrangement = Arrangement.spacedBy(2.dp),
                                ) {
                                    Text(
                                        text = "Batch ${layer.sourceRef} • Masuk ${formatFifoInboundDate(layer.receivedAt)}",
                                        color = Color(0xFF334155),
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                    Text(
                                        text = "Sisa: ${layer.qtyAvailable} / ${layer.qtyOriginal}",
                                        color = Color(0xFF475569),
                                        fontSize = 11.sp,
                                    )
                                    if (layer.unitCost > 0.0) {
                                        Text(
                                            text = "Biaya/unit: ${rupiah(layer.unitCost.toLong())}",
                                            color = Color(0xFF14532D),
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                        )
                                    }
                                }
                                if (index < fifoLayers.lastIndex) {
                                    HorizontalDivider(color = Color(0xFFE2E8F0))
                                }
                            }
                        }
                    }
                }
            }

            when {
                normalizedMode == "bom" -> {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        shape = RoundedCornerShape(16.dp),
                        modifier = Modifier.testTag("recipe_detail_section"),
                    ) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text(text = "Komposisi Resep", fontWeight = FontWeight.Bold, color = Color(0xFF1D4ED8))
                            if (recipeLines.isEmpty()) {
                                Text(
                                    text = "Belum ada bahan resep tersimpan.",
                                    color = Color(0xFF64748B),
                                    fontSize = 12.sp,
                                )
                            } else {
                                recipeLines.forEach { line ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .testTag("recipe_material_detail_item"),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically,
                                    ) {
                                        Text(
                                            text = "${line.materialName} (${line.qty} ${line.unit})",
                                            color = Color(0xFF334155),
                                            fontSize = 12.sp,
                                        )
                                        val materialCost = line.materialProductLocalId
                                            ?.let { materialCostById[it] }
                                            ?.let { (line.qty * it).toLong() }
                                            ?: 0L
                                        Text(text = rupiah(materialCost), color = Color(0xFF14532D), fontWeight = FontWeight.Bold, fontSize = 11.sp)
                                    }
                                }
                                HorizontalDivider(color = Color(0xFFE2E8F0))
                                Text(
                                    text = "Total HPP Resep: ${rupiah(recipeHppEstimate)}",
                                    color = Color(0xFF14532D),
                                    fontWeight = FontWeight.Bold,
                                )
                            }
                        }
                    }
                }
                normalizedItemType == "raw_material" -> {
                    Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFECFDF3)), shape = RoundedCornerShape(14.dp)) {
                        Text(
                            text = "Produk ini berperan sebagai bahan baku untuk resep/auto-consume.",
                            color = Color(0xFF166534),
                            modifier = Modifier.padding(12.dp),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                }
                normalizedMode == "variant" -> {
                    Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFEFF6FF)), shape = RoundedCornerShape(14.dp)) {
                        Text(
                            text = "Produk mode varian. Detail atribut varian mengikuti struktur existing produk turunan.",
                            color = Color(0xFF1D4ED8),
                            modifier = Modifier.padding(12.dp),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                }
                else -> {
                    Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)), shape = RoundedCornerShape(14.dp)) {
                        Text(
                            text = "Produk biasa (default) untuk penjualan langsung.",
                            color = Color(0xFF334155),
                            modifier = Modifier.padding(12.dp),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                }
            }

            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                    shape = RoundedCornerShape(14.dp),
                ) {
                    Icon(Icons.Default.Add, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Tambah Stok")
                }
                Button(
                    onClick = {},
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(containerColor = Color.White, contentColor = Color(0xFF334155)),
                    shape = RoundedCornerShape(14.dp),
                ) {
                    Icon(Icons.Default.Remove, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Kurangi Stok")
                }
            }

            Button(
                onClick = onEdit,
                modifier = Modifier.fillMaxWidth().testTag("edit_product_button"),
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF0F172A)),
                shape = RoundedCornerShape(14.dp),
            ) {
                Icon(Icons.Default.Edit, contentDescription = null)
                Spacer(modifier = Modifier.width(6.dp))
                Text("Edit Produk")
            }
        }
    }
}

@Composable
fun HistoryScreen(
    receipts: List<SaleReceiptUi>,
    shifts: List<HistoryShiftUi>,
    saleItemsBySaleId: Map<String, List<SaleItemWithProductNameRow>>,
    paymentBySaleId: Map<String, PaymentEntity>,
    pendingCount: Int,
    failedCount: Int,
    canPrintShiftSummary: Boolean,
    canDownloadReport: Boolean,
    canViewPurchasesReport: Boolean,
    canViewStockCard: Boolean,
    canViewFifoLayers: Boolean,
    showAuditLog: Boolean,
    auditEvents: List<String>,
    purchaseSuppliers: List<SupplierSummary>,
    purchaseProducts: List<PurchaseProductOptionUi>,
    purchaseSaving: Boolean,
    purchaseMessage: String?,
    purchaseError: String?,
    purchaseCreateCanSubmit: Boolean,
    purchaseCreateFieldErrors: Map<String, String>,
    purchaseCreateFormError: String?,
    purchaseReceiveCanSubmit: Boolean,
    purchaseReceiveActionEnabled: Boolean,
    purchaseReceiveFieldErrors: Map<String, String>,
    purchaseReceiveActionError: String?,
    purchaseReturnCanSubmit: Boolean,
    purchaseReturnActionEnabled: Boolean,
    purchaseReturnFieldErrors: Map<String, String>,
    purchaseReturnActionError: String?,
    onCreateSupplier: (String, String?, String?, String?) -> Unit,
    onFinalizePurchase: (PurchaseFinalizeDraftUi) -> Unit,
    onEvaluateFinalizePurchaseDraft: (PurchaseFinalizeDraftUi) -> Unit,
    onEvaluateMarkPurchaseReceivedDraft: (PurchaseReceiveDraftUi) -> Unit,
    onMarkPurchaseReceived: (PurchaseReceiveDraftUi) -> Unit,
    onEvaluateRecordPurchaseReturnDraft: (PurchaseReturnDraftUi) -> Unit,
    onRecordPurchaseReturn: (PurchaseReturnDraftUi) -> Unit,
    inventoryOpsSaving: Boolean,
    inventoryOpsMessage: String?,
    inventoryOpsError: String?,
    onRecordStockAdjustment: (String, Double, String) -> Unit,
    onRecordStockTransfer: (String, Double, String, String) -> Unit,
    taxPercent: Double,
    onVoidSale: (SaleReceiptUi) -> Unit,
    onOpenSync: () -> Unit,
    initialReceiptNo: String? = null,
    initialSaleLocalId: String? = null,
    onInitialDetailConsumed: () -> Unit = {},
    onRequestExit: (() -> Unit)? = null,
) {
    val context = LocalContext.current
    var activeTab by remember { mutableStateOf(HistoryTab.TRANSAKSI) }
    var historyMode by remember { mutableStateOf(HistoryMode.LIST) }
    var selectedReceipt by remember { mutableStateOf<SaleReceiptUi?>(null) }
    var selectedReceiptNo by remember { mutableStateOf<String?>(null) }
    var selectedSaleLocalId by remember { mutableStateOf<String?>(null) }
    var selectedShift by remember { mutableStateOf<HistoryShiftUi?>(null) }
    BackHandler(enabled = historyMode != HistoryMode.LIST || onRequestExit != null) {
        if (historyMode != HistoryMode.LIST) {
            historyMode = HistoryMode.LIST
            selectedReceipt = null
            selectedReceiptNo = null
            selectedSaleLocalId = null
            selectedShift = null
        } else {
            onRequestExit?.invoke()
        }
    }

    val fallbackReceipts = remember {
        listOf(
            SaleReceiptUi(
                saleLocalId = "trx-refund-1",
                receiptNo = "TRX-20231024-0089",
                customerName = "Walk-in",
                items = listOf(
                    com.umkm.offline.feature.pos.presentation.ReceiptItemUi(name = "Kopi Susu Gula Aren", qty = 2.0, lineTotal = 36000),
                    com.umkm.offline.feature.pos.presentation.ReceiptItemUi(name = "Croissant Cheese", qty = 1.0, lineTotal = 25000),
                ),
                totalAmount = 67100,
                paymentMethodLabel = "QRIS Dana",
                syncStatusLabel = "Sudah Di-refund",
            ),
            SaleReceiptUi(
                saleLocalId = "trx-ok-2",
                receiptNo = "TRX-20231024-0091",
                customerName = "Walk-in",
                items = listOf(
                    com.umkm.offline.feature.pos.presentation.ReceiptItemUi(name = "Es Kopi Susu", qty = 2.0, lineTotal = 40000),
                    com.umkm.offline.feature.pos.presentation.ReceiptItemUi(name = "Roti Bakar Coklat", qty = 1.0, lineTotal = 15000),
                ),
                totalAmount = 60500,
                paymentMethodLabel = "Tunai",
                syncStatusLabel = "Sukses",
            ),
        )
    }

    val shownReceipts = if (receipts.isEmpty()) fallbackReceipts else receipts
    val shiftHistory = remember {
        listOf(
            HistoryShiftUi("20 Okt 2023", "08:00 - 16:00", "Budi", 100000, 2500000, true),
            HistoryShiftUi("19 Okt 2023", "08:00 - 16:00", "Ani", 100000, 1850000, true),
            HistoryShiftUi("18 Okt 2023", "08:00 - 16:00", "Budi", 100000, 3100000, false),
        )
    }
    val shownShifts = if (shifts.isEmpty()) shiftHistory else shifts

    LaunchedEffect(initialReceiptNo, initialSaleLocalId, shownReceipts) {
        val targetReceiptNo = initialReceiptNo?.trim()?.ifBlank { null }
        val targetSaleLocalId = initialSaleLocalId?.trim()?.ifBlank { null }
        if (targetReceiptNo == null && targetSaleLocalId == null) {
            return@LaunchedEffect
        }

        val resolved = shownReceipts.firstOrNull { receipt ->
            (targetSaleLocalId != null && receipt.saleLocalId == targetSaleLocalId) ||
                (targetReceiptNo != null && receipt.receiptNo == targetReceiptNo)
        }
        if (resolved != null) {
            activeTab = HistoryTab.TRANSAKSI
            selectedReceiptNo = resolved.receiptNo
            selectedSaleLocalId = resolved.saleLocalId
            historyMode = HistoryMode.TRANSACTION_DETAIL
            onInitialDetailConsumed()
        }
    }

    Surface(modifier = Modifier.fillMaxSize(), color = UiBackground) {
        when (historyMode) {
            HistoryMode.TRANSACTION_DETAIL -> {
                val currentReceipt = shownReceipts.firstOrNull { receipt ->
                    (selectedSaleLocalId != null && receipt.saleLocalId == selectedSaleLocalId) ||
                        (selectedReceiptNo != null && receipt.receiptNo == selectedReceiptNo)
                }

                currentReceipt?.let {
                    val resolvedItems = resolveReceiptItems(
                        receipt = it,
                        saleItemsBySaleId = saleItemsBySaleId,
                    )
                    val resolvedMethod = paymentBySaleId[it.saleLocalId]
                        ?.method
                        ?.let(::normalizePaymentMethodLabel)
                        ?: it.paymentMethodLabel
                    TransactionDetailScreen(
                        receipt = it.copy(
                            items = resolvedItems,
                            paymentMethodLabel = resolvedMethod,
                        ),
                        taxPercent = taxPercent,
                        onBack = {
                            historyMode = HistoryMode.LIST
                            selectedReceiptNo = null
                            selectedSaleLocalId = null
                        },
                        onVoid = {
                            onVoidSale(it)
                            historyMode = HistoryMode.LIST
                            selectedReceiptNo = null
                            selectedSaleLocalId = null
                        },
                        onShare = {
                            shareReceipt(
                                context = context,
                                receipt = it.copy(items = resolvedItems, paymentMethodLabel = resolvedMethod),
                                printable = false,
                            )
                        },
                        onPrint = {
                            printReceiptNative(
                                context = context,
                                receipt = it.copy(items = resolvedItems, paymentMethodLabel = resolvedMethod),
                            )
                        },
                    )
                }
            }

            HistoryMode.SHIFT_DETAIL -> {
                selectedShift?.let {
                    ShiftDetailScreen(
                        shift = it,
                        canPrintSummary = canPrintShiftSummary,
                        onBack = { historyMode = HistoryMode.LIST },
                    )
                }
            }

            HistoryMode.PURCHASE_REPORT -> {
                PurchaseManagementScreen(
                    title = "Laporan Pembelian",
                    suppliers = purchaseSuppliers,
                    products = purchaseProducts,
                    isSaving = purchaseSaving,
                    message = purchaseMessage,
                    errorMessage = purchaseError,
                    createCanSubmit = purchaseCreateCanSubmit,
                    createFieldErrors = purchaseCreateFieldErrors,
                    createFormError = purchaseCreateFormError,
                    receiveCanSubmit = purchaseReceiveCanSubmit,
                    receiveActionEnabled = purchaseReceiveActionEnabled,
                    receiveFieldErrors = purchaseReceiveFieldErrors,
                    receiveActionError = purchaseReceiveActionError,
                    returnCanSubmit = purchaseReturnCanSubmit,
                    returnActionEnabled = purchaseReturnActionEnabled,
                    returnFieldErrors = purchaseReturnFieldErrors,
                    returnActionError = purchaseReturnActionError,
                    onCreateSupplier = onCreateSupplier,
                    onFinalizePurchase = onFinalizePurchase,
                    onEvaluateFinalizePurchaseDraft = onEvaluateFinalizePurchaseDraft,
                    onEvaluateMarkPurchaseReceivedDraft = onEvaluateMarkPurchaseReceivedDraft,
                    onMarkPurchaseReceived = onMarkPurchaseReceived,
                    onEvaluateRecordPurchaseReturnDraft = onEvaluateRecordPurchaseReturnDraft,
                    onRecordPurchaseReturn = onRecordPurchaseReturn,
                    onBack = { historyMode = HistoryMode.LIST },
                )
            }

            HistoryMode.STOCK_CARD -> {
                StockOperationsScreen(
                    title = "Kartu Stok",
                    isSaving = inventoryOpsSaving,
                    message = inventoryOpsMessage,
                    errorMessage = inventoryOpsError,
                    onRecordStockAdjustment = onRecordStockAdjustment,
                    onRecordStockTransfer = onRecordStockTransfer,
                    onBack = { historyMode = HistoryMode.LIST },
                )
            }

            HistoryMode.FIFO_LAYERS -> {
                FinanceModuleScreen(
                    title = "FIFO Layers",
                    subtitle = "Detail layer biaya persediaan",
                    items = listOf("Layer Aktif", "Konsumsi Layer", "Valuasi Terkini"),
                    onBack = { historyMode = HistoryMode.LIST },
                )
            }

            HistoryMode.AUDIT_LOG -> {
                GovernanceScreen(
                    auditEvents = auditEvents,
                    onBack = { historyMode = HistoryMode.LIST },
                )
            }

            HistoryMode.LIST -> {
                Column(modifier = Modifier.fillMaxSize()) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(bottomStart = 18.dp, bottomEnd = 18.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 14.dp, vertical = 10.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                Box(
                                    modifier = Modifier
                                        .size(38.dp)
                                        .clip(RoundedCornerShape(12.dp))
                                        .background(UiPrimary.copy(alpha = 0.2f)),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Icon(Icons.Default.Inventory2, contentDescription = null, tint = UiPrimary)
                                }
                                Column {
                                    Text(text = "Riwayat", fontWeight = FontWeight.Bold, fontSize = 20.sp)
                                    Text(text = "Transaksi, shift, dan laporan", fontSize = 10.sp, color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold)
                                }
                            }
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                CircleIconButton(icon = Icons.Default.FilterList)
                                CircleIconButton(icon = Icons.Default.Person)
                            }
                        }

                        Row(
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 2.dp),
                            horizontalArrangement = Arrangement.spacedBy(18.dp),
                        ) {
                            HistoryTabButton(text = "Transaksi", active = activeTab == HistoryTab.TRANSAKSI) { activeTab = HistoryTab.TRANSAKSI }
                            HistoryTabButton(text = "Shift", active = activeTab == HistoryTab.SHIFT) { activeTab = HistoryTab.SHIFT }
                            HistoryTabButton(text = "Laporan", active = activeTab == HistoryTab.LAPORAN) { activeTab = HistoryTab.LAPORAN }
                        }
                    }

                    when (activeTab) {
                        HistoryTab.TRANSAKSI -> {
                            LazyColumn(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .weight(1f),
                                contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 96.dp),
                                verticalArrangement = Arrangement.spacedBy(10.dp),
                            ) {
                                itemsIndexed(shownReceipts) { _, receipt ->
                                    val voidStatus = resolveVoidReceiptStatus(receipt.syncStatusLabel)
                                    val statusText = when (voidStatus) {
                                        VoidReceiptStatus.REQUESTED -> "Void Diminta"
                                        VoidReceiptStatus.COMPLETED -> "Sudah Di-refund"
                                        VoidReceiptStatus.NONE -> "Sukses"
                                    }
                                    val statusColor = when (voidStatus) {
                                        VoidReceiptStatus.REQUESTED -> Color(0xFFF59E0B)
                                        VoidReceiptStatus.COMPLETED -> Color(0xFFE11D48)
                                        VoidReceiptStatus.NONE -> UiPrimary
                                    }
                                    Card(
                                        modifier = Modifier
                                            .testTag("history_sale_item")
                                            .fillMaxWidth()
                                            .clickable {
                                                selectedReceipt = receipt
                                                selectedReceiptNo = receipt.receiptNo
                                                selectedSaleLocalId = receipt.saleLocalId
                                                historyMode = HistoryMode.TRANSACTION_DETAIL
                                            },
                                        shape = RoundedCornerShape(18.dp),
                                        colors = CardDefaults.cardColors(containerColor = Color.White),
                                    ) {
                                        Row(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .padding(14.dp),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically,
                                        ) {
                                            Column {
                                                Text(text = "#${receipt.receiptNo}", fontWeight = FontWeight.Bold)
                                                Text(text = receipt.paymentMethodLabel, color = Color(0xFF64748B), fontSize = 12.sp)
                                                Text(text = statusText, color = statusColor, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                            }
                                            Column(horizontalAlignment = Alignment.End) {
                                                Text(text = rupiah(receipt.totalAmount), fontWeight = FontWeight.Bold)
                                                Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFFCBD5E1))
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        HistoryTab.SHIFT -> {
                            Column(modifier = Modifier.fillMaxSize()) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(horizontal = 16.dp, vertical = 10.dp),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    OutlinedTextField(
                                        value = "",
                                        onValueChange = {},
                                        modifier = Modifier.weight(1f),
                                        placeholder = { Text("Cari kasir atau tanggal...") },
                                        leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                                        enabled = false,
                                        shape = RoundedCornerShape(16.dp),
                                    )
                                    CircleIconButton(icon = Icons.Default.Tune)
                                }

                                LazyColumn(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .weight(1f),
                                    contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 6.dp, end = 16.dp, bottom = 96.dp),
                                    verticalArrangement = Arrangement.spacedBy(12.dp),
                                ) {
                                    itemsIndexed(shownShifts) { _, shift ->
                                        Card(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .clickable {
                                                    selectedShift = shift
                                                    historyMode = HistoryMode.SHIFT_DETAIL
                                                },
                                            shape = RoundedCornerShape(22.dp),
                                            colors = CardDefaults.cardColors(containerColor = Color.White),
                                        ) {
                                            Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                    Column {
                                                        Text(text = shift.dateLabel, fontWeight = FontWeight.Bold)
                                                        Text(text = shift.timeRange, color = Color(0xFF94A3B8), fontSize = 12.sp)
                                                    }
                                                    Text(
                                                        text = if (shift.isMatched) "Sesuai" else "Selisih",
                                                        color = if (shift.isMatched) Color(0xFF16A34A) else Color(0xFFD97706),
                                                        fontWeight = FontWeight.Bold,
                                                        fontSize = 10.sp,
                                                        modifier = Modifier
                                                            .clip(RoundedCornerShape(999.dp))
                                                            .background(if (shift.isMatched) Color(0xFFDCFCE7) else Color(0xFFFEF3C7))
                                                            .padding(horizontal = 10.dp, vertical = 4.dp),
                                                    )
                                                }

                                                Row(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .clip(RoundedCornerShape(14.dp))
                                                        .background(Color(0xFFF8FAFC))
                                                        .padding(10.dp),
                                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                                    verticalAlignment = Alignment.CenterVertically,
                                                ) {
                                                    Box(
                                                        modifier = Modifier
                                                            .size(34.dp)
                                                            .clip(CircleShape)
                                                            .background(UiPrimary.copy(alpha = 0.2f)),
                                                        contentAlignment = Alignment.Center,
                                                    ) {
                                                        Icon(Icons.Default.Person, contentDescription = null, tint = UiPrimary, modifier = Modifier.size(18.dp))
                                                    }
                                                    Column {
                                                        Text(text = "Kasir", color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 10.sp)
                                                        Text(text = shift.cashierName, fontWeight = FontWeight.Bold)
                                                    }
                                                }

                                                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                    Column {
                                                        Text(text = "Saldo Awal", color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 10.sp)
                                                        Text(text = rupiah(shift.openingBalance), fontWeight = FontWeight.Bold)
                                                    }
                                                    Column(horizontalAlignment = Alignment.End) {
                                                        Text(text = "Saldo Akhir", color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 10.sp)
                                                        Text(text = rupiah(shift.closingBalance), fontWeight = FontWeight.Bold, color = UiPrimary)
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        HistoryTab.LAPORAN -> {
                            ReportHistoryScreen(
                                receipts = shownReceipts,
                                shifts = shownShifts,
                                pendingCount = pendingCount,
                                failedCount = failedCount,
                                canDownloadReport = canDownloadReport,
                                canViewPurchasesReport = canViewPurchasesReport,
                                canViewStockCard = canViewStockCard,
                                canViewFifoLayers = canViewFifoLayers,
                                showAuditLog = showAuditLog,
                                onOpenPurchasesReport = { historyMode = HistoryMode.PURCHASE_REPORT },
                                onOpenStockCard = { historyMode = HistoryMode.STOCK_CARD },
                                onOpenFifoLayers = { historyMode = HistoryMode.FIFO_LAYERS },
                                onOpenAuditLog = { historyMode = HistoryMode.AUDIT_LOG },
                                onDownload = onOpenSync,
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun TransactionDetailScreen(
    receipt: SaleReceiptUi,
    taxPercent: Double,
    onBack: () -> Unit,
    onVoid: () -> Unit,
    onShare: () -> Unit,
    onPrint: () -> Unit,
) {
    val voidStatus = resolveVoidReceiptStatus(receipt.syncStatusLabel)
    val isVoidCompleted = voidStatus == VoidReceiptStatus.COMPLETED
    val isVoidRequested = voidStatus == VoidReceiptStatus.REQUESTED
    val normalizedTax = taxPercent.coerceIn(0.0, 100.0)
    val divisor = (100.0 + normalizedTax).coerceAtLeast(1.0)
    val tax = ((receipt.totalAmount.toDouble() * normalizedTax) / divisor).toLong()
    val subtotal = receipt.totalAmount - tax

    Column(modifier = Modifier.fillMaxSize().background(UiBackground).testTag("sale_detail_screen")) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                Column {
                    Text(text = "Detail Transaksi", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    Text(text = "#${receipt.receiptNo}", color = Color(0xFF94A3B8), fontSize = 11.sp)
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                if (!isVoidCompleted && !isVoidRequested) {
                    CircleIconButton(
                        icon = Icons.Default.Cancel,
                        contentDescription = "Void Transaksi",
                        modifier = Modifier.testTag("void_button"),
                        onClick = onVoid,
                    )
                }
                CircleIconButton(icon = Icons.Default.Share, contentDescription = "Bagikan Transaksi", onClick = onShare)
                CircleIconButton(icon = Icons.Default.Print, contentDescription = "Cetak Transaksi", onClick = onPrint)
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize().testTag("sale_detail_content"),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            if (isVoidCompleted || isVoidRequested) {
                item {
                    Card(
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isVoidRequested) Color(0xFFFFF8E6) else Color(0xFFFFEEF2),
                        ),
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Box(
                                modifier = Modifier.size(34.dp).clip(CircleShape).background(
                                    if (isVoidRequested) Color(0xFFFDE68A) else Color(0xFFFECDD3),
                                ),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(Icons.Default.Cancel, contentDescription = null, tint = Color(0xFFE11D48), modifier = Modifier.size(18.dp))
                            }
                            Column {
                                Text(
                                    text = if (isVoidRequested) "Void sudah diminta" else "Transaksi ini telah dibatalkan",
                                    color = Color(0xFFE11D48),
                                    fontWeight = FontWeight.Bold,
                                )
                                Text(
                                    text = if (isVoidRequested) "Menunggu approval/sinkronisasi dari server." else "Dana telah dikembalikan sepenuhnya.",
                                    color = Color(0xFFFB7185),
                                    fontSize = 11.sp,
                                )
                            }
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Column {
                                Text(text = "Status Pembayaran", color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                Text(
                                    text = when {
                                        isVoidRequested -> "Void Diminta"
                                        isVoidCompleted -> "Sudah Di-refund"
                                        else -> "Lunas"
                                    },
                                    color = when {
                                        isVoidRequested -> Color(0xFFF59E0B)
                                        isVoidCompleted -> Color(0xFFE11D48)
                                        else -> UiPrimary
                                    },
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 11.sp,
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(999.dp))
                                        .background(
                                            when {
                                                isVoidRequested -> Color(0xFFFFF3C4)
                                                isVoidCompleted -> Color(0xFFFFE4E6)
                                                else -> UiPrimary.copy(alpha = 0.15f)
                                            },
                                        )
                                        .testTag("sale_status_label")
                                        .padding(horizontal = 10.dp, vertical = 4.dp),
                                )
                            }
                            Column(horizontalAlignment = Alignment.End) {
                                Text(text = "Waktu", color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                Text(text = "24 Okt 2023, 14:20", fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
                            }
                        }

                        receipt.items.forEachIndexed { idx, item ->
                            if (idx > 0) Spacer(modifier = Modifier.height(6.dp))
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Box(
                                        modifier = Modifier
                                            .size(34.dp)
                                            .clip(RoundedCornerShape(10.dp))
                                            .background(Color(0xFFF1F5F9)),
                                        contentAlignment = Alignment.Center,
                                    ) {
                                        Icon(
                                            imageVector = if (idx % 2 == 0) Icons.Default.Coffee else Icons.Default.BakeryDining,
                                            contentDescription = null,
                                            tint = Color(0xFF94A3B8),
                                            modifier = Modifier.size(18.dp),
                                        )
                                    }
                                    Column {
                                        Text(text = item.name, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        Text(text = "${item.qty.toInt()}x @ ${rupiah(item.lineTotal / item.qty.toLong().coerceAtLeast(1))}", color = Color(0xFF94A3B8), fontSize = 11.sp)
                                    }
                                }
                                Text(text = rupiah(item.lineTotal), fontWeight = FontWeight.Bold, fontSize = 13.sp)
                            }
                        }

                        Spacer(modifier = Modifier.height(4.dp))
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Subtotal", color = Color(0xFF94A3B8))
                            Text(rupiah(subtotal), fontWeight = FontWeight.Medium)
                        }
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Pajak (${normalizedTax.toInt()}%)", color = Color(0xFF94A3B8))
                            Text(rupiah(tax), fontWeight = FontWeight.Medium)
                        }
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Total Akhir", fontWeight = FontWeight.Bold)
                            Text(
                                rupiah(receipt.totalAmount),
                                fontWeight = FontWeight.Bold,
                                color = when {
                                    isVoidRequested -> Color(0xFFF59E0B)
                                    isVoidCompleted -> Color(0xFFE11D48)
                                    else -> UiPrimary
                                },
                                modifier = Modifier.testTag("sale_total_label"),
                            )
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Informasi Pembayaran", color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        PaymentInfoRow("Metode", receipt.paymentMethodLabel, "Kasir", "Andi Wijaya")
                        PaymentInfoRow("Waktu Refund", "24 Okt, 14:45", "Catatan", "Salah input pesanan")
                    }
                }
            }
        }
    }
}

@Composable
private fun ShiftDetailScreen(shift: HistoryShiftUi, canPrintSummary: Boolean, onBack: () -> Unit) {
    val context = LocalContext.current
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(UiBackground)
            .navigationBarsPadding(),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                Column {
                    Text(text = "Detail Shift", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    Text(text = "${shift.dateLabel} • Shift Pagi", color = Color(0xFF64748B), fontSize = 11.sp, fontWeight = FontWeight.Medium)
                }
            }
            Text(
                text = "Selesai",
                fontWeight = FontWeight.Bold,
                fontSize = 11.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color(0xFFF1F5F9))
                    .padding(horizontal = 10.dp, vertical = 6.dp),
            )
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                    ShiftMetricCard("Total Penjualan", "Rp 1.845.000", Icons.Default.Payments, Modifier.weight(1f))
                    ShiftMetricCard("Jml Transaksi", "32", Icons.Default.Inventory2, Modifier.weight(1f))
                }
            }

            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("RINCIAN PEMBAYARAN", color = Color(0xFF94A3B8), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        PaymentSplitRow("Tunai", "20 Transaksi", "Rp 1.250.000", Icons.Default.Payments, Color(0xFF16A34A))
                        PaymentSplitRow("QRIS / E-Wallet", "12 Transaksi", "Rp 595.000", Icons.Default.QrCode2, Color(0xFF8B5CF6))
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text("INFO SHIFT", color = Color(0xFF94A3B8), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        PaymentInfoRow("Kasir", shift.cashierName, "Waktu Mulai", "08:00 WIB")
                        PaymentInfoRow("Waktu Berakhir", "16:00 WIB", "Saldo Awal", rupiah(200000))
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Total Kas di Tangan", fontWeight = FontWeight.Bold)
                            Text("Rp 1.450.000", fontWeight = FontWeight.ExtraBold, color = UiPrimary)
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF7ED))) {
                    Text(
                        text = "\"Semua laporan sesuai, tidak ada selisih kas. Sisa stok kopi susu gula aren tinggal sedikit, sudah diinformasikan ke admin stok.\"",
                        modifier = Modifier.padding(14.dp),
                        color = Color(0xFF6B7280),
                        fontSize = 13.sp,
                    )
                }
            }
        }

        Button(
            onClick = {
                if (!canPrintSummary) {
                    showToastSafe(context, "Cetak ringkasan shift tersedia di Paket M/P")
                    return@Button
                }
                val body = buildString {
                    appendLine("RINGKASAN SHIFT")
                    appendLine("Tanggal: ${shift.dateLabel}")
                    appendLine("Waktu: ${shift.timeRange}")
                    appendLine("Kasir: ${shift.cashierName}")
                    appendLine("Saldo Awal: ${rupiah(shift.openingBalance)}")
                    appendLine("Saldo Akhir: ${rupiah(shift.closingBalance)}")
                    appendLine("Status: ${if (shift.isMatched) "Sesuai" else "Selisih"}")
                }
                printPlainText(context, "Shift-${shift.dateLabel}", body)
            },
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
                .height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = if (canPrintSummary) UiPrimary else Color(0xFFCBD5E1)),
            shape = RoundedCornerShape(16.dp),
        ) {
            Icon(Icons.Default.Print, contentDescription = null)
            Spacer(modifier = Modifier.width(6.dp))
            Text(
                if (canPrintSummary) "Cetak Ringkasan Shift" else "Terkunci di Paket M/P",
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

@Composable
private fun ReportHistoryScreen(
    receipts: List<SaleReceiptUi>,
    shifts: List<HistoryShiftUi>,
    pendingCount: Int,
    failedCount: Int,
    canDownloadReport: Boolean,
    canViewPurchasesReport: Boolean,
    canViewStockCard: Boolean,
    canViewFifoLayers: Boolean,
    showAuditLog: Boolean,
    onOpenPurchasesReport: () -> Unit,
    onOpenStockCard: () -> Unit,
    onOpenFifoLayers: () -> Unit,
    onOpenAuditLog: () -> Unit,
    onDownload: () -> Unit,
) {
    val filterItems = listOf("Hari Ini", "Kemarin", "7 Hari Terakhir", "Pilih Tanggal")
    val topProducts = listOf(
        "Kopi Susu Gula Aren" to "Rp 840k",
        "Roti Bakar Cokelat" to "Rp 570k",
        "Es Teh Manis Jumbo" to "Rp 245k",
        "Dimsum Ayam (4pcs)" to "Rp 435k",
        "Indomie Nyemek" to "Rp 360k",
    )
    val totalSales = receipts.sumOf { it.totalAmount }
    val avgReceipt = if (receipts.isNotEmpty()) totalSales / receipts.size else 0L
    val estimatedCogs = (totalSales * 0.58).toLong()
    val estimatedGrossProfit = (totalSales - estimatedCogs).coerceAtLeast(0L)
    val estimatedOperationalExpense = (totalSales * 0.07).toLong()
    val estimatedNetProfit = (estimatedGrossProfit - estimatedOperationalExpense).coerceAtLeast(0L)
    val newCustomers = (receipts.size / 4).coerceAtLeast(0)

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                filterItems.forEachIndexed { index, label ->
                    Text(
                        text = label,
                        color = if (index == 0) Color.White else Color(0xFF64748B),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (index == 0) UiPrimary else Color.White)
                            .padding(horizontal = 12.dp, vertical = 8.dp),
                    )
                }
            }
        }

        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                ReportMetricCard("Total Penjualan", rupiah(totalSales), "Target: Rp 20jt", Modifier.weight(1f))
                ReportMetricCard("Transaksi", receipts.size.toString(), "Avg: ${rupiah(avgReceipt)}", Modifier.weight(1f))
            }
        }

        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                ReportMetricCard("Laba Kotor Est.", rupiah(estimatedGrossProfit), "HPP Est.: ${rupiah(estimatedCogs)}", Modifier.weight(1f))
                ReportMetricCard("Laba Bersih Est.", rupiah(estimatedNetProfit), "Beban Est.: ${rupiah(estimatedOperationalExpense)}", Modifier.weight(1f))
            }
        }

        item {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                ReportMetricCard("Pelanggan Baru", newCustomers.toString(), "Shift aktif: ${shifts.size}", Modifier.weight(1f))
                ReportMetricCard("Sinkronisasi", "$pendingCount pending", "$failedCount gagal", Modifier.weight(1f))
            }
        }

        item {
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Top Produk", fontWeight = FontWeight.Bold)
                        Text("Lihat Semua", color = UiPrimary, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                    }
                    topProducts.forEachIndexed { idx, pair ->
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier
                                        .size(24.dp)
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(Color(0xFFF1F5F9)),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Text(text = (idx + 1).toString(), fontSize = 10.sp, color = Color(0xFF64748B), fontWeight = FontWeight.Bold)
                                }
                                Column {
                                    Text(text = pair.first, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                    Text(text = "${42 - idx * 4} Terjual", fontSize = 10.sp, color = Color(0xFF94A3B8))
                                }
                            }
                            Text(text = pair.second, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }

        item {
            Button(
                onClick = onDownload,
                enabled = canDownloadReport,
                modifier = Modifier.fillMaxWidth().height(48.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Color.White, contentColor = Color(0xFF334155)),
            ) {
                Icon(Icons.Default.Download, contentDescription = null, tint = UiPrimary)
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    if (canDownloadReport) "Unduh Laporan PDF/Excel" else "Unduh Laporan (Paket M/P)",
                    fontWeight = FontWeight.Bold,
                )
            }
        }

        if (!canDownloadReport) {
            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF7ED))) {
                    Text(
                        text = "Fitur export PDF/CSV tersedia mulai Paket Medium.",
                        modifier = Modifier.padding(14.dp),
                        color = Color(0xFF9A3412),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
            }
        }

        item {
            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(14.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column {
                        Text(text = "Audit Log", fontWeight = FontWeight.Bold)
                        Text(
                            text = if (showAuditLog) "Aktif untuk Paket Pro" else "Terkunci (Paket Pro)",
                            color = Color(0xFF94A3B8),
                            fontSize = 12.sp,
                        )
                    }
                    Text(
                        text = if (showAuditLog) "Pro" else "Upgrade",
                        color = if (showAuditLog) UiPrimary else Color(0xFFD97706),
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.clickable(enabled = showAuditLog) { onOpenAuditLog() },
                    )
                }
            }
        }

        item {
            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    ReportGateRow(
                        title = "Laporan Pembelian",
                        enabled = canViewPurchasesReport,
                        lockedLabel = "Paket M/P",
                        onClick = onOpenPurchasesReport,
                    )
                    ReportGateRow(
                        title = "Kartu Stok",
                        enabled = canViewStockCard,
                        lockedLabel = "Paket Pro",
                        onClick = onOpenStockCard,
                    )
                    ReportGateRow(
                        title = "FIFO Layers",
                        enabled = canViewFifoLayers,
                        lockedLabel = "Paket Pro",
                        onClick = onOpenFifoLayers,
                    )
                }
            }
        }

        item {
            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(text = "Status Sinkronisasi", fontWeight = FontWeight.Bold)
                    Text(text = "Pending: $pendingCount • Gagal: $failedCount", color = Color(0xFF64748B), fontSize = 12.sp)
                }
            }
        }
    }
}

@Composable
private fun HistoryTabButton(text: String, active: Boolean, onClick: () -> Unit) {
    Column(
        modifier = Modifier
            .clickable(onClick = onClick)
            .padding(bottom = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            text = text,
            color = if (active) UiPrimary else Color(0xFF64748B),
            fontWeight = if (active) FontWeight.Bold else FontWeight.SemiBold,
            fontSize = 12.sp,
        )
        Spacer(modifier = Modifier.height(4.dp))
        Box(
            modifier = Modifier
                .height(2.dp)
                .width(56.dp)
                .background(if (active) UiPrimary else Color.Transparent),
        )
    }
}

@Composable
private fun ReportMetricCard(title: String, value: String, hint: String, modifier: Modifier = Modifier) {
    Card(modifier = modifier, shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 10.sp)
            Text(text = value, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp)
            if (hint.isNotBlank()) {
                Text(text = hint, color = UiPrimary, fontWeight = FontWeight.Bold, fontSize = 10.sp)
            }
        }
    }
}

@Composable
private fun PaymentSplitRow(title: String, subtitle: String, value: String, icon: androidx.compose.ui.graphics.vector.ImageVector, tint: Color) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(34.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(tint.copy(alpha = 0.14f)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
            }
            Column {
                Text(title, fontWeight = FontWeight.Bold)
                Text(subtitle, color = Color(0xFF94A3B8), fontSize = 11.sp)
            }
        }
        Text(value, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun ShiftMetricCard(title: String, value: String, icon: androidx.compose.ui.graphics.vector.ImageVector, modifier: Modifier = Modifier) {
    Card(modifier = modifier, shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Icon(icon, contentDescription = null, tint = Color(0xFF94A3B8), modifier = Modifier.size(16.dp))
                Text(text = title, color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 10.sp)
            }
            Text(text = value, fontWeight = FontWeight.ExtraBold, fontSize = 20.sp)
        }
    }
}

@Composable
private fun PaymentInfoRow(leftTitle: String, leftValue: String, rightTitle: String, rightValue: String) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Column {
            Text(leftTitle, color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
            Text(leftValue, fontWeight = FontWeight.Bold, fontSize = 12.sp)
        }
        Column(horizontalAlignment = Alignment.End) {
            Text(rightTitle, color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
            Text(rightValue, fontWeight = FontWeight.Bold, fontSize = 12.sp, textAlign = TextAlign.End)
        }
    }
}

@Composable
fun SettingsScreen(
    storeName: String,
    storeAddress: String,
    appVersion: String,
    activePlan: SubscriptionPlan,
    connectivityState: ConnectivityState,
    pendingCount: Int,
    failedCount: Int,
    isSyncing: Boolean,
    canUseProFinance: Boolean,
    canUsePurchaseManagement: Boolean,
    canUseGovernance: Boolean,
    governanceCurrentRole: String,
    governanceVoidRequiresApproval: Boolean,
    governanceStockAdjustmentRequiresApproval: Boolean,
    governanceStockTransferRequiresApproval: Boolean,
    governanceCashMovementRequiresApproval: Boolean,
    governanceDailySnapshotRequiresApproval: Boolean,
    governancePurchaseMutationRequiresApproval: Boolean,
    isWriteLocked: Boolean,
    taxPercent: Double,
    todaySales: Long,
    todayTransactions: Int,
    grossProfitEstimate: Long,
    cashInTotal: Long,
    cashOutTotal: Long,
    stockValueEstimate: Long,
    syncActionSummaryText: String,
    syncFailedSummaryText: String,
    financeSaving: Boolean,
    financeMessage: String?,
    financeError: String?,
    auditEvents: List<String>,
    purchaseSuppliers: List<SupplierSummary>,
    purchaseProducts: List<PurchaseProductOptionUi>,
    purchaseSaving: Boolean,
    purchaseMessage: String?,
    purchaseError: String?,
    purchaseCreateCanSubmit: Boolean,
    purchaseCreateFieldErrors: Map<String, String>,
    purchaseCreateFormError: String?,
    purchaseReceiveCanSubmit: Boolean,
    purchaseReceiveActionEnabled: Boolean,
    purchaseReceiveFieldErrors: Map<String, String>,
    purchaseReceiveActionError: String?,
    purchaseReturnCanSubmit: Boolean,
    purchaseReturnActionEnabled: Boolean,
    purchaseReturnFieldErrors: Map<String, String>,
    purchaseReturnActionError: String?,
    onCreateSupplier: (String, String?, String?, String?) -> Unit,
    onFinalizePurchase: (PurchaseFinalizeDraftUi) -> Unit,
    onEvaluateFinalizePurchaseDraft: (PurchaseFinalizeDraftUi) -> Unit,
    onEvaluateMarkPurchaseReceivedDraft: (PurchaseReceiveDraftUi) -> Unit,
    onMarkPurchaseReceived: (PurchaseReceiveDraftUi) -> Unit,
    onEvaluateRecordPurchaseReturnDraft: (PurchaseReturnDraftUi) -> Unit,
    onRecordPurchaseReturn: (PurchaseReturnDraftUi) -> Unit,
    onRecordCashMovement: (String, Long, String?) -> Unit,
    onSnapshotDailyReport: () -> Unit,
    onPlanChanged: (SubscriptionPlan) -> Unit,
    onGovernanceRoleChanged: (String) -> Unit,
    onGovernanceVoidApprovalChanged: (Boolean) -> Unit,
    onGovernanceStockAdjustmentApprovalChanged: (Boolean) -> Unit,
    onGovernanceStockTransferApprovalChanged: (Boolean) -> Unit,
    onGovernanceCashMovementApprovalChanged: (Boolean) -> Unit,
    onGovernanceDailySnapshotApprovalChanged: (Boolean) -> Unit,
    onGovernancePurchaseMutationApprovalChanged: (Boolean) -> Unit,
    onToggleWriteLock: (Boolean) -> Unit,
    onSyncNow: () -> Unit,
    onRequestExit: (() -> Unit)? = null,
) {
    var mode by remember { mutableStateOf(SettingsMode.MAIN) }
    BackHandler(enabled = mode != SettingsMode.MAIN || onRequestExit != null) {
        if (mode != SettingsMode.MAIN) {
            mode = SettingsMode.MAIN
        } else {
            onRequestExit?.invoke()
        }
    }

    Surface(modifier = Modifier.fillMaxSize(), color = UiBackground) {
        if (mode == SettingsMode.PRINTER) {
            PrinterReceiptScreen(onBack = { mode = SettingsMode.MAIN })
            return@Surface
        }
        if (mode == SettingsMode.SUBSCRIPTION) {
            SubscriptionPlanScreen(
                activePlan = activePlan,
                onBack = { mode = SettingsMode.MAIN },
                onPlanChanged = onPlanChanged,
            )
            return@Surface
        }
        if (mode == SettingsMode.PURCHASES) {
            PurchaseManagementScreen(
                title = "Pembelian Lengkap",
                suppliers = purchaseSuppliers,
                products = purchaseProducts,
                isSaving = purchaseSaving,
                message = purchaseMessage,
                errorMessage = purchaseError,
                createCanSubmit = purchaseCreateCanSubmit,
                createFieldErrors = purchaseCreateFieldErrors,
                createFormError = purchaseCreateFormError,
                receiveCanSubmit = purchaseReceiveCanSubmit,
                receiveActionEnabled = purchaseReceiveActionEnabled,
                receiveFieldErrors = purchaseReceiveFieldErrors,
                receiveActionError = purchaseReceiveActionError,
                returnCanSubmit = purchaseReturnCanSubmit,
                returnActionEnabled = purchaseReturnActionEnabled,
                returnFieldErrors = purchaseReturnFieldErrors,
                returnActionError = purchaseReturnActionError,
                onCreateSupplier = onCreateSupplier,
                onFinalizePurchase = onFinalizePurchase,
                onEvaluateFinalizePurchaseDraft = onEvaluateFinalizePurchaseDraft,
                onEvaluateMarkPurchaseReceivedDraft = onEvaluateMarkPurchaseReceivedDraft,
                onMarkPurchaseReceived = onMarkPurchaseReceived,
                onEvaluateRecordPurchaseReturnDraft = onEvaluateRecordPurchaseReturnDraft,
                onRecordPurchaseReturn = onRecordPurchaseReturn,
                onBack = { mode = SettingsMode.MAIN },
            )
            return@Surface
        }
        if (mode == SettingsMode.FINANCE) {
            FinancePolicyScreen(
                isWriteLocked = isWriteLocked,
                canUseProFinance = canUseProFinance,
                todaySales = todaySales,
                todayTransactions = todayTransactions,
                grossProfitEstimate = grossProfitEstimate,
                cashInTotal = cashInTotal,
                cashOutTotal = cashOutTotal,
                stockValueEstimate = stockValueEstimate,
                syncActionSummaryText = syncActionSummaryText,
                syncFailedSummaryText = syncFailedSummaryText,
                isSaving = financeSaving,
                message = financeMessage,
                errorMessage = financeError,
                onRecordCashMovement = onRecordCashMovement,
                onSnapshotDailyReport = onSnapshotDailyReport,
                onToggleWriteLock = onToggleWriteLock,
                onBack = { mode = SettingsMode.MAIN },
            )
            return@Surface
        }
        if (mode == SettingsMode.GOVERNANCE) {
            GovernanceScreen(
                auditEvents = auditEvents,
                currentRole = governanceCurrentRole,
                voidRequiresApproval = governanceVoidRequiresApproval,
                stockAdjustmentRequiresApproval = governanceStockAdjustmentRequiresApproval,
                stockTransferRequiresApproval = governanceStockTransferRequiresApproval,
                cashMovementRequiresApproval = governanceCashMovementRequiresApproval,
                dailySnapshotRequiresApproval = governanceDailySnapshotRequiresApproval,
                purchaseMutationRequiresApproval = governancePurchaseMutationRequiresApproval,
                canUseGovernance = canUseGovernance,
                onRoleChanged = onGovernanceRoleChanged,
                onVoidApprovalChanged = onGovernanceVoidApprovalChanged,
                onStockAdjustmentApprovalChanged = onGovernanceStockAdjustmentApprovalChanged,
                onStockTransferApprovalChanged = onGovernanceStockTransferApprovalChanged,
                onCashMovementApprovalChanged = onGovernanceCashMovementApprovalChanged,
                onDailySnapshotApprovalChanged = onGovernanceDailySnapshotApprovalChanged,
                onPurchaseMutationApprovalChanged = onGovernancePurchaseMutationApprovalChanged,
                onBack = { mode = SettingsMode.MAIN },
            )
            return@Surface
        }

        Column(modifier = Modifier.fillMaxSize()) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(bottomStart = 18.dp, bottomEnd = 18.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White),
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        Text(text = "Setelan", fontSize = 22.sp, fontWeight = FontWeight.Bold)
                        Text(text = "Preferensi aplikasi dan operasional", color = Color(0xFF94A3B8), fontSize = 11.sp, fontWeight = FontWeight.Medium)
                    }
                    CircleIconButton(icon = Icons.AutoMirrored.Filled.HelpOutline)
                }
            }

            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 8.dp, end = 16.dp, bottom = 96.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(14.dp),
                            horizontalArrangement = Arrangement.spacedBy(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(64.dp)
                                    .clip(RoundedCornerShape(16.dp))
                                    .background(Color(0xFFE2E8F0)),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(Icons.Default.Person, contentDescription = null, tint = Color(0xFF64748B))
                                Box(
                                    modifier = Modifier
                                        .align(Alignment.BottomEnd)
                                        .size(20.dp)
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(UiPrimary),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Icon(Icons.Default.Verified, contentDescription = null, tint = Color.White, modifier = Modifier.size(14.dp))
                                }
                            }
                            Column(modifier = Modifier.weight(1f)) {
                                Text(text = storeName, fontWeight = FontWeight.Bold)
                                Text(text = storeAddress, color = Color(0xFF94A3B8), fontSize = 12.sp)
                                Spacer(modifier = Modifier.height(6.dp))
                                Button(
                                    onClick = {},
                                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary.copy(alpha = 0.15f), contentColor = UiPrimary),
                                    shape = RoundedCornerShape(999.dp),
                                ) {
                                    Text("Edit Profil", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }

                item {
                    SettingsSection(
                        title = "Sistem & Operasional",
                        items = listOf(
                            Triple(Icons.Default.Settings, "Shift Kasir", ""),
                            Triple(Icons.Default.Person, "Pengguna / Karyawan", ""),
                            Triple(Icons.Default.Print, "Printer Struk", ""),
                            Triple(Icons.Default.Payments, "Metode Pembayaran", ""),
                        ),
                        onItemClick = { index ->
                            if (index == 2) {
                                mode = SettingsMode.PRINTER
                            }
                        },
                    )
                }

                item {
                    SettingsSection(
                        title = "Bisnis",
                        items = listOf(
                            Triple(Icons.Default.Percent, "Pajak", "${taxPercent.toInt()}%"),
                            Triple(Icons.Default.Sell, "Diskon Default", ""),
                            Triple(Icons.Default.Inventory2, "Pembelian Lengkap", if (canUsePurchaseManagement) "Aktif" else "Paket M/P"),
                        ),
                        onItemClick = { index ->
                            if (index == 2 && canUsePurchaseManagement) {
                                mode = SettingsMode.PURCHASES
                            }
                        },
                    )
                }

                item {
                    SettingsSection(
                        title = "Keuangan Pro",
                        items = listOf(
                            Triple(Icons.Default.Payments, "Neraca", if (canUseProFinance) "Aktif" else "Paket Pro"),
                            Triple(Icons.Default.Payments, "Arus Kas", if (canUseProFinance) "Aktif" else "Paket Pro"),
                            Triple(Icons.Default.Settings, "Lock Period", if (isWriteLocked) "ON" else if (canUseProFinance) "OFF" else "Paket Pro"),
                        ),
                        onItemClick = {
                            if (canUseProFinance) {
                                mode = SettingsMode.FINANCE
                            }
                        },
                    )
                }

                item {
                    SettingsSection(
                        title = "Governance",
                        items = listOf(
                            Triple(Icons.Default.Search, "Audit Log", if (canUseGovernance) "Aktif" else "Paket Pro"),
                            Triple(Icons.Default.Person, "Role & Permission", if (canUseGovernance) "Aktif" else "Paket Pro"),
                        ),
                        onItemClick = {
                            if (canUseGovernance) {
                                mode = SettingsMode.GOVERNANCE
                            }
                        },
                    )
                }

                item {
                    SettingsSection(
                        title = "Sinkronisasi",
                        items = listOf(
                            Triple(Icons.Default.Sync, "Status: $connectivityState", if (isSyncing) "Sedang sync" else "Siap"),
                            Triple(Icons.Default.Inventory2, "Pending: $pendingCount", "Gagal: $failedCount"),
                        ),
                        onItemClick = { index ->
                            if (index == 0) {
                                onSyncNow()
                            }
                        },
                    )
                }

                item {
                    SettingsSection(
                        title = "Akun",
                        items = listOf(
                            Triple(Icons.Default.Stars, "Paket Langganan", "${activePlan.code} • ${activePlan.label}"),
                            Triple(Icons.AutoMirrored.Filled.Logout, "Keluar", ""),
                        ),
                        onItemClick = { index ->
                            if (index == 0) {
                                mode = SettingsMode.SUBSCRIPTION
                            }
                        },
                    )
                }

                item {
                    Text(
                        text = "Versi Aplikasi $appVersion",
                        color = Color(0xFF94A3B8),
                        fontSize = 11.sp,
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 20.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun SettingsSection(
    title: String,
    items: List<Triple<androidx.compose.ui.graphics.vector.ImageVector, String, String>>,
    onItemClick: ((Int) -> Unit)? = null,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(text = title.uppercase(Locale.ROOT), color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 10.sp)
        Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
            Column(modifier = Modifier.fillMaxWidth()) {
                items.forEachIndexed { index, item ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { onItemClick?.invoke(index) }
                            .padding(horizontal = 12.dp, vertical = 14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(34.dp)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(UiPrimary.copy(alpha = 0.12f)),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(item.first, contentDescription = null, tint = UiPrimary)
                            }
                            Text(
                                text = item.second,
                                fontWeight = FontWeight.SemiBold,
                                fontSize = 14.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        }

                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp),
                        ) {
                            if (item.third.isNotBlank()) {
                                Text(text = item.third, color = Color(0xFF64748B), fontSize = 12.sp, fontWeight = FontWeight.Medium)
                            }
                            Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFFCBD5E1))
                        }
                    }

                    if (index < items.lastIndex) {
                        Spacer(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(1.dp)
                                .background(Color(0xFFF1F5F9)),
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ProductSectionChip(text: String, active: Boolean, onClick: () -> Unit) {
    Text(
        text = text,
        color = if (active) Color.White else Color(0xFF64748B),
        fontWeight = FontWeight.Bold,
        fontSize = 12.sp,
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(if (active) UiPrimary else Color.White)
            .border(1.dp, if (active) UiPrimary else Color(0xFFE2E8F0), RoundedCornerShape(999.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 9.dp),
    )
}

@Composable
private fun ReportGateRow(title: String, enabled: Boolean, lockedLabel: String) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Text(text = title, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = Color(0xFF334155))
        Text(
            text = if (enabled) "Aktif" else lockedLabel,
            color = if (enabled) UiPrimary else Color(0xFF64748B),
            fontWeight = FontWeight.Bold,
            fontSize = 12.sp,
        )
    }
}

@Composable
private fun ReportGateRow(title: String, enabled: Boolean, lockedLabel: String, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .clickable(enabled = enabled) { onClick() }
            .padding(vertical = 2.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text = title, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = Color(0xFF334155))
        Text(
            text = if (enabled) "Buka" else lockedLabel,
            color = if (enabled) UiPrimary else Color(0xFF64748B),
            fontWeight = FontWeight.Bold,
            fontSize = 12.sp,
        )
    }
}

@Composable
private fun PurchaseManagementScreen(
    title: String,
    suppliers: List<SupplierSummary>,
    products: List<PurchaseProductOptionUi>,
    isSaving: Boolean,
    message: String?,
    errorMessage: String?,
    createCanSubmit: Boolean,
    createFieldErrors: Map<String, String>,
    createFormError: String?,
    receiveCanSubmit: Boolean,
    receiveActionEnabled: Boolean,
    receiveFieldErrors: Map<String, String>,
    receiveActionError: String?,
    returnCanSubmit: Boolean,
    returnActionEnabled: Boolean,
    returnFieldErrors: Map<String, String>,
    returnActionError: String?,
    onCreateSupplier: (String, String?, String?, String?) -> Unit,
    onFinalizePurchase: (PurchaseFinalizeDraftUi) -> Unit,
    onEvaluateFinalizePurchaseDraft: (PurchaseFinalizeDraftUi) -> Unit,
    onEvaluateMarkPurchaseReceivedDraft: (PurchaseReceiveDraftUi) -> Unit,
    onMarkPurchaseReceived: (PurchaseReceiveDraftUi) -> Unit,
    onEvaluateRecordPurchaseReturnDraft: (PurchaseReturnDraftUi) -> Unit,
    onRecordPurchaseReturn: (PurchaseReturnDraftUi) -> Unit,
    onBack: () -> Unit,
) {
    var supplierName by remember { mutableStateOf("") }
    var supplierPhone by remember { mutableStateOf("") }

    var purchaseNo by remember { mutableStateOf("") }
    var selectedSupplierId by remember { mutableStateOf<String?>(null) }
    var showSupplierDropdown by remember { mutableStateOf(false) }
    var showInlineCreateSupplier by remember { mutableStateOf(false) }
    var selectedProductId by remember { mutableStateOf<String?>(null) }
    var showProductPicker by remember { mutableStateOf(false) }
    var productSearchQuery by remember { mutableStateOf("") }
    var qtyInput by remember { mutableStateOf("1") }
    var unitCostInput by remember { mutableStateOf("0") }
    var discountInput by remember { mutableStateOf("0") }
    var purchaseNote by remember { mutableStateOf("") }
    val purchaseItems = remember { mutableStateListOf<PurchaseItemEditorUi>() }
    var purchaseNotice by remember { mutableStateOf<String?>(null) }
    var paymentMethod by remember { mutableStateOf(PurchaseContract.PaymentMethod.CASH) }
    var cashSource by remember { mutableStateOf("kas_shift") }
    var dueDaysInput by remember { mutableStateOf("") }

    var receivePurchaseId by remember { mutableStateOf("") }
    var receiveStatusInput by remember { mutableStateOf(PurchaseContract.Status.APPROVED) }
    val receiveItems = remember {
        mutableStateListOf(
            PurchaseReceiveItemEditorUi(),
        )
    }
    var receiveTransactionDateInput by remember { mutableStateOf("") }

    var returnPurchaseId by remember { mutableStateOf("") }
    var returnStatusInput by remember { mutableStateOf(PurchaseContract.Status.RECEIVED) }
    var returnSupplierIdInput by remember { mutableStateOf("") }
    var returnPurchaseSupplierIdInput by remember { mutableStateOf("") }
    val returnItems = remember {
        mutableStateListOf(
            PurchaseReturnItemEditorUi(),
        )
    }
    var returnTransactionDateInput by remember { mutableStateOf("") }
    var returnReason by remember { mutableStateOf("") }

    var viewMode by remember { mutableStateOf("purchase") }
    var showAdvancedOps by remember { mutableStateOf(false) }
    var showPurchaseSummary by remember { mutableStateOf(false) }
    var showTotalsPanel by remember { mutableStateOf(false) }
    var dismissMessage by remember(message) { mutableStateOf(false) }
    var dismissErrorMessage by remember(errorMessage) { mutableStateOf(false) }
    val qtyValue = qtyInput.replace(',', '.').toDoubleOrNull() ?: 0.0
    val unitCostValue = unitCostInput.toLongOrNull() ?: 0L
    val discountValue = discountInput.toLongOrNull() ?: 0L
    val selectedSupplier = suppliers.firstOrNull { it.localId == selectedSupplierId }
    val selectedProduct = products.firstOrNull { it.localId == selectedProductId }
    val totalPreview = purchaseItems.sumOf { ((it.qty * it.unitCost) - it.discountAmount).coerceAtLeast(0.0) }
    val filteredProducts = remember(products, productSearchQuery) {
        val keyword = productSearchQuery.trim().lowercase()
        products
            .asSequence()
            .filter {
                keyword.isBlank() ||
                    it.name.lowercase().contains(keyword) ||
                    it.categoryName.lowercase().contains(keyword)
            }
            .toList()
    }
    val dueDays = dueDaysInput.toLongOrNull()
    val dueDate = dueDays
        ?.takeIf { it > 0L }
        ?.let { System.currentTimeMillis() + (it * 24L * 60L * 60L * 1000L) }
    val receiveTransactionDate = receiveTransactionDateInput.trim().let { raw ->
        when {
            raw.isBlank() -> null
            else -> raw.toLongOrNull() ?: -1L
        }
    }
    val receiveItemsSnapshot = receiveItems.map { item ->
        Triple(item.productLocalId, item.qtyInput, item.remainingQtyInput)
    }
    val returnTransactionDate = returnTransactionDateInput.trim().let { raw ->
        when {
            raw.isBlank() -> null
            else -> raw.toLongOrNull() ?: -1L
        }
    }
    val returnItemsSnapshot = returnItems.map { item ->
        listOf(
            item.productLocalId,
            item.qtyInput,
            item.priceInput,
            item.remainingQtyInput,
            item.reason,
        )
    }
    val effectivePaymentMethod = PurchaseContract.PaymentMethod.normalizeForCreate(paymentMethod)
    val effectivePaymentStatus = if (effectivePaymentMethod == PurchaseContract.PaymentMethod.TEMPO) {
        PurchaseContract.PaymentStatus.UNPAID
    } else {
        PurchaseContract.PaymentStatus.PAID
    }
    val draftForValidation = PurchaseFinalizeDraftUi(
        purchaseNo = purchaseNo,
        supplierLocalId = selectedSupplierId,
        items = purchaseItems.map { line ->
            PurchaseLineDraftUi(
                productLocalId = line.productLocalId,
                qty = line.qty,
                unitCost = line.unitCost,
                discountAmount = line.discountAmount,
            )
        },
        discountAmount = purchaseItems.sumOf { it.discountAmount.coerceAtLeast(0L) },
        paymentMethod = effectivePaymentMethod,
        paymentStatus = effectivePaymentStatus,
        cashSource = if (effectivePaymentMethod == PurchaseContract.PaymentMethod.CASH) cashSource.ifBlank { "kas_shift" } else null,
        dueDate = if (effectivePaymentMethod == PurchaseContract.PaymentMethod.TEMPO) dueDate else null,
        note = purchaseNote.trim().ifBlank { null },
    )
    val receiveLinesDraft = receiveItems.map { row ->
        PurchaseReceiveLineDraftUi(
            productLocalId = row.productLocalId,
            qty = row.qtyInput.replace(',', '.').toDoubleOrNull() ?: 0.0,
            remainingQty = row.remainingQtyInput.replace(',', '.').toDoubleOrNull() ?: 0.0,
        )
    }
    val receiveDraftForValidation = PurchaseReceiveDraftUi(
        purchaseLocalId = receivePurchaseId,
        purchaseStatus = receiveStatusInput,
        transactionDate = receiveTransactionDate,
        items = receiveLinesDraft,
    )
    val returnLinesDraft = returnItems.map { item ->
        PurchaseReturnLineDraftUi(
            productLocalId = item.productLocalId,
            qty = item.qtyInput.replace(',', '.').toDoubleOrNull() ?: 0.0,
            price = item.priceInput.toLongOrNull() ?: 0L,
            localRemainingQty = item.remainingQtyInput.replace(',', '.').toDoubleOrNull(),
            reason = item.reason.trim().ifBlank { null },
        )
    }
    val returnDraftForValidation = PurchaseReturnDraftUi(
        purchaseLocalId = returnPurchaseId,
        purchaseStatus = returnStatusInput,
        supplierLocalId = returnSupplierIdInput,
        purchaseSupplierLocalId = returnPurchaseSupplierIdInput,
        transactionDate = returnTransactionDate,
        notes = null,
        reason = returnReason,
        items = returnLinesDraft,
    )

    LaunchedEffect(Unit) {
        if (purchaseNo.isBlank()) {
            purchaseNo = "PO-${SimpleDateFormat("yyyyMMdd-HHmmss", Locale.getDefault()).format(Date())}"
        }
    }

    LaunchedEffect(
        selectedSupplierId,
        purchaseItems.map { it.productLocalId to it.qty to it.unitCost },
        paymentMethod,
        cashSource,
        dueDaysInput,
        purchaseNo,
        purchaseNote,
    ) {
        onEvaluateFinalizePurchaseDraft(draftForValidation)
    }

    LaunchedEffect(
        receivePurchaseId,
        receiveStatusInput,
        receiveItemsSnapshot,
        receiveTransactionDateInput,
    ) {
        onEvaluateMarkPurchaseReceivedDraft(receiveDraftForValidation)
    }

    LaunchedEffect(
        returnPurchaseId,
        returnStatusInput,
        returnSupplierIdInput,
        returnPurchaseSupplierIdInput,
        returnReason,
        returnTransactionDateInput,
        returnItemsSnapshot,
    ) {
        onEvaluateRecordPurchaseReturnDraft(returnDraftForValidation)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    listOf(Color(0xFFF7FAF8), Color(0xFFECF2EE), Color(0xFFE9F0EB)),
                ),
            ),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White.copy(alpha = 0.92f))
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Column(modifier = Modifier.weight(1f)) {
                Text(text = title.ifBlank { "Pembelian Stok" }, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Text(text = "PLAN PRO ACTIVE", color = UiPrimary, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            }
            IconButton(onClick = {}) { Icon(Icons.AutoMirrored.Filled.HelpOutline, contentDescription = null, tint = UiPrimary) }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 10.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                listOf(
                    "purchase" to "Input Pembelian",
                    "create" to "Tambah Supplier",
                ).forEach { (key, label) ->
                    val active = viewMode == key
                    Text(
                        text = label,
                        color = if (active) Color.White else Color(0xFF334155),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (active) UiPrimary else Color.White)
                            .border(1.dp, if (active) UiPrimary else Color(0xFFE2E8F0), RoundedCornerShape(999.dp))
                            .clickable { viewMode = key }
                            .padding(horizontal = 12.dp, vertical = 7.dp),
                    )
                }
            }

            if (!message.isNullOrBlank() && !dismissMessage) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 10.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFECFDF3)),
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(start = 12.dp, end = 4.dp, top = 4.dp, bottom = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        Text(
                            text = message,
                            color = Color(0xFF166534),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.weight(1f),
                        )
                        IconButton(onClick = { dismissMessage = true }) {
                            Icon(Icons.Default.Cancel, contentDescription = "Tutup notifikasi", tint = Color(0xFF166534))
                        }
                    }
                }
            }

            if (!errorMessage.isNullOrBlank() && !dismissErrorMessage) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 8.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2)),
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(start = 12.dp, end = 4.dp, top = 4.dp, bottom = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        Text(
                            text = errorMessage,
                            color = Color(0xFFB91C1C),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.weight(1f),
                        )
                        IconButton(onClick = { dismissErrorMessage = true }) {
                            Icon(Icons.Default.Cancel, contentDescription = "Tutup notifikasi", tint = Color(0xFFB91C1C))
                        }
                    }
                }
            }

            when (viewMode) {
                "create" -> {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .verticalScroll(rememberScrollState())
                            .padding(top = 12.dp, bottom = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        Card(shape = RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(text = "Tambah Supplier", fontWeight = FontWeight.Bold)
                                OutlinedTextField(value = supplierName, onValueChange = { supplierName = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Nama Supplier") }, singleLine = true)
                                OutlinedTextField(value = supplierPhone, onValueChange = { supplierPhone = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Nomor HP / WhatsApp") }, singleLine = true)
                                Button(
                                    onClick = {
                                        onCreateSupplier(
                                            supplierName,
                                            supplierPhone.ifBlank { null },
                                            null,
                                            null,
                                        )
                                    },
                                    enabled = !isSaving,
                                    modifier = Modifier.fillMaxWidth().height(52.dp),
                                    shape = RoundedCornerShape(14.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                                ) {
                                    Text(if (isSaving) "Menyimpan..." else "Simpan Supplier", fontWeight = FontWeight.Bold)
                                }
                            }
                        }

                        Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFEFF7F3))) {
                            Text(
                                text = "Supplier yang tersimpan bisa langsung dipilih saat pembelian stok.",
                                color = Color(0xFF334155),
                                fontSize = 12.sp,
                                modifier = Modifier.padding(12.dp),
                            )
                        }
                    }
                }

                "suppliers" -> {
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 12.dp),
                    ) {
                        itemsIndexed(suppliers) { _, supplier ->
                            val active = selectedSupplierId == supplier.localId
                            Card(
                                shape = RoundedCornerShape(18.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { selectedSupplierId = supplier.localId },
                            ) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(42.dp)
                                            .clip(RoundedCornerShape(12.dp))
                                            .background(if (active) UiPrimary.copy(alpha = 0.18f) else Color(0xFFF1F5F9)),
                                        contentAlignment = Alignment.Center,
                                    ) {
                                        Icon(Icons.Default.Inventory2, contentDescription = null, tint = if (active) UiPrimary else Color(0xFF64748B))
                                    }
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(text = supplier.name, fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                                        Text(text = supplier.phone?.ifBlank { "No HP belum diisi" } ?: "No HP belum diisi", fontSize = 12.sp, color = Color(0xFF64748B))
                                    }
                                    Text(
                                        text = if (active) "Aktif" else "Pilih",
                                        color = if (active) UiPrimary else Color(0xFF94A3B8),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                    )
                                }
                            }
                        }

                        if (suppliers.isEmpty()) {
                            item {
                                Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                                    Text(
                                        text = "Belum ada supplier. Tambah supplier dulu.",
                                        color = Color(0xFF64748B),
                                        modifier = Modifier.padding(12.dp),
                                    )
                                }
                            }
                        }
                    }
                }
                else -> {
                    Column(modifier = Modifier.weight(1f).padding(top = 12.dp)) {
                        Column(
                            modifier = Modifier
                                .weight(1f)
                                .verticalScroll(rememberScrollState()),
                            verticalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            Card(
                                shape = RoundedCornerShape(22.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                modifier = Modifier.border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(22.dp)),
                            ) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                    Text(text = "SUPPLIER", fontWeight = FontWeight.Bold, color = Color(0xFF94A3B8), fontSize = 11.sp)
                                    Box(modifier = Modifier.fillMaxWidth()) {
                                        OutlinedTextField(
                                            value = selectedSupplier?.name ?: "",
                                            onValueChange = {},
                                            readOnly = true,
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .testTag("purchase_supplier_picker")
                                                .clickable { showSupplierDropdown = true },
                                            label = { Text("Pilih Supplier") },
                                            placeholder = { Text("Klik untuk pilih supplier") },
                                            singleLine = true,
                                        )
                                        DropdownMenu(
                                            expanded = showSupplierDropdown,
                                            onDismissRequest = { showSupplierDropdown = false },
                                            modifier = Modifier.fillMaxWidth(0.94f),
                                        ) {
                                            suppliers.forEach { supplier ->
                                                DropdownMenuItem(
                                                    text = {
                                                        Column {
                                                            Text(supplier.name, fontWeight = FontWeight.SemiBold)
                                                            Text(
                                                                supplier.phone?.ifBlank { "No HP belum diisi" } ?: "No HP belum diisi",
                                                                fontSize = 11.sp,
                                                                color = Color(0xFF64748B),
                                                            )
                                                        }
                                                    },
                                                    onClick = {
                                                        selectedSupplierId = supplier.localId
                                                        showSupplierDropdown = false
                                                        purchaseNotice = null
                                                    },
                                                )
                                            }
                                            DropdownMenuItem(
                                                text = { Text("+ Tambah supplier baru", color = UiPrimary, fontWeight = FontWeight.Bold) },
                                                onClick = {
                                                    showInlineCreateSupplier = true
                                                    showSupplierDropdown = false
                                                },
                                            )
                                        }
                                    }

                                    Text(
                                        text = selectedSupplier?.phone?.ifBlank { "Tanpa nomor telepon" } ?: "Belum memilih supplier",
                                        color = Color(0xFF64748B),
                                        fontSize = 12.sp,
                                    )

                                    if (showInlineCreateSupplier) {
                                        Card(
                                            shape = RoundedCornerShape(14.dp),
                                            colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)),
                                            modifier = Modifier.fillMaxWidth(),
                                        ) {
                                            Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                OutlinedTextField(
                                                    value = supplierName,
                                                    onValueChange = { supplierName = it },
                                                    modifier = Modifier.fillMaxWidth(),
                                                    label = { Text("Nama Supplier Baru") },
                                                    singleLine = true,
                                                )
                                                OutlinedTextField(
                                                    value = supplierPhone,
                                                    onValueChange = { supplierPhone = it },
                                                    modifier = Modifier.fillMaxWidth(),
                                                    label = { Text("Nomor HP / WhatsApp") },
                                                    singleLine = true,
                                                )
                                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                                                    Button(
                                                        onClick = {
                                                            onCreateSupplier(
                                                                supplierName,
                                                                supplierPhone.ifBlank { null },
                                                                null,
                                                                null,
                                                            )
                                                        },
                                                        enabled = !isSaving && supplierName.isNotBlank(),
                                                        modifier = Modifier.weight(1f),
                                                        shape = RoundedCornerShape(12.dp),
                                                        colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                                                    ) {
                                                        Text("Simpan Supplier")
                                                    }
                                                    TextButton(
                                                        onClick = { showInlineCreateSupplier = false },
                                                        modifier = Modifier.align(Alignment.CenterVertically),
                                                    ) {
                                                        Text("Batal")
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            Card(
                                shape = RoundedCornerShape(22.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                modifier = Modifier.border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(22.dp)),
                            ) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                    Text(text = "DAFTAR PRODUK", fontWeight = FontWeight.Bold, color = Color(0xFF94A3B8), fontSize = 11.sp)
                                    OutlinedTextField(
                                        value = purchaseNo,
                                        onValueChange = { purchaseNo = it },
                                        modifier = Modifier.fillMaxWidth(),
                                        label = { Text("Nomor Purchase (otomatis)") },
                                        singleLine = true,
                                    )
                                    Text(
                                        text = "Nomor purchase sudah terisi otomatis. Bisa diubah bila dibutuhkan.",
                                        color = Color(0xFF64748B),
                                        fontSize = 11.sp,
                                    )
                                    Button(
                                        onClick = { showProductPicker = true },
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .testTag("purchase_product_picker"),
                                        shape = RoundedCornerShape(12.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE2E8F0)),
                                    ) {
                                        Text(
                                            text = selectedProduct?.name ?: "Tambah Item Produk",
                                            color = Color(0xFF0F172A),
                                            fontWeight = FontWeight.Bold,
                                        )
                                    }
                                    selectedProduct?.let { product ->
                                        val validForPurchase = isProductValidForPurchase(product)
                                        Text(
                                            text = "${product.categoryName} • ${modeLabel(normalizeProductMode(product.productMode))} • stok ${product.stockOnHand}",
                                            color = Color(0xFF64748B),
                                            fontSize = 12.sp,
                                        )
                                        if (!validForPurchase) {
                                            Card(
                                                shape = RoundedCornerShape(12.dp),
                                                colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2)),
                                                modifier = Modifier.fillMaxWidth(),
                                            ) {
                                                Row(
                                                    modifier = Modifier.fillMaxWidth().padding(start = 10.dp, end = 4.dp, top = 4.dp, bottom = 4.dp),
                                                    verticalAlignment = Alignment.CenterVertically,
                                                ) {
                                                    Text(
                                                        text = "Produk resep tidak dapat dibeli sebagai stok.",
                                                        color = Color(0xFFB91C1C),
                                                        fontSize = 12.sp,
                                                        fontWeight = FontWeight.SemiBold,
                                                        modifier = Modifier.weight(1f),
                                                    )
                                                    IconButton(onClick = { selectedProductId = null }) {
                                                        Icon(Icons.Default.Cancel, contentDescription = "Tutup peringatan", tint = Color(0xFFB91C1C))
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Text(text = "Qty", fontWeight = FontWeight.SemiBold, color = Color(0xFF64748B), fontSize = 12.sp)
                                        CircleIconButton(
                                            icon = Icons.Default.Remove,
                                            modifier = Modifier.testTag("purchase_qty_minus"),
                                            onClick = {
                                                val current = qtyInput.replace(',', '.').toDoubleOrNull() ?: 0.0
                                                qtyInput = ((current - 1.0).coerceAtLeast(1.0)).toInt().toString()
                                            },
                                        )
                                        OutlinedTextField(
                                            value = qtyInput,
                                            onValueChange = { qtyInput = it },
                                            modifier = Modifier.width(92.dp),
                                            singleLine = true,
                                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                        )
                                        CircleIconButton(
                                            icon = Icons.Default.Add,
                                            modifier = Modifier.testTag("purchase_qty_plus"),
                                            onClick = {
                                                val current = qtyInput.replace(',', '.').toDoubleOrNull() ?: 0.0
                                                qtyInput = (current + 1.0).toInt().toString()
                                            },
                                        )
                                    }

                                    OutlinedTextField(
                                        value = unitCostInput,
                                        onValueChange = { unitCostInput = it },
                                        modifier = Modifier.fillMaxWidth().testTag("purchase_price_input"),
                                        label = { Text("Harga Beli") },
                                        singleLine = true,
                                    )
                                    OutlinedTextField(
                                        value = discountInput,
                                        onValueChange = { discountInput = it },
                                        modifier = Modifier.fillMaxWidth(),
                                        label = { Text("Diskon Item (opsional)") },
                                        singleLine = true,
                                    )

                                    Button(
                                        onClick = {
                                            val currentProduct = products.firstOrNull { it.localId == selectedProductId }
                                            if (currentProduct == null) {
                                                purchaseNotice = "Produk tidak valid untuk pembelian"
                                                return@Button
                                            }
                                            val validForPurchase = isProductValidForPurchase(currentProduct)
                                            Log.d(
                                                "PurchaseValidation",
                                                "select productLocalId=${currentProduct.localId} itemType=${currentProduct.itemType.orEmpty()} productMode=${currentProduct.productMode.orEmpty()} valid=$validForPurchase",
                                            )
                                            if (!validForPurchase) {
                                                purchaseNotice = "Produk resep tidak dapat dibeli sebagai stok. Stok produk resep berasal dari bahan baku"
                                                return@Button
                                            }
                                            if (qtyValue <= 0.0) {
                                                purchaseNotice = "Qty tidak valid"
                                                return@Button
                                            }
                                            if (unitCostValue <= 0L) {
                                                purchaseNotice = "Harga beli belum diisi"
                                                return@Button
                                            }
                                            val existingIndex = purchaseItems.indexOfFirst { it.productLocalId == currentProduct.localId }
                                            if (existingIndex >= 0) {
                                                val previous = purchaseItems[existingIndex]
                                                purchaseItems[existingIndex] = previous.copy(
                                                    qty = previous.qty + qtyValue,
                                                    unitCost = unitCostValue,
                                                    discountAmount = discountValue.coerceAtLeast(0L),
                                                )
                                            } else {
                                                purchaseItems.add(
                                                    PurchaseItemEditorUi(
                                                        productLocalId = currentProduct.localId,
                                                        productName = currentProduct.name,
                                                        categoryName = currentProduct.categoryName,
                                                        itemType = currentProduct.itemType,
                                                        productMode = currentProduct.productMode,
                                                        stockOnHand = currentProduct.stockOnHand,
                                                        qty = qtyValue,
                                                        unitCost = unitCostValue,
                                                        discountAmount = discountValue.coerceAtLeast(0L),
                                                    ),
                                                )
                                            }
                                            selectedProductId = null
                                            qtyInput = "1"
                                            unitCostInput = "0"
                                            discountInput = "0"
                                            purchaseNotice = null
                                        },
                                        enabled = !isSaving,
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(14.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                                    ) {
                                        Text("Tambah ke Daftar Produk", fontWeight = FontWeight.Bold)
                                    }

                                    OutlinedTextField(
                                        value = purchaseNote,
                                        onValueChange = { purchaseNote = it },
                                        modifier = Modifier.fillMaxWidth().testTag("purchase_note_input"),
                                        label = { Text("Catatan (opsional)") },
                                        singleLine = false,
                                    )
                                    Text(
                                        text = "Pembayaran diatur di Ringkasan Pembelian setelah klik simpan.",
                                        color = Color(0xFF475569),
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }
                            }

                            if (!purchaseNotice.isNullOrBlank()) {
                                Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2))) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth().padding(start = 10.dp, end = 4.dp, top = 4.dp, bottom = 4.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    ) {
                                        Text(
                                            text = purchaseNotice.orEmpty(),
                                            color = Color(0xFFB91C1C),
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            modifier = Modifier.weight(1f),
                                        )
                                        IconButton(onClick = { purchaseNotice = null }) {
                                            Icon(Icons.Default.Cancel, contentDescription = "Tutup peringatan", tint = Color(0xFFB91C1C))
                                        }
                                    }
                                }
                            }

                            purchaseItems.forEachIndexed { index, item ->
                                Card(
                                    shape = RoundedCornerShape(20.dp),
                                    colors = CardDefaults.cardColors(containerColor = Color.White),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(20.dp))
                                        .testTag("purchase_product_item"),
                                ) {
                                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                            Row(
                                                modifier = Modifier.weight(1f),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(40.dp)
                                                        .clip(RoundedCornerShape(12.dp))
                                                        .background(Color(0xFFF1F5F9)),
                                                    contentAlignment = Alignment.Center,
                                                ) {
                                                    Icon(Icons.Default.Inventory2, contentDescription = null, tint = Color(0xFF64748B))
                                                }
                                                Column(modifier = Modifier.weight(1f)) {
                                                    Text(item.productName, fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                                                    Text(
                                                        "${item.categoryName} • stok ${item.stockOnHand}",
                                                        color = Color(0xFF64748B),
                                                        fontSize = 12.sp,
                                                    )
                                                }
                                            }
                                            Text(
                                                text = "Hapus",
                                                color = Color(0xFFB91C1C),
                                                fontWeight = FontWeight.Bold,
                                                modifier = Modifier.clickable { purchaseItems.removeAt(index) },
                                            )
                                        }
                                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                            CircleIconButton(
                                                icon = Icons.Default.Remove,
                                                modifier = Modifier.testTag("purchase_qty_minus"),
                                                onClick = {
                                                    val nextQty = (item.qty - 1.0).coerceAtLeast(1.0)
                                                    purchaseItems[index] = item.copy(qty = nextQty)
                                                },
                                            )
                                            Text(text = item.qty.toString(), fontWeight = FontWeight.Bold, color = Color(0xFF334155))
                                            CircleIconButton(
                                                icon = Icons.Default.Add,
                                                modifier = Modifier.testTag("purchase_qty_plus"),
                                                onClick = {
                                                    purchaseItems[index] = item.copy(qty = item.qty + 1.0)
                                                },
                                            )
                                        }
                                        Text(
                                            text = "Harga Beli",
                                            color = Color(0xFF94A3B8),
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                        )
                                        OutlinedTextField(
                                            value = item.unitCost.toString(),
                                            onValueChange = { value ->
                                                purchaseItems[index] = item.copy(unitCost = value.toLongOrNull() ?: 0L)
                                            },
                                            modifier = Modifier.fillMaxWidth().testTag("purchase_price_input"),
                                            label = { Text("Harga Beli") },
                                            singleLine = true,
                                        )
                                    }
                                }
                            }

                            Spacer(modifier = Modifier.height(12.dp))
                        }

                        Card(
                            shape = RoundedCornerShape(topStart = 22.dp, topEnd = 22.dp),
                            colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.98f)),
                            modifier = Modifier
                                .fillMaxWidth()
                                .navigationBarsPadding(),
                        ) {
                            Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(
                                    text = if (showTotalsPanel) "Sembunyikan Total Pembelian" else "Lihat Total Pembelian",
                                    color = UiPrimary,
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 12.sp,
                                    modifier = Modifier.clickable { showTotalsPanel = !showTotalsPanel },
                                )

                                if (showTotalsPanel) {
                                    Text(
                                        text = rupiah(totalPreview.toLong()),
                                        fontWeight = FontWeight.ExtraBold,
                                        fontSize = 22.sp,
                                        color = Color(0xFF0F172A),
                                        modifier = Modifier.testTag("purchase_total_value"),
                                    )
                                }

                                createFormError?.let { formError ->
                                    Text(
                                        text = formError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                Button(
                                    onClick = {
                                        if (selectedSupplierId.isNullOrBlank()) {
                                            purchaseNotice = "Supplier belum dipilih"
                                            return@Button
                                        }
                                        if (purchaseItems.isEmpty()) {
                                            purchaseNotice = "Item pembelian kosong"
                                            return@Button
                                        }
                                        val invalidItem = purchaseItems.firstOrNull { !isProductValidForPurchase(it.itemType, it.productMode) }
                                        if (invalidItem != null) {
                                            purchaseNotice = "Produk resep tidak dapat dibeli sebagai stok"
                                            Log.d(
                                                "PurchaseValidation",
                                                "save_blocked productLocalId=${invalidItem.productLocalId} itemType=${invalidItem.itemType.orEmpty()} productMode=${invalidItem.productMode.orEmpty()} result=blocked",
                                            )
                                            return@Button
                                        }
                                        if (purchaseItems.any { it.qty <= 0.0 }) {
                                            purchaseNotice = "Qty tidak valid"
                                            return@Button
                                        }
                                        if (purchaseItems.any { it.unitCost <= 0L }) {
                                            purchaseNotice = "Harga beli belum diisi"
                                            return@Button
                                        }

                                        purchaseItems.forEach { item ->
                                            Log.d(
                                                "PurchaseValidation",
                                                "save_line productLocalId=${item.productLocalId} itemType=${item.itemType.orEmpty()} productMode=${item.productMode.orEmpty()} result=allowed",
                                            )
                                        }

                                        showPurchaseSummary = true
                                    },
                                    enabled = !isSaving && createCanSubmit,
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(56.dp)
                                        .testTag("save_purchase_button"),
                                    shape = RoundedCornerShape(18.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                                ) {
                                    Text(if (isSaving) "Memproses..." else "Simpan & Lanjutkan", fontWeight = FontWeight.Bold)
                                }
                            }
                        }

                        if (showProductPicker) {
                            AlertDialog(
                                onDismissRequest = { showProductPicker = false },
                                confirmButton = {
                                    TextButton(onClick = { showProductPicker = false }) {
                                        Text("Tutup")
                                    }
                                },
                                title = { Text("Pilih Produk Pembelian") },
                                text = {
                                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                        OutlinedTextField(
                                            value = productSearchQuery,
                                            onValueChange = { productSearchQuery = it },
                                            label = { Text("Cari produk") },
                                            singleLine = true,
                                            modifier = Modifier.fillMaxWidth(),
                                        )
                                        LazyColumn(modifier = Modifier.heightIn(max = 320.dp)) {
                                            itemsIndexed(filteredProducts) { _, product ->
                                                val validForPurchase = isProductValidForPurchase(product)
                                                Column(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .clip(RoundedCornerShape(8.dp))
                                                        .clickable {
                                                            Log.d(
                                                                "PurchaseValidation",
                                                                "picker_click productLocalId=${product.localId} itemType=${product.itemType.orEmpty()} productMode=${product.productMode.orEmpty()} result=${if (validForPurchase) "allowed" else "blocked"}",
                                                            )
                                                            if (!validForPurchase) {
                                                                purchaseNotice = "Produk resep tidak dapat dibeli sebagai stok. Stok produk resep berasal dari bahan baku"
                                                                return@clickable
                                                            }
                                                            selectedProductId = product.localId
                                                            showProductPicker = false
                                                            purchaseNotice = null
                                                        }
                                                        .padding(horizontal = 8.dp, vertical = 10.dp)
                                                        .testTag("purchase_product_item"),
                                                ) {
                                                    Text(product.name, fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                                                    Text(
                                                        "${product.categoryName} • ${modeLabel(normalizeProductMode(product.productMode))}",
                                                        color = Color(0xFF64748B),
                                                        fontSize = 12.sp,
                                                    )
                                                    if (!validForPurchase) {
                                                        Text(
                                                            "Produk tidak valid untuk pembelian",
                                                            color = Color(0xFFB91C1C),
                                                            fontSize = 11.sp,
                                                            fontWeight = FontWeight.SemiBold,
                                                        )
                                                    }
                                                }
                                            }
                                        }
                                    }
                                },
                            )
                        }

                        if (showPurchaseSummary) {
                            val subtotal = purchaseItems.sumOf { it.qty * it.unitCost }
                            val totalDiscount = purchaseItems.sumOf { it.discountAmount.coerceAtLeast(0L).toDouble() }
                            val taxable = (subtotal - totalDiscount).coerceAtLeast(0.0)
                            val totalTax = taxable * 0.11
                            val grandTotal = (taxable + totalTax).toLong()
                            val selectedItemQty = purchaseItems.sumOf { it.qty }.toInt()

                            androidx.compose.ui.window.Dialog(
                                onDismissRequest = { showPurchaseSummary = false },
                                properties = androidx.compose.ui.window.DialogProperties(usePlatformDefaultWidth = false),
                            ) {
                                Card(
                                    modifier = Modifier
                                        .fillMaxSize()
                                        .padding(top = 22.dp),
                                    shape = RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp),
                                    colors = CardDefaults.cardColors(containerColor = Color(0xFFF7FAF8)),
                                ) {
                                    Column(modifier = Modifier.fillMaxSize().padding(12.dp)) {
                                        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                            IconButton(onClick = { showPurchaseSummary = false }) {
                                                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null)
                                            }
                                            Text(text = "Ringkasan Pembelian", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                                        }

                                        Text(
                                            text = "DAFTAR ITEM (${purchaseItems.size})",
                                            color = Color(0xFF64748B),
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 11.sp,
                                            modifier = Modifier.padding(start = 8.dp, top = 4.dp, bottom = 8.dp),
                                        )

                                        Column(
                                            modifier = Modifier
                                                .weight(1f)
                                                .verticalScroll(rememberScrollState()),
                                            verticalArrangement = Arrangement.spacedBy(10.dp),
                                        ) {
                                            purchaseItems.forEachIndexed { index, item ->
                                                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                                                    Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                        Row(
                                                            modifier = Modifier.fillMaxWidth(),
                                                            horizontalArrangement = Arrangement.SpaceBetween,
                                                            verticalAlignment = Alignment.CenterVertically,
                                                        ) {
                                                            Column(modifier = Modifier.weight(1f)) {
                                                                Text(item.productName, fontWeight = FontWeight.Bold, color = Color(0xFF0F172A), fontSize = 14.sp)
                                                                Text("Stok saat ini: ${item.stockOnHand.toInt()} pcs", color = Color(0xFF64748B), fontSize = 11.sp)
                                                            }
                                                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                                                CircleIconButton(icon = Icons.Default.Remove, onClick = {
                                                                    val nextQty = (item.qty - 1.0).coerceAtLeast(1.0)
                                                                    purchaseItems[index] = item.copy(qty = nextQty)
                                                                })
                                                                Text(item.qty.toInt().toString(), fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                                                                CircleIconButton(icon = Icons.Default.Add, background = UiPrimary, tint = Color.White, onClick = {
                                                                    purchaseItems[index] = item.copy(qty = item.qty + 1.0)
                                                                })
                                                            }
                                                        }

                                                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                                                            OutlinedTextField(
                                                                value = item.unitCost.toString(),
                                                                onValueChange = { value ->
                                                                    purchaseItems[index] = item.copy(unitCost = value.toLongOrNull() ?: 0L)
                                                                },
                                                                modifier = Modifier.weight(1f),
                                                                label = { Text("Harga Beli") },
                                                                singleLine = true,
                                                            )
                                                            OutlinedTextField(
                                                                value = item.discountAmount.toString(),
                                                                onValueChange = { value ->
                                                                    purchaseItems[index] = item.copy(discountAmount = value.toLongOrNull() ?: 0L)
                                                                },
                                                                modifier = Modifier.weight(1f),
                                                                label = { Text("Diskon") },
                                                                singleLine = true,
                                                            )
                                                        }
                                                    }
                                                }
                                            }

                                            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                                                Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                    Text("METODE PEMBAYARAN KE SUPPLIER", color = Color(0xFF64748B), fontWeight = FontWeight.Bold, fontSize = 11.sp)
                                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                                                        listOf(
                                                            Triple("Tunai", PurchaseContract.PaymentMethod.CASH, "kas_shift"),
                                                            Triple("Tempo", PurchaseContract.PaymentMethod.TEMPO, "hutang"),
                                                            Triple("Transfer", PurchaseContract.PaymentMethod.CASH, "bank_transfer"),
                                                        ).forEach { (label, method, source) ->
                                                            val active = paymentMethod == method && (method != PurchaseContract.PaymentMethod.CASH || cashSource == source || (label == "Tunai" && cashSource == "kas_shift"))
                                                            Text(
                                                                text = label,
                                                                color = if (active) Color.White else Color(0xFF334155),
                                                                fontWeight = FontWeight.Bold,
                                                                fontSize = 12.sp,
                                                                modifier = Modifier
                                                                    .weight(1f)
                                                                    .clip(RoundedCornerShape(12.dp))
                                                                    .background(if (active) UiPrimary else Color(0xFFF1F5F9))
                                                                    .clickable {
                                                                        paymentMethod = method
                                                                        if (method == PurchaseContract.PaymentMethod.CASH) {
                                                                            cashSource = source
                                                                        }
                                                                    }
                                                                    .padding(vertical = 12.dp),
                                                                textAlign = TextAlign.Center,
                                                            )
                                                        }
                                                    }

                                                    if (paymentMethod == PurchaseContract.PaymentMethod.TEMPO) {
                                                        OutlinedTextField(
                                                            value = dueDaysInput,
                                                            onValueChange = { dueDaysInput = it },
                                                            modifier = Modifier.fillMaxWidth(),
                                                            label = { Text("Jatuh Tempo (hari)") },
                                                            singleLine = true,
                                                        )
                                                    }

                                                    if (paymentMethod == PurchaseContract.PaymentMethod.CASH && cashSource != "kas_shift") {
                                                        OutlinedTextField(
                                                            value = cashSource,
                                                            onValueChange = { cashSource = it },
                                                            modifier = Modifier.fillMaxWidth(),
                                                            label = { Text("Sumber Transfer") },
                                                            singleLine = true,
                                                        )
                                                    }

                                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                        Text("Subtotal", color = Color(0xFF64748B))
                                                        Text(rupiah(subtotal.toLong()), fontWeight = FontWeight.Bold)
                                                    }
                                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                        Text("Total Diskon", color = Color(0xFF64748B))
                                                        Text("- ${rupiah(totalDiscount.toLong())}", color = Color(0xFFB91C1C), fontWeight = FontWeight.Bold)
                                                    }
                                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                        Text("Total Pajak (11%)", color = Color(0xFF64748B))
                                                        Text(rupiah(totalTax.toLong()), fontWeight = FontWeight.Bold)
                                                    }
                                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                        Text("TOTAL AKHIR", color = Color(0xFF64748B), fontWeight = FontWeight.Bold)
                                                        Text(rupiah(grandTotal), fontWeight = FontWeight.ExtraBold, fontSize = 22.sp)
                                                    }
                                                    Text(
                                                        text = "ITEM TERPILIH $selectedItemQty PCS",
                                                        color = Color(0xFF94A3B8),
                                                        fontWeight = FontWeight.Bold,
                                                        fontSize = 10.sp,
                                                    )
                                                }
                                            }
                                        }

                                        Button(
                                            onClick = {
                                                val dueDateRequired = effectivePaymentMethod == PurchaseContract.PaymentMethod.TEMPO
                                                if (dueDateRequired && dueDate == null) {
                                                    purchaseNotice = "Jatuh tempo wajib diisi untuk tempo/belum dibayar"
                                                    return@Button
                                                }
                                                onFinalizePurchase(draftForValidation)
                                                showPurchaseSummary = false
                                            },
                                            enabled = !isSaving && createCanSubmit,
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .height(52.dp),
                                            shape = RoundedCornerShape(14.dp),
                                            colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                                        ) {
                                            Text(if (isSaving) "Memproses..." else "Konfirmasi & Simpan Stok", fontWeight = FontWeight.Bold)
                                        }
                                    }
                                }
                            }
                        }

                        Text(
                            text = if (showAdvancedOps) "Sembunyikan Operasi Lanjutan" else "Tampilkan Operasi Lanjutan",
                            color = UiPrimary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier
                                .padding(top = 8.dp)
                                .clickable { showAdvancedOps = !showAdvancedOps },
                        )

                        if (showAdvancedOps) Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(text = "Operasi Lanjutan", fontWeight = FontWeight.Bold)
                                OutlinedTextField(value = receivePurchaseId, onValueChange = { receivePurchaseId = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Purchase Local ID (Receive)") }, singleLine = true)
                                receiveFieldErrors["purchase_id"]?.let { receivePurchaseIdError ->
                                    Text(
                                        text = receivePurchaseIdError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                Text(text = "Status Purchase", fontWeight = FontWeight.Bold, color = Color(0xFF334155), fontSize = 12.sp)
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.horizontalScroll(rememberScrollState())) {
                                    listOf(
                                        PurchaseContract.Status.APPROVED to "approved",
                                        PurchaseContract.Status.PARTIALLY_RECEIVED to "partially_received",
                                    ).forEach { (key, label) ->
                                        val active = receiveStatusInput == key
                                        Text(
                                            text = label,
                                            color = if (active) Color.White else Color(0xFF334155),
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            modifier = Modifier
                                                .clip(RoundedCornerShape(999.dp))
                                                .background(if (active) UiPrimary else Color(0xFFF1F5F9))
                                                .clickable { receiveStatusInput = key }
                                                .padding(horizontal = 10.dp, vertical = 6.dp),
                                        )
                                    }
                                }
                                receiveFieldErrors["status"]?.let { receiveStatusError ->
                                    Text(
                                        text = receiveStatusError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                Text(text = "Item Receive", fontWeight = FontWeight.Bold, color = Color(0xFF334155), fontSize = 12.sp)
                                receiveItems.forEachIndexed { index, item ->
                                    Card(shape = RoundedCornerShape(10.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC))) {
                                        Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                            OutlinedTextField(
                                                value = item.productLocalId,
                                                onValueChange = { value ->
                                                    receiveItems[index] = item.copy(productLocalId = value)
                                                },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Product Local ID") },
                                                singleLine = true,
                                            )
                                            receiveFieldErrors["item_${index}.product_id"]?.let { receiveProductError ->
                                                Text(
                                                    text = receiveProductError,
                                                    color = Color(0xFFB91C1C),
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                )
                                            }

                                            OutlinedTextField(
                                                value = item.qtyInput,
                                                onValueChange = { value ->
                                                    receiveItems[index] = item.copy(qtyInput = value)
                                                },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Qty Receive") },
                                                singleLine = true,
                                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                            )
                                            receiveFieldErrors["item_${index}.qty"]?.let { receiveQtyError ->
                                                Text(
                                                    text = receiveQtyError,
                                                    color = Color(0xFFB91C1C),
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                )
                                            }

                                            OutlinedTextField(
                                                value = item.remainingQtyInput,
                                                onValueChange = { value ->
                                                    receiveItems[index] = item.copy(remainingQtyInput = value)
                                                },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Remaining Qty") },
                                                singleLine = true,
                                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                            )
                                            receiveFieldErrors["item_${index}.remaining"]?.let { receiveRemainingError ->
                                                Text(
                                                    text = receiveRemainingError,
                                                    color = Color(0xFFB91C1C),
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                )
                                            }

                                            if (receiveItems.size > 1) {
                                                Text(
                                                    text = "Hapus item",
                                                    color = Color(0xFFB91C1C),
                                                    fontWeight = FontWeight.Bold,
                                                    fontSize = 11.sp,
                                                    modifier = Modifier.clickable { receiveItems.removeAt(index) },
                                                )
                                            }
                                        }
                                    }
                                }

                                Text(
                                    text = "Tambah item receive",
                                    color = UiPrimary,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    modifier = Modifier.clickable { receiveItems.add(PurchaseReceiveItemEditorUi()) },
                                )

                                OutlinedTextField(
                                    value = receiveTransactionDateInput,
                                    onValueChange = { receiveTransactionDateInput = it },
                                    modifier = Modifier.fillMaxWidth(),
                                    label = { Text("Transaction Date (epoch ms, opsional)") },
                                    singleLine = true,
                                )
                                receiveFieldErrors["transaction_date"]?.let { receiveDateError ->
                                    Text(
                                        text = receiveDateError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                receiveFieldErrors["items"]?.let { receiveItemsError ->
                                    Text(
                                        text = receiveItemsError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                Button(
                                    onClick = { onMarkPurchaseReceived(receiveDraftForValidation) },
                                    enabled = !isSaving && receiveActionEnabled && receiveCanSubmit,
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(12.dp),
                                ) {
                                    Text(if (isSaving) "Memproses..." else "Tandai Received")
                                }

                                receiveActionError?.let { receiveActionErrorText ->
                                    Text(
                                        text = receiveActionErrorText,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                OutlinedTextField(value = returnPurchaseId, onValueChange = { returnPurchaseId = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Purchase Local ID (Retur)") }, singleLine = true)
                                returnFieldErrors["purchase_id"]?.let { returnPurchaseIdError ->
                                    Text(
                                        text = returnPurchaseIdError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                Text(text = "Status Return", fontWeight = FontWeight.Bold, color = Color(0xFF334155), fontSize = 12.sp)
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.horizontalScroll(rememberScrollState())) {
                                    listOf(
                                        PurchaseContract.Status.RECEIVED,
                                        PurchaseContract.Status.RECEIVED_UNPAID,
                                        PurchaseContract.Status.RECEIVED_PARTIAL_PAID,
                                        PurchaseContract.Status.RETURNED_PARTIAL,
                                    ).forEach { statusKey ->
                                        val active = returnStatusInput == statusKey
                                        Text(
                                            text = statusKey,
                                            color = if (active) Color.White else Color(0xFF334155),
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            modifier = Modifier
                                                .clip(RoundedCornerShape(999.dp))
                                                .background(if (active) UiPrimary else Color(0xFFF1F5F9))
                                                .clickable { returnStatusInput = statusKey }
                                                .padding(horizontal = 10.dp, vertical = 6.dp),
                                        )
                                    }
                                }
                                returnFieldErrors["status"]?.let { returnStatusError ->
                                    Text(
                                        text = returnStatusError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                OutlinedTextField(value = returnSupplierIdInput, onValueChange = { returnSupplierIdInput = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Supplier Local ID") }, singleLine = true)
                                returnFieldErrors["supplier_id"]?.let { returnSupplierError ->
                                    Text(
                                        text = returnSupplierError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                OutlinedTextField(value = returnPurchaseSupplierIdInput, onValueChange = { returnPurchaseSupplierIdInput = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Supplier Purchase Asal") }, singleLine = true)
                                returnFieldErrors["purchase_supplier_id"]?.let { returnPurchaseSupplierError ->
                                    Text(
                                        text = returnPurchaseSupplierError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }
                                returnFieldErrors["supplier_match"]?.let { returnSupplierMatchError ->
                                    Text(
                                        text = returnSupplierMatchError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                Text(text = "Item Retur", fontWeight = FontWeight.Bold, color = Color(0xFF334155), fontSize = 12.sp)
                                returnItems.forEachIndexed { index, item ->
                                    Card(shape = RoundedCornerShape(10.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC))) {
                                        Column(modifier = Modifier.padding(10.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                            OutlinedTextField(
                                                value = item.productLocalId,
                                                onValueChange = { value -> returnItems[index] = item.copy(productLocalId = value) },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Product Local ID") },
                                                singleLine = true,
                                            )
                                            returnFieldErrors["item_${index}.product_id"]?.let { returnProductError ->
                                                Text(text = returnProductError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                            }

                                            OutlinedTextField(
                                                value = item.qtyInput,
                                                onValueChange = { value -> returnItems[index] = item.copy(qtyInput = value) },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Qty Retur") },
                                                singleLine = true,
                                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                            )
                                            returnFieldErrors["item_${index}.qty"]?.let { returnQtyError ->
                                                Text(text = returnQtyError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                            }

                                            OutlinedTextField(
                                                value = item.priceInput,
                                                onValueChange = { value -> returnItems[index] = item.copy(priceInput = value) },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Harga Retur") },
                                                singleLine = true,
                                            )
                                            returnFieldErrors["item_${index}.price"]?.let { returnPriceError ->
                                                Text(text = returnPriceError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                            }

                                            OutlinedTextField(
                                                value = item.remainingQtyInput,
                                                onValueChange = { value -> returnItems[index] = item.copy(remainingQtyInput = value) },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Remaining Qty Lokal (opsional)") },
                                                singleLine = true,
                                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                                            )
                                            returnFieldErrors["item_${index}.remaining"]?.let { returnRemainingError ->
                                                Text(text = returnRemainingError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                            }

                                            OutlinedTextField(
                                                value = item.reason,
                                                onValueChange = { value -> returnItems[index] = item.copy(reason = value) },
                                                modifier = Modifier.fillMaxWidth(),
                                                label = { Text("Reason Item (opsional)") },
                                                singleLine = true,
                                            )

                                            if (returnItems.size > 1) {
                                                Text(
                                                    text = "Hapus item",
                                                    color = Color(0xFFB91C1C),
                                                    fontWeight = FontWeight.Bold,
                                                    fontSize = 11.sp,
                                                    modifier = Modifier.clickable { returnItems.removeAt(index) },
                                                )
                                            }
                                        }
                                    }
                                }

                                Text(
                                    text = "Tambah item retur",
                                    color = UiPrimary,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    modifier = Modifier.clickable { returnItems.add(PurchaseReturnItemEditorUi()) },
                                )

                                OutlinedTextField(value = returnReason, onValueChange = { returnReason = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Alasan Retur") }, singleLine = true)
                                returnFieldErrors["reason"]?.let { returnReasonError ->
                                    Text(
                                        text = returnReasonError,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }

                                OutlinedTextField(value = returnTransactionDateInput, onValueChange = { returnTransactionDateInput = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Transaction Date (epoch ms, opsional)") }, singleLine = true)
                                returnFieldErrors["transaction_date"]?.let { returnDateError ->
                                    Text(text = returnDateError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                }

                                returnFieldErrors["items"]?.let { returnItemsError ->
                                    Text(text = returnItemsError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                }
                                returnFieldErrors["items_effective"]?.let { returnItemsEffectiveError ->
                                    Text(text = returnItemsEffectiveError, color = Color(0xFFB91C1C), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                }

                                Button(
                                    onClick = { onRecordPurchaseReturn(returnDraftForValidation) },
                                    enabled = !isSaving && returnActionEnabled && returnCanSubmit,
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(12.dp),
                                ) {
                                    Text(if (isSaving) "Memproses..." else "Simpan Retur")
                                }

                                returnActionError?.let { returnActionErrorText ->
                                    Text(
                                        text = returnActionErrorText,
                                        color = Color(0xFFB91C1C),
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun FinanceModuleScreen(title: String, subtitle: String, items: List<String>, onBack: () -> Unit) {
    Column(modifier = Modifier.fillMaxSize().background(UiBackground)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Column {
                Text(text = title, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Text(text = subtitle, color = Color(0xFF94A3B8), fontSize = 11.sp)
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            itemsIndexed(items) { _, item ->
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Text(text = item, modifier = Modifier.padding(14.dp), fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun StockOperationsScreen(
    title: String,
    isSaving: Boolean,
    message: String?,
    errorMessage: String?,
    onRecordStockAdjustment: (String, Double, String) -> Unit,
    onRecordStockTransfer: (String, Double, String, String) -> Unit,
    onBack: () -> Unit,
) {
    var adjustmentProductId by remember { mutableStateOf("") }
    var adjustmentQty by remember { mutableStateOf("0") }
    var adjustmentReason by remember { mutableStateOf("stock_opname") }

    var transferProductId by remember { mutableStateOf("") }
    var transferQty by remember { mutableStateOf("1") }
    var transferFrom by remember { mutableStateOf("utama") }
    var transferTo by remember { mutableStateOf("cabang") }

    Column(modifier = Modifier.fillMaxSize().background(UiBackground)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Column {
                Text(text = title, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Text(text = "Adjustment dan transfer stok lokal", color = Color(0xFF94A3B8), fontSize = 11.sp)
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                if (!message.isNullOrBlank()) {
                    Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFECFDF3))) {
                        Text(text = message, color = Color(0xFF166534), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(12.dp))
                    }
                }
            }

            item {
                if (!errorMessage.isNullOrBlank()) {
                    Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFFEF2F2))) {
                        Text(text = errorMessage, color = Color(0xFFB91C1C), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(12.dp))
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Stock Adjustment", fontWeight = FontWeight.Bold)
                        OutlinedTextField(value = adjustmentProductId, onValueChange = { adjustmentProductId = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Product Local ID") }, singleLine = true)
                        OutlinedTextField(value = adjustmentQty, onValueChange = { adjustmentQty = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Qty Delta (+/-)") }, singleLine = true)
                        OutlinedTextField(value = adjustmentReason, onValueChange = { adjustmentReason = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Alasan") }, singleLine = true)
                        Button(
                            onClick = {
                                val qtyDelta = adjustmentQty.replace(',', '.').toDoubleOrNull() ?: 0.0
                                onRecordStockAdjustment(adjustmentProductId, qtyDelta, adjustmentReason)
                            },
                            enabled = !isSaving,
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                        ) {
                            Text(if (isSaving) "Memproses..." else "Simpan Adjustment")
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Stock Transfer", fontWeight = FontWeight.Bold)
                        OutlinedTextField(value = transferProductId, onValueChange = { transferProductId = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Product Local ID") }, singleLine = true)
                        OutlinedTextField(value = transferQty, onValueChange = { transferQty = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Qty Transfer") }, singleLine = true)
                        OutlinedTextField(value = transferFrom, onValueChange = { transferFrom = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Dari Lokasi") }, singleLine = true)
                        OutlinedTextField(value = transferTo, onValueChange = { transferTo = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Ke Lokasi") }, singleLine = true)
                        Button(
                            onClick = {
                                val qty = transferQty.replace(',', '.').toDoubleOrNull() ?: 0.0
                                onRecordStockTransfer(transferProductId, qty, transferFrom, transferTo)
                            },
                            enabled = !isSaving,
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                        ) {
                            Text(if (isSaving) "Memproses..." else "Simpan Transfer")
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun FinancePolicyScreen(
    isWriteLocked: Boolean,
    canUseProFinance: Boolean,
    todaySales: Long,
    todayTransactions: Int,
    grossProfitEstimate: Long,
    cashInTotal: Long,
    cashOutTotal: Long,
    stockValueEstimate: Long,
    syncActionSummaryText: String,
    syncFailedSummaryText: String,
    isSaving: Boolean,
    message: String?,
    errorMessage: String?,
    onRecordCashMovement: (String, Long, String?) -> Unit,
    onSnapshotDailyReport: () -> Unit,
    onToggleWriteLock: (Boolean) -> Unit,
    onBack: () -> Unit,
) {
    var selectedDirection by remember { mutableStateOf("in") }
    var cashAmountInput by remember { mutableStateOf("") }
    var cashNoteInput by remember { mutableStateOf("") }

    Column(modifier = Modifier.fillMaxSize().background(UiBackground)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Column {
                Text(text = "Kebijakan Keuangan", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Text(text = "Neraca, arus kas, lock period", color = Color(0xFF94A3B8), fontSize = 11.sp)
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(text = "Modul Pro", fontWeight = FontWeight.Bold)
                        Text(text = if (canUseProFinance) "Aktif" else "Terkunci Paket Pro", color = if (canUseProFinance) UiPrimary else Color(0xFF94A3B8), fontWeight = FontWeight.Bold)
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Ringkasan Hari Ini", fontWeight = FontWeight.Bold)
                        Text(text = "Penjualan: ${rupiah(todaySales)}", color = Color(0xFF0F172A), fontWeight = FontWeight.SemiBold)
                        Text(text = "Transaksi: $todayTransactions", color = Color(0xFF334155), fontSize = 12.sp)
                        Text(text = "Estimasi Laba Kotor: ${rupiah(grossProfitEstimate)}", color = Color(0xFF334155), fontSize = 12.sp)
                        Text(text = "Kas Masuk/Keluar: ${rupiah(cashInTotal)} / ${rupiah(cashOutTotal)}", color = Color(0xFF334155), fontSize = 12.sp)
                        Text(text = "Estimasi Nilai Stok: ${rupiah(stockValueEstimate)}", color = Color(0xFF334155), fontSize = 12.sp)
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column {
                            Text(text = "Lock Period", fontWeight = FontWeight.Bold)
                            Text(text = if (isWriteLocked) "Transaksi POS diblokir" else "Transaksi POS diizinkan", color = Color(0xFF64748B), fontSize = 12.sp)
                        }
                        Switch(
                            checked = isWriteLocked,
                            onCheckedChange = {
                                if (canUseProFinance) {
                                    onToggleWriteLock(it)
                                }
                            },
                            enabled = canUseProFinance,
                        )
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Arus Kas Shift", fontWeight = FontWeight.Bold)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            Button(
                                onClick = { selectedDirection = "in" },
                                modifier = Modifier.weight(1f),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = if (selectedDirection == "in") UiPrimary else Color(0xFFE2E8F0),
                                ),
                            ) {
                                Text(text = "Kas Masuk", color = if (selectedDirection == "in") Color.White else Color(0xFF0F172A))
                            }
                            Button(
                                onClick = { selectedDirection = "out" },
                                modifier = Modifier.weight(1f),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = if (selectedDirection == "out") Color(0xFFEF4444) else Color(0xFFE2E8F0),
                                ),
                            ) {
                                Text(text = "Kas Keluar", color = if (selectedDirection == "out") Color.White else Color(0xFF0F172A))
                            }
                        }
                        OutlinedTextField(
                            value = cashAmountInput,
                            onValueChange = { cashAmountInput = it },
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Nominal") },
                            singleLine = true,
                        )
                        OutlinedTextField(
                            value = cashNoteInput,
                            onValueChange = { cashNoteInput = it },
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Catatan (opsional)") },
                            singleLine = true,
                        )
                        Button(
                            onClick = {
                                val amount = cashAmountInput.replace(".", "").replace(",", "").toLongOrNull() ?: 0L
                                onRecordCashMovement(selectedDirection, amount, cashNoteInput.ifBlank { null })
                            },
                            modifier = Modifier.fillMaxWidth(),
                            enabled = canUseProFinance && !isSaving,
                            shape = RoundedCornerShape(12.dp),
                        ) {
                            Text(if (isSaving) "Memproses..." else "Simpan Arus Kas")
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Snapshot Laporan Harian", fontWeight = FontWeight.Bold)
                        Text(text = "Menyimpan angka hari ini ke local report dan outbox.", color = Color(0xFF64748B), fontSize = 12.sp)
                        Button(
                            onClick = onSnapshotDailyReport,
                            enabled = canUseProFinance && !isSaving,
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                        ) {
                            Text(if (isSaving) "Memproses..." else "Buat Snapshot")
                        }
                        if (!message.isNullOrBlank()) {
                            Text(text = message, color = UiPrimary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                        if (!errorMessage.isNullOrBlank()) {
                            Text(text = errorMessage, color = Color(0xFFB91C1C), fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(text = "Observabilitas Sinkronisasi", fontWeight = FontWeight.Bold)
                        Text(text = "Antrian per aksi: $syncActionSummaryText", color = Color(0xFF334155), fontSize = 12.sp)
                        Text(text = "Gagal per aksi: $syncFailedSummaryText", color = Color(0xFFB45309), fontSize = 12.sp)
                    }
                }
            }
        }
    }
}

@Composable
private fun GovernanceScreen(
    auditEvents: List<String>,
    currentRole: String = "kasir",
    voidRequiresApproval: Boolean = true,
    stockAdjustmentRequiresApproval: Boolean = true,
    stockTransferRequiresApproval: Boolean = true,
    cashMovementRequiresApproval: Boolean = true,
    dailySnapshotRequiresApproval: Boolean = true,
    purchaseMutationRequiresApproval: Boolean = true,
    canUseGovernance: Boolean = false,
    onRoleChanged: (String) -> Unit = {},
    onVoidApprovalChanged: (Boolean) -> Unit = {},
    onStockAdjustmentApprovalChanged: (Boolean) -> Unit = {},
    onStockTransferApprovalChanged: (Boolean) -> Unit = {},
    onCashMovementApprovalChanged: (Boolean) -> Unit = {},
    onDailySnapshotApprovalChanged: (Boolean) -> Unit = {},
    onPurchaseMutationApprovalChanged: (Boolean) -> Unit = {},
    onBack: () -> Unit,
) {
    val fallback = listOf(
        "Kasir A melakukan void transaksi #TRX-1021",
        "Supervisor mengubah batas diskon dari 10% ke 15%",
        "Admin mengaktifkan lock period bulanan",
    )
    val shownLogs = if (auditEvents.isEmpty()) fallback else auditEvents
    val enabledPolicyCount = listOf(
        voidRequiresApproval,
        stockAdjustmentRequiresApproval,
        stockTransferRequiresApproval,
        cashMovementRequiresApproval,
        dailySnapshotRequiresApproval,
        purchaseMutationRequiresApproval,
    ).count { it }
    val blockedForCashier = buildList {
        if (voidRequiresApproval) add("Void Sale")
        if (stockAdjustmentRequiresApproval) add("Stock Adjustment")
        if (stockTransferRequiresApproval) add("Stock Transfer")
        if (cashMovementRequiresApproval) add("Cash Movement")
        if (dailySnapshotRequiresApproval) add("Daily Snapshot")
        if (purchaseMutationRequiresApproval) add("Purchase Mutation")
    }

    Column(modifier = Modifier.fillMaxSize().background(UiBackground)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Text(text = "Governance & Audit", fontWeight = FontWeight.Bold, fontSize = 18.sp)
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Role Aktif Operasional", fontWeight = FontWeight.Bold)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            listOf("kasir", "supervisor", "admin").forEach { role ->
                                val selected = currentRole.equals(role, ignoreCase = true)
                                Button(
                                    onClick = { onRoleChanged(role) },
                                    enabled = canUseGovernance,
                                    modifier = Modifier.weight(1f),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = if (selected) UiPrimary else Color(0xFFE2E8F0),
                                    ),
                                ) {
                                    Text(
                                        text = role.replaceFirstChar { it.uppercase() },
                                        color = if (selected) Color.White else Color(0xFF0F172A),
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                }
                            }
                        }
                        Text(
                            text = "Role ini dipakai untuk simulasi gate otorisasi di APK.",
                            color = Color(0xFF64748B),
                            fontSize = 12.sp,
                        )
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(text = "Ringkasan Governance", fontWeight = FontWeight.Bold)
                        Text(
                            text = "Role aktif: ${currentRole.uppercase(Locale.ROOT)}",
                            color = Color(0xFF334155),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                        Text(
                            text = "Policy approval aktif: $enabledPolicyCount/6",
                            color = Color(0xFF64748B),
                            fontSize = 12.sp,
                        )
                        if (blockedForCashier.isNotEmpty()) {
                            Text(
                                text = "Aksi yang butuh approval saat role kasir: ${blockedForCashier.joinToString(", ")}",
                                color = Color(0xFF92400E),
                                fontSize = 12.sp,
                            )
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column {
                            Text(text = "Policy Void Perlu Approval", fontWeight = FontWeight.Bold)
                            Text(
                                text = if (voidRequiresApproval) "Kasir tidak dapat void tanpa approval" else "Void langsung diizinkan",
                                color = Color(0xFF64748B),
                                fontSize = 12.sp,
                            )
                        }
                        Switch(
                            checked = voidRequiresApproval,
                            onCheckedChange = onVoidApprovalChanged,
                            enabled = canUseGovernance,
                        )
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Text(text = "Policy Approval Matrix", fontWeight = FontWeight.Bold)
                        GovernancePolicySwitchRow(
                            label = "Stock Adjustment",
                            checked = stockAdjustmentRequiresApproval,
                            onCheckedChange = onStockAdjustmentApprovalChanged,
                            enabled = canUseGovernance,
                        )
                        GovernancePolicySwitchRow(
                            label = "Stock Transfer",
                            checked = stockTransferRequiresApproval,
                            onCheckedChange = onStockTransferApprovalChanged,
                            enabled = canUseGovernance,
                        )
                        GovernancePolicySwitchRow(
                            label = "Cash Movement",
                            checked = cashMovementRequiresApproval,
                            onCheckedChange = onCashMovementApprovalChanged,
                            enabled = canUseGovernance,
                        )
                        GovernancePolicySwitchRow(
                            label = "Daily Snapshot",
                            checked = dailySnapshotRequiresApproval,
                            onCheckedChange = onDailySnapshotApprovalChanged,
                            enabled = canUseGovernance,
                        )
                        GovernancePolicySwitchRow(
                            label = "Purchase Mutation",
                            checked = purchaseMutationRequiresApproval,
                            onCheckedChange = onPurchaseMutationApprovalChanged,
                            enabled = canUseGovernance,
                        )
                    }
                }
            }

            itemsIndexed(shownLogs) { idx, row ->
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(text = "Audit #${idx + 1}", color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        Text(text = row, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }
}

@Composable
private fun GovernancePolicySwitchRow(
    label: String,
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    enabled: Boolean,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text = label, fontWeight = FontWeight.SemiBold, color = Color(0xFF334155), fontSize = 13.sp)
        Switch(
            checked = checked,
            onCheckedChange = onCheckedChange,
            enabled = enabled,
        )
    }
}

@Composable
private fun PrinterReceiptScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    var pairedPrinters by remember { mutableStateOf(fetchPairedPrinters(context)) }
    val hasPrinter = pairedPrinters.isNotEmpty()

    Column(modifier = Modifier.fillMaxSize().background(UiBackground)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                Text(text = "Printer Struk", fontWeight = FontWeight.Bold, fontSize = 18.sp)
            }
            CircleIconButton(icon = Icons.AutoMirrored.Filled.HelpOutline)
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Row(
                        modifier = Modifier.padding(14.dp),
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box(
                            modifier = Modifier
                                .size(52.dp)
                                .clip(RoundedCornerShape(16.dp))
                                .background(Color(0xFFF1F5F9)),
                            contentAlignment = Alignment.Center,
                        ) {
                            Icon(Icons.Default.Print, contentDescription = null, tint = Color(0xFF94A3B8), modifier = Modifier.size(28.dp))
                        }
                        Column {
                            Text(text = if (hasPrinter) "Printer Terdeteksi" else "Printer Belum Terhubung", fontWeight = FontWeight.Bold)
                            Text(
                                text = if (hasPrinter) pairedPrinters.first() else "Sambungkan Bluetooth printer Anda",
                                color = Color(0xFF64748B),
                                fontSize = 12.sp,
                            )
                        }
                    }
                }
            }

            item {
                Button(
                    onClick = {
                        val granted = ensureBluetoothConnectPermission(context)
                        if (!granted) {
                            return@Button
                        }
                        openBluetoothSettings(context)
                        pairedPrinters = fetchPairedPrinters(context)
                    },
                    modifier = Modifier.fillMaxWidth().height(50.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = UiPrimary),
                    shape = RoundedCornerShape(14.dp),
                ) {
                    Icon(Icons.Default.Search, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Cari Printer", fontWeight = FontWeight.Bold)
                }
            }

            item {
                Button(
                    onClick = {
                        shareText(
                            context = context,
                            title = "Tes Cetak Struk",
                            body = sampleReceiptText(),
                        )
                    },
                    modifier = Modifier.fillMaxWidth().height(50.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Color.White, contentColor = Color(0xFF334155)),
                    shape = RoundedCornerShape(14.dp),
                ) {
                    Icon(Icons.Default.Print, contentDescription = null)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Tes Cetak", fontWeight = FontWeight.Bold)
                }
            }

            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFF1F5F9))) {
                    Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("CONTOH STRUK", color = Color(0xFF94A3B8), fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        Card(shape = RoundedCornerShape(8.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text("WARUNG JAYA", modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center, fontWeight = FontWeight.Bold)
                                Text("Jl. Melati No. 123, Jakarta", modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center, fontSize = 10.sp)
                                Text("2x Kopi Susu Aren       36.000", fontSize = 11.sp)
                                Text("1x Roti Bakar Coklat    15.000", fontSize = 11.sp)
                                Text("TOTAL                   56.100", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                Text("Terima Kasih", modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center, fontSize = 10.sp)
                            }
                        }
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        SettingLine("Lebar Kertas", "58mm")
                        SettingLine("Ukuran Font", "Normal")
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text("Cetak Otomatis", fontWeight = FontWeight.SemiBold)
                            Switch(checked = false, onCheckedChange = {})
                        }
                    }
                }
            }

            if (pairedPrinters.isNotEmpty()) {
                item {
                    Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text("Printer Terpasang", fontWeight = FontWeight.Bold)
                            pairedPrinters.forEach { printerName ->
                                Text(text = "• $printerName", color = Color(0xFF475569), fontSize = 12.sp)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SettingLine(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Text(label, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = Color(0xFF334155))
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(value, color = UiPrimary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
            Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFFCBD5E1))
        }
    }
}

data class HistoryShiftUi(
    val dateLabel: String,
    val timeRange: String,
    val cashierName: String,
    val openingBalance: Long,
    val closingBalance: Long,
    val isMatched: Boolean,
)

private enum class HistoryTab {
    TRANSAKSI,
    SHIFT,
    LAPORAN,
}

private enum class HistoryMode {
    LIST,
    TRANSACTION_DETAIL,
    SHIFT_DETAIL,
    PURCHASE_REPORT,
    STOCK_CARD,
    FIFO_LAYERS,
    AUDIT_LOG,
}

private enum class SettingsMode {
    MAIN,
    PRINTER,
    SUBSCRIPTION,
    PURCHASES,
    FINANCE,
    GOVERNANCE,
}

@Composable
private fun SubscriptionPlanScreen(
    activePlan: SubscriptionPlan,
    onBack: () -> Unit,
    onPlanChanged: (SubscriptionPlan) -> Unit,
) {
    val plans = listOf(SubscriptionPlan.S, SubscriptionPlan.M, SubscriptionPlan.P)
    val limits = when (activePlan) {
        SubscriptionPlan.S -> "Limit: 1 cabang • 2 staff • 1000 produk"
        SubscriptionPlan.M -> "Limit: 3 cabang • 10 staff • 5000 produk"
        SubscriptionPlan.P -> "Limit: Unlimited cabang • staff • produk"
    }
    val highlights = when (activePlan) {
        SubscriptionPlan.S -> listOf(
            "POS, riwayat transaksi, shift",
            "Produk dan stok dasar",
            "Laporan dasar penjualan/laba",
        )
        SubscriptionPlan.M -> listOf(
            "Semua Starter",
            "Pelanggan + pembelian lengkap",
            "Export laporan PDF/CSV",
        )
        SubscriptionPlan.P -> listOf(
            "Semua Medium",
            "Audit log, lock period, governance",
            "Akuntansi dan kontrol profesional",
        )
    }

    Column(modifier = Modifier.fillMaxSize().background(UiBackground)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Text(text = "Paket Langganan", fontWeight = FontWeight.Bold, fontSize = 19.sp)
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(text = "Mode paket diterapkan seperti versi web (visible but locked).", color = Color(0xFF64748B), fontSize = 12.sp)
                        Text(text = "Paket aktif: ${activePlan.code} - ${activePlan.label}", fontWeight = FontWeight.Bold)
                        Text(text = limits, color = UiPrimary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    }
                }
            }

            item {
                Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(text = "Highlight Fitur", fontWeight = FontWeight.Bold)
                        highlights.forEach { text ->
                            Text(text = "• $text", color = Color(0xFF475569), fontSize = 12.sp)
                        }
                    }
                }
            }

            itemsIndexed(plans) { _, plan ->
                val selected = activePlan == plan
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable {
                            onPlanChanged(plan)
                        },
                    shape = RoundedCornerShape(20.dp),
                    colors = CardDefaults.cardColors(containerColor = if (selected) UiPrimary.copy(alpha = 0.18f) else Color.White),
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column {
                            Text(text = "Paket ${plan.code}", color = Color(0xFF64748B), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            Text(text = plan.label, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                        }
                        Text(
                            text = if (selected) "Aktif" else "Pilih",
                            color = if (selected) UiPrimary else Color(0xFF64748B),
                            fontWeight = FontWeight.Bold,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun CircleIconButton(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    contentDescription: String? = null,
    modifier: Modifier = Modifier,
    tint: Color = Color(0xFF475569),
    background: Color = Color(0xFFF1F5F9),
    onClick: () -> Unit = {},
) {
    IconButton(
        onClick = onClick,
        modifier = modifier
            .size(42.dp)
            .clip(CircleShape)
            .background(background),
    ) {
        Icon(icon, contentDescription = contentDescription, tint = tint)
    }
}

private fun stockFor(index: Int): Int {
    return when {
        index % 5 == 0 -> 2
        index % 3 == 0 -> 5
        else -> 12 + (index % 7)
    }
}

private fun rupiah(value: Long): String {
    val format = NumberFormat.getCurrencyInstance(Locale("id", "ID"))
    format.maximumFractionDigits = 0
    return format.format(value)
}

private fun showToastSafe(context: Context, message: String, duration: Int = Toast.LENGTH_SHORT) {
    Handler(Looper.getMainLooper()).post {
        Toast.makeText(context, message, duration).show()
    }
}

private data class PurchaseItemEditorUi(
    val productLocalId: String,
    val productName: String,
    val categoryName: String,
    val itemType: String?,
    val productMode: String?,
    val stockOnHand: Double,
    val qty: Double,
    val unitCost: Long,
    val discountAmount: Long,
)

private fun isProductValidForPurchase(product: PurchaseProductOptionUi): Boolean {
    return isProductValidForPurchase(product.itemType, product.productMode)
}

private fun isProductValidForPurchase(itemType: String?, productMode: String?): Boolean {
    val normalizedMode = normalizeProductMode(productMode)
    if (normalizedMode == "bom") {
        return false
    }
    val normalizedType = normalizeProductItemType(itemType, productMode)
    return normalizedType == "raw_material" || normalizedMode in setOf("standard", "add_on", "addon", "add-on", "variant")
}

private fun normalizePaymentMethodLabel(rawMethod: String): String {
    return when (rawMethod.trim().lowercase(Locale.ROOT)) {
        "cash", "tunai" -> "Tunai"
        "qris" -> "QRIS"
        "debit", "kartu_debit", "card" -> "Kartu Debit"
        "transfer", "bank_transfer" -> "Transfer Bank"
        "ewallet", "e-wallet", "wallet" -> "E-Wallet"
        else -> rawMethod.uppercase(Locale.ROOT)
    }
}

private fun shareReceipt(context: Context, receipt: SaleReceiptUi, printable: Boolean) {
    val title = if (printable) "Cetak Struk" else "Bagikan Transaksi"
    val prefix = if (printable) "STRUK TRANSAKSI" else "DETAIL TRANSAKSI"
    val body = buildString {
        appendLine(prefix)
        appendLine("No: ${receipt.receiptNo}")
        appendLine("Metode: ${receipt.paymentMethodLabel}")
        appendLine("Status: ${receipt.syncStatusLabel}")
        appendLine("Total: ${rupiah(receipt.totalAmount)}")
        if (receipt.items.isNotEmpty()) {
            appendLine("--------------------")
            receipt.items.forEach {
                appendLine("${it.qty.toInt()}x ${it.name}  ${rupiah(it.lineTotal)}")
            }
        }
    }
    shareText(context = context, title = title, body = body)
}

private fun resolveReceiptItems(
    receipt: SaleReceiptUi,
    saleItemsBySaleId: Map<String, List<SaleItemWithProductNameRow>>,
): List<ReceiptItemUi> {
    if (receipt.items.isNotEmpty()) {
        return receipt.items
    }

    return saleItemsBySaleId[receipt.saleLocalId].orEmpty().map { item ->
        ReceiptItemUi(
            name = item.productName ?: item.productLocalId.take(8),
            qty = item.qty,
            lineTotal = item.lineTotal,
        )
    }
}

private fun shareText(context: Context, title: String, body: String) {
    val sendIntent = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, body)
    }
    val chooser = Intent.createChooser(sendIntent, title)
    chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    context.startActivity(chooser)
}

private fun printReceiptNative(context: Context, receipt: SaleReceiptUi) {
    val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
    if (printManager == null) {
        showToastSafe(context, "Print service tidak tersedia")
        return
    }

    val text = buildString {
        appendLine("WARUNG JAYA")
        appendLine("No: ${receipt.receiptNo}")
        appendLine("Metode: ${receipt.paymentMethodLabel}")
        appendLine("Status: ${receipt.syncStatusLabel}")
        appendLine("-----------------------")
        receipt.items.forEach {
            appendLine("${it.qty.toInt()}x ${it.name}")
            appendLine("   ${rupiah(it.lineTotal)}")
        }
        appendLine("-----------------------")
        appendLine("TOTAL: ${rupiah(receipt.totalAmount)}")
    }

    printManager.print("Receipt-${receipt.receiptNo}", TextPrintDocumentAdapter(text), PrintAttributes.Builder().build())
}

private fun printPlainText(context: Context, title: String, text: String) {
    val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
    if (printManager == null) {
        showToastSafe(context, "Print service tidak tersedia")
        return
    }
    printManager.print(title, TextPrintDocumentAdapter(text), PrintAttributes.Builder().build())
}

private class TextPrintDocumentAdapter(
    private val content: String,
) : PrintDocumentAdapter() {
    override fun onLayout(
        oldAttributes: PrintAttributes?,
        newAttributes: PrintAttributes,
        cancellationSignal: CancellationSignal?,
        callback: LayoutResultCallback,
        extras: Bundle?,
    ) {
        if (cancellationSignal?.isCanceled == true) {
            callback.onLayoutCancelled()
            return
        }

        val info = PrintDocumentInfo.Builder("receipt.pdf")
            .setContentType(PrintDocumentInfo.CONTENT_TYPE_DOCUMENT)
            .setPageCount(1)
            .build()
        callback.onLayoutFinished(info, true)
    }

    override fun onWrite(
        pages: Array<out PageRange>,
        destination: ParcelFileDescriptor,
        cancellationSignal: CancellationSignal?,
        callback: WriteResultCallback,
    ) {
        val document = PdfDocument()
        val pageInfo = PdfDocument.PageInfo.Builder(300, 600, 1).create()
        val page = document.startPage(pageInfo)
        drawText(page.canvas, content)
        document.finishPage(page)

        try {
            document.writeTo(java.io.FileOutputStream(destination.fileDescriptor))
            callback.onWriteFinished(arrayOf(PageRange.ALL_PAGES))
        } catch (e: Exception) {
            callback.onWriteFailed(e.message)
        } finally {
            document.close()
            destination.close()
        }
    }

    private fun drawText(canvas: Canvas, text: String) {
        val paint = Paint().apply {
            color = android.graphics.Color.BLACK
            textSize = 10f
            isAntiAlias = true
        }
        var y = 24f
        text.lines().forEach { line ->
            canvas.drawText(line, 16f, y, paint)
            y += 16f
        }
    }
}

private fun openBluetoothSettings(context: Context) {
    val intent = Intent(Settings.ACTION_BLUETOOTH_SETTINGS).apply {
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    context.startActivity(intent)
}

private fun ensureBluetoothConnectPermission(context: Context): Boolean {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
        return true
    }

    val granted = ContextCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED
    if (granted) {
        return true
    }

    val activity = context as? Activity
    if (activity == null) {
        showToastSafe(context, "Izin Bluetooth diperlukan untuk scan printer")
        return false
    }

    val shouldShowRationale = ActivityCompat.shouldShowRequestPermissionRationale(activity, Manifest.permission.BLUETOOTH_CONNECT)
    val prefs = context.getSharedPreferences("permissions", Context.MODE_PRIVATE)
    val askedBefore = prefs.getBoolean("asked_bt_connect", false)

    if (!shouldShowRationale && askedBefore) {
        showToastSafe(context, "Izin Bluetooth ditolak permanen. Membuka Pengaturan Aplikasi.", Toast.LENGTH_LONG)
        openAppPermissionSettings(context)
        return false
    }

    if (shouldShowRationale) {
        showToastSafe(context, "Aktifkan izin Bluetooth agar printer bisa dideteksi", Toast.LENGTH_LONG)
    }

    prefs.edit().putBoolean("asked_bt_connect", true).apply()
    ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.BLUETOOTH_CONNECT), 7001)
    if (!shouldShowRationale) {
        showToastSafe(context, "Jika izin ditolak, buka Pengaturan Aplikasi untuk mengaktifkannya", Toast.LENGTH_LONG)
    }
    return false
}

private fun openAppPermissionSettings(context: Context) {
    val intent = Intent(
        Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
        Uri.fromParts("package", context.packageName, null),
    ).apply {
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    context.startActivity(intent)
}

@SuppressLint("MissingPermission")
private fun fetchPairedPrinters(context: Context): List<String> {
    return try {
        val bluetoothManager = context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager ?: return emptyList()
        val adapter = bluetoothManager.adapter ?: return emptyList()
        val devices = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            adapter.bondedDevices
        } else {
            @Suppress("DEPRECATION")
            adapter.bondedDevices
        }
        devices.mapNotNull { device -> device.name }.sorted()
    } catch (_: SecurityException) {
        showToastSafe(context, "Izin Bluetooth belum diberikan")
        emptyList()
    }
}

private fun sampleReceiptText(): String {
    return """
        WARUNG JAYA
        Jl. Melati No. 123, Jakarta

        2x Kopi Susu Aren   36.000
        1x Roti Bakar       15.000
        --------------------------
        TOTAL               56.100
        Terima kasih
    """.trimIndent()
}

enum class ProductMode {
    LIST,
    ADD,
    DETAIL,
    RECIPE_EDITOR,
}

private enum class ProductSection {
    PRODUCTS,
    MATERIALS,
    RECIPES,
}

@Composable
private fun ProductMetaBadge(label: String, tag: String? = null) {
    val baseModifier = Modifier
        .clip(RoundedCornerShape(999.dp))
        .background(Color(0xFFE2E8F0))
        .padding(horizontal = 10.dp, vertical = 4.dp)
    val modifier = if (tag == null) baseModifier else baseModifier.testTag(tag)
    Text(
        text = label,
        color = Color(0xFF334155),
        fontSize = 11.sp,
        fontWeight = FontWeight.Bold,
        modifier = modifier,
    )
}

private fun normalizeCategoryName(rawValue: String, existingCategories: List<String>): String {
    val trimmed = rawValue.trim()
    if (trimmed.isBlank()) {
        return "Umum"
    }
    val existing = existingCategories.firstOrNull { it.trim().equals(trimmed, ignoreCase = true) }
    return existing?.trim().orEmpty().ifBlank { trimmed }
}

private fun normalizeProductMode(mode: String?): String {
    return mode?.trim()?.lowercase().orEmpty().ifBlank { "standard" }
}

private fun normalizeProductItemType(itemType: String?, productMode: String?): String {
    val normalizedMode = normalizeProductMode(productMode)
    val normalizedType = itemType?.trim()?.lowercase().orEmpty()
    return when {
        normalizedMode in setOf("add_on", "addon", "add-on") -> "raw_material"
        normalizedType in setOf("add_on", "addon", "add-on") -> "raw_material"
        else -> normalizedType.ifBlank { "finished_good" }
    }
}

private fun isMaterialProduct(product: PosProductItemUi): Boolean {
    val normalizedType = normalizeProductItemType(product.itemType, product.productMode)
    return normalizedType == "raw_material"
}

private fun isRecipeProduct(product: PosProductItemUi): Boolean {
    return normalizeProductMode(product.productMode) == "bom"
}

private fun modeLabel(mode: String): String {
    return when (mode) {
        "bom" -> "Resep"
        "add_on", "addon", "add-on" -> "Add-On"
        "variant" -> "Varian"
        else -> "Default"
    }
}

private fun formatFifoInboundDate(value: Long): String {
    return SimpleDateFormat("dd MMM yyyy", Locale("id", "ID")).format(Date(value))
}

private fun itemTypeLabel(itemType: String): String {
    return when (itemType) {
        "raw_material" -> "Bahan Baku"
        "finished_good" -> "Produk Jadi"
        else -> itemType.ifBlank { "-" }
    }
}

data class RecipeSeedLineUi(
    val materialProductLocalId: String?,
    val materialName: String,
    val qty: Double,
    val unit: String,
)

private data class RecipeEditorLineUi(
    val materialProductLocalId: String? = null,
    val materialName: String = "",
    val qty: String = "",
    val unit: String = "pcs",
)

data class RecipeMaterialCandidateUi(
    val localId: String,
    val name: String,
    val categoryName: String,
    val itemType: String,
    val productMode: String,
    val unitCost: Long = 0L,
)

data class ProductFifoLayerUi(
    val localId: String,
    val sourceRef: String,
    val qtyOriginal: Double,
    val qtyAvailable: Double,
    val unitCost: Double,
    val receivedAt: Long,
)

data class PurchaseProductOptionUi(
    val localId: String,
    val name: String,
    val categoryName: String,
    val itemType: String?,
    val productMode: String?,
    val stockOnHand: Double,
)

data class PurchaseFinalizeDraftUi(
    val purchaseNo: String,
    val supplierLocalId: String?,
    val items: List<PurchaseLineDraftUi>,
    val discountAmount: Long,
    val paymentMethod: String,
    val paymentStatus: String,
    val cashSource: String?,
    val dueDate: Long?,
    val note: String?,
)

data class PurchaseReceiveDraftUi(
    val purchaseLocalId: String,
    val purchaseStatus: String,
    val transactionDate: Long?,
    val items: List<PurchaseReceiveLineDraftUi>,
)

data class PurchaseReceiveLineDraftUi(
    val productLocalId: String,
    val qty: Double,
    val remainingQty: Double,
)

data class PurchaseReceiveItemEditorUi(
    val productLocalId: String = "",
    val qtyInput: String = "0",
    val remainingQtyInput: String = "0",
)

data class PurchaseReturnDraftUi(
    val purchaseLocalId: String,
    val purchaseStatus: String,
    val supplierLocalId: String,
    val purchaseSupplierLocalId: String,
    val transactionDate: Long?,
    val notes: String?,
    val reason: String,
    val items: List<PurchaseReturnLineDraftUi>,
)

data class PurchaseReturnLineDraftUi(
    val productLocalId: String,
    val qty: Double,
    val price: Long,
    val localRemainingQty: Double?,
    val reason: String?,
)

data class PurchaseReturnItemEditorUi(
    val productLocalId: String = "",
    val qtyInput: String = "0",
    val priceInput: String = "0",
    val remainingQtyInput: String = "",
    val reason: String = "",
)

data class PurchaseLineDraftUi(
    val productLocalId: String,
    val qty: Double,
    val unitCost: Long,
    val discountAmount: Long = 0L,
)

data class ProductEditSeedUi(
    val localId: String,
    val name: String,
    val category: String,
    val sellPrice: Long,
    val costPrice: Long,
    val openingStock: Int,
    val mode: NewProductMode,
    val recipeLines: List<RecipeSeedLineUi>,
)

enum class NewProductMode(val label: String) {
    DEFAULT("Default"),
    ADD_ON("Add-On"),
    RECIPE("Resep"),
    VARIANT("Varian"),
}

data class NewProductVariantDraft(
    val name: String,
    val price: Long,
)

data class NewProductDraft(
    val editingProductLocalId: String? = null,
    val name: String,
    val category: String,
    val sellPrice: Long,
    val costPrice: Long,
    val openingStock: Int,
    val mode: NewProductMode,
    val addOns: List<String>,
    val variants: List<NewProductVariantDraft>,
    val recipeLines: List<RecipeSeedLineUi>,
)

private data class VariantDraftUi(
    val name: String = "",
    val price: String = "",
)

