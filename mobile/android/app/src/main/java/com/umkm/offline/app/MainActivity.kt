package com.umkm.offline.app

import android.content.Context
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.text.KeyboardOptions
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.room.Room
import com.umkm.offline.app.navigation.AppTab
import com.umkm.offline.app.finance.FinanceOpsRepository
import com.umkm.offline.app.purchase.PurchaseItemDraft
import com.umkm.offline.app.purchase.PurchaseReceiveLineDraft
import com.umkm.offline.app.purchase.PurchaseRepository
import com.umkm.offline.app.purchase.PurchaseReturnLineDraft
import com.umkm.offline.app.purchase.PurchaseReturnItemDraft
import com.umkm.offline.app.inventory.InventoryOpsRepository
import com.umkm.offline.app.state.ConnectivityMonitor
import com.umkm.offline.app.state.PosViewModelFactory
import com.umkm.offline.app.state.PurchaseViewModelFactory
import com.umkm.offline.core.common.ConnectivityState
import com.umkm.offline.core.common.EntityType
import com.umkm.offline.core.common.OutboxStatus
import com.umkm.offline.core.common.SyncActionType
import com.umkm.offline.core.common.SyncStatus
import com.umkm.offline.core.database.OfflineDatabase
import com.umkm.offline.core.database.dao.CashMovementDao
import com.umkm.offline.core.database.dao.CustomerDao
import com.umkm.offline.core.database.dao.DailyReportDao
import com.umkm.offline.core.database.dao.InventoryLayerDao
import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.RecipeDao
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.SaleDao
import com.umkm.offline.core.database.dao.ShiftDao
import com.umkm.offline.core.database.dao.SupplierDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.AuditEventDao
import com.umkm.offline.core.database.dao.AppPolicyDao
import com.umkm.offline.core.database.entity.AppPolicyEntity
import com.umkm.offline.core.database.entity.CustomerEntity
import com.umkm.offline.core.database.entity.PaymentEntity
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.SaleEntity
import com.umkm.offline.core.database.entity.ShiftEntity
import com.umkm.offline.core.database.entity.SupplierEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import com.umkm.offline.app.recipe.RecipeDraftLine
import com.umkm.offline.app.recipe.RecipeRepository
import com.umkm.offline.core.sync.SyncDependencies
import com.umkm.offline.core.sync.SyncEngine
import com.umkm.offline.core.sync.SyncWorkScheduler
import com.umkm.offline.core.network.RetrofitSyncApi
import com.umkm.offline.feature.auth.LoginScreen
import com.umkm.offline.feature.home.HomeScreen
import com.umkm.offline.feature.pos.data.PosRepositoryImpl
import com.umkm.offline.feature.pos.presentation.PosScreen
import com.umkm.offline.feature.syncstatus.SyncStatusScreen
import androidx.work.WorkInfo
import androidx.work.WorkManager
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.rememberCoroutineScope
import com.umkm.offline.feature.pos.presentation.SaleReceiptUi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.util.UUID

internal fun isSyncRunning(syncWorkStates: List<WorkInfo.State>): Boolean {
    return syncWorkStates.any { it == WorkInfo.State.RUNNING }
}

internal fun resolveConnectivityState(
    isSyncing: Boolean,
    networkState: ConnectivityState,
    failedCount: Int,
): ConnectivityState {
    return when {
        isSyncing -> ConnectivityState.SYNCING
        networkState == ConnectivityState.OFFLINE -> ConnectivityState.OFFLINE
        failedCount > 0 -> ConnectivityState.SYNC_ERROR
        else -> ConnectivityState.ONLINE
    }
}

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val database = Room.databaseBuilder(
            this,
            OfflineDatabase::class.java,
            "umkm-offline.db",
        )
            .fallbackToDestructiveMigration()
            .build()

        val repository = PosRepositoryImpl(
            customerDao = database.customerDao(),
            productDao = database.productDao(),
            recipeDao = database.recipeDao(),
            saleDao = database.saleDao(),
            syncActionDao = database.syncActionDao(),
            inventoryLayerDao = database.inventoryLayerDao(),
            saleFifoAllocationDao = database.saleFifoAllocationDao(),
            allowNegativeRawMaterialStockProvider = {
                database.appPolicyDao()
                    .getValue(POLICY_ALLOW_NEGATIVE_RAW_MATERIAL_STOCK)
                    ?.equals("true", ignoreCase = true)
                    ?: true
            },
        )

        SyncDependencies.syncEngine = SyncEngine(
            syncActionDao = database.syncActionDao(),
            syncCursorDao = database.syncCursorDao(),
            syncApi = if (BuildConfig.SYNC_BASE_URL.isBlank()) {
                FakeSyncApi()
            } else {
                RetrofitSyncApi(BuildConfig.SYNC_BASE_URL)
            },
            purchaseDao = database.purchaseDao(),
            productDao = database.productDao(),
            stockMovementDao = database.stockMovementDao(),
            inventoryLayerDao = database.inventoryLayerDao(),
            auditEventDao = database.auditEventDao(),
            syncEffectDao = database.syncEffectDao(),
        )

        val syncScheduler = SyncWorkScheduler(this)
        syncScheduler.schedulePeriodicSync()

        val recipeRepository = RecipeRepository(
            recipeDao = database.recipeDao(),
            productDao = database.productDao(),
            syncActionDao = database.syncActionDao(),
        )

        lifecycleScope.launch {
            seedDemoDataIfNeeded(
                productDao = database.productDao(),
                customerDao = database.customerDao(),
                supplierDao = database.supplierDao(),
                purchaseDao = database.purchaseDao(),
            )
        }

        setContent {
            AppRoot(
                repository = repository,
                productDao = database.productDao(),
                inventoryLayerDao = database.inventoryLayerDao(),
                cashMovementDao = database.cashMovementDao(),
                dailyReportDao = database.dailyReportDao(),
                purchaseDao = database.purchaseDao(),
                supplierDao = database.supplierDao(),
                saleDao = database.saleDao(),
                shiftDao = database.shiftDao(),
                recipeDao = database.recipeDao(),
                syncActionDao = database.syncActionDao(),
                auditEventDao = database.auditEventDao(),
                appPolicyDao = database.appPolicyDao(),
                recipeRepository = recipeRepository,
                syncScheduler = syncScheduler,
            )
        }
    }
}

@Composable
private fun AppRoot(
    repository: PosRepositoryImpl,
    productDao: ProductDao,
    inventoryLayerDao: InventoryLayerDao,
    cashMovementDao: CashMovementDao,
    dailyReportDao: DailyReportDao,
    purchaseDao: PurchaseDao,
    supplierDao: SupplierDao,
    saleDao: SaleDao,
    shiftDao: ShiftDao,
    recipeDao: RecipeDao,
    syncActionDao: SyncActionDao,
    auditEventDao: AuditEventDao,
    appPolicyDao: AppPolicyDao,
    recipeRepository: RecipeRepository,
    syncScheduler: SyncWorkScheduler,
) {
    val context = LocalContext.current
    val connectivityMonitor = remember(context) { ConnectivityMonitor(context.applicationContext) }
    val networkState by connectivityMonitor.connectivityState.collectAsStateWithLifecycle(initialValue = ConnectivityState.OFFLINE)
    val syncWorkInfos by remember(context) {
        WorkManager.getInstance(context.applicationContext)
            .getWorkInfosByTagFlow(SyncWorkScheduler.SYNC_WORK_TAG)
    }.collectAsStateWithLifecycle(initialValue = emptyList())

    val posViewModel = viewModel<com.umkm.offline.feature.pos.presentation.PosViewModel>(
        factory = PosViewModelFactory(repository),
    )
    val purchaseRepository = remember(supplierDao, purchaseDao, syncActionDao, productDao) {
        PurchaseRepository(
            supplierDao = supplierDao,
            purchaseDao = purchaseDao,
            syncActionDao = syncActionDao,
            productDao = productDao,
        )
    }
    val purchaseViewModel = viewModel<com.umkm.offline.app.purchase.PurchaseViewModel>(
        factory = PurchaseViewModelFactory(purchaseRepository),
    )
    val posState by posViewModel.uiState.collectAsStateWithLifecycle()
    val purchaseState by purchaseViewModel.uiState.collectAsStateWithLifecycle()
    val financeRepository = remember(cashMovementDao, dailyReportDao, syncActionDao) {
        FinanceOpsRepository(
            cashMovementDao = cashMovementDao,
            dailyReportDao = dailyReportDao,
            syncActionDao = syncActionDao,
        )
    }
    val inventoryRepository = remember(productDao, syncActionDao) {
        InventoryOpsRepository(
            productDao = productDao,
            syncActionDao = syncActionDao,
        )
    }
    val inventoryProducts by productDao.observeProducts().collectAsStateWithLifecycle(initialValue = emptyList())
    val pendingCount by syncActionDao.observePendingCount().collectAsStateWithLifecycle(initialValue = 0)
    val failedCount by syncActionDao.observeFailedCount().collectAsStateWithLifecycle(initialValue = 0)
    val actionTypeCounts by syncActionDao.observeActionTypeCounts().collectAsStateWithLifecycle(initialValue = emptyList())
    val failedActionTypeCounts by syncActionDao.observeFailedActionTypeCounts().collectAsStateWithLifecycle(initialValue = emptyList())
    val lockPeriodValue by appPolicyDao.observeValue(POLICY_WRITE_LOCK_ENABLED).collectAsStateWithLifecycle(initialValue = "false")
    val taxPercentValue by appPolicyDao.observeValue(POLICY_TAX_PERCENT).collectAsStateWithLifecycle(initialValue = "11")
    val governanceRoleValue by appPolicyDao.observeValue(POLICY_GOV_CURRENT_ROLE).collectAsStateWithLifecycle(initialValue = "kasir")
    val governanceVoidApprovalValue by appPolicyDao.observeValue(POLICY_GOV_VOID_REQUIRES_APPROVAL).collectAsStateWithLifecycle(initialValue = "true")
    val governanceStockAdjustmentApprovalValue by appPolicyDao.observeValue(POLICY_GOV_STOCK_ADJUSTMENT_REQUIRES_APPROVAL).collectAsStateWithLifecycle(initialValue = "true")
    val governanceStockTransferApprovalValue by appPolicyDao.observeValue(POLICY_GOV_STOCK_TRANSFER_REQUIRES_APPROVAL).collectAsStateWithLifecycle(initialValue = "true")
    val governanceCashMovementApprovalValue by appPolicyDao.observeValue(POLICY_GOV_CASH_MOVEMENT_REQUIRES_APPROVAL).collectAsStateWithLifecycle(initialValue = "true")
    val governanceDailySnapshotApprovalValue by appPolicyDao.observeValue(POLICY_GOV_DAILY_SNAPSHOT_REQUIRES_APPROVAL).collectAsStateWithLifecycle(initialValue = "true")
    val governancePurchaseMutationApprovalValue by appPolicyDao.observeValue(POLICY_GOV_PURCHASE_MUTATION_REQUIRES_APPROVAL).collectAsStateWithLifecycle(initialValue = "true")
    val recentAuditRows by auditEventDao.observeRecent(limit = 50).collectAsStateWithLifecycle(initialValue = emptyList())
    val recentSales by saleDao.observeRecentSales(limit = 60).collectAsStateWithLifecycle(initialValue = emptyList())
    val recentSaleItems by saleDao.observeRecentSaleItemsWithProductName(limit = 500).collectAsStateWithLifecycle(initialValue = emptyList())
    val recentPayments by saleDao.observeRecentPayments(limit = 120).collectAsStateWithLifecycle(initialValue = emptyList())
    val recentShifts by shiftDao.observeRecent(limit = 30).collectAsStateWithLifecycle(initialValue = emptyList())
    val recipeItems by recipeDao.observeAll().collectAsStateWithLifecycle(initialValue = emptyList())
    val isSyncing = isSyncRunning(syncWorkInfos.map { it.state })
    val connectivityState = resolveConnectivityState(
        isSyncing = isSyncing,
        networkState = networkState,
        failedCount = failedCount,
    )
    val isWriteLocked = lockPeriodValue.equals("true", ignoreCase = true)
    val governanceRole = (governanceRoleValue ?: "kasir").lowercase()
    val governanceVoidRequiresApproval = governanceVoidApprovalValue.equals("true", ignoreCase = true)
    val governanceStockAdjustmentRequiresApproval = governanceStockAdjustmentApprovalValue.equals("true", ignoreCase = true)
    val governanceStockTransferRequiresApproval = governanceStockTransferApprovalValue.equals("true", ignoreCase = true)
    val governanceCashMovementRequiresApproval = governanceCashMovementApprovalValue.equals("true", ignoreCase = true)
    val governanceDailySnapshotRequiresApproval = governanceDailySnapshotApprovalValue.equals("true", ignoreCase = true)
    val governancePurchaseMutationRequiresApproval = governancePurchaseMutationApprovalValue.equals("true", ignoreCase = true)
    val taxPercent = taxPercentValue?.toDoubleOrNull()?.coerceIn(0.0, 100.0) ?: 11.0
    val recentAuditEvents = remember(recentAuditRows) {
        recentAuditRows.map { row -> formatAuditEntry(row.createdAt, row.message) }
    }
    val coroutineScope = rememberCoroutineScope()
    fun showToastSafe(message: String, duration: Int = Toast.LENGTH_SHORT) {
        Handler(Looper.getMainLooper()).post {
            Toast.makeText(context, message, duration).show()
        }
    }
    suspend fun showToastOnMain(message: String) {
        showToastSafe(message)
    }
    val subscriptionPrefs = remember(context) {
        context.applicationContext.getSharedPreferences("subscription", Context.MODE_PRIVATE)
    }

    var loggedStoreCode by rememberSaveable { mutableStateOf<String?>(null) }
    var loggedBranchLocalId by rememberSaveable { mutableStateOf<String?>(null) }
    var loggedRegisterLocalId by rememberSaveable { mutableStateOf<String?>(null) }
    var activeTab by rememberSaveable { mutableStateOf(AppTab.HOME) }
    var activePlanCode by rememberSaveable {
        mutableStateOf(subscriptionPrefs.getString("active_plan_code", SubscriptionPlan.S.code) ?: SubscriptionPlan.S.code)
    }
    val receiptHistory = remember { mutableStateListOf<SaleReceiptUi>() }
    val refundedSaleIds = remember { mutableStateListOf<String>() }
    var inventoryOpsSaving by remember { mutableStateOf(false) }
    var inventoryOpsMessage by remember { mutableStateOf<String?>(null) }
    var inventoryOpsError by remember { mutableStateOf<String?>(null) }
    var financeSaving by remember { mutableStateOf(false) }
    var financeMessage by remember { mutableStateOf<String?>(null) }
    var financeError by remember { mutableStateOf<String?>(null) }
    var observedSaleId by remember { mutableStateOf<String?>(null) }
    var showOpenShiftDialog by remember { mutableStateOf(false) }
    var showCloseShiftDialog by remember { mutableStateOf(false) }
    var showLateShiftDialog by remember { mutableStateOf(false) }
    var openShiftCashInput by remember { mutableStateOf("") }
    var closeShiftCashInput by remember { mutableStateOf("") }
    var lateShiftCashInput by remember { mutableStateOf("") }
    var pendingHistoryReceiptNo by remember { mutableStateOf<String?>(null) }
    var pendingHistorySaleLocalId by remember { mutableStateOf<String?>(null) }
    var notifiedLateShiftLocalId by remember { mutableStateOf<String?>(null) }
    var showExitAppDialog by remember { mutableStateOf(false) }
    var lastPosErrorToast by remember { mutableStateOf<String?>(null) }
    val activePlan = remember(activePlanCode) { SubscriptionPlan.fromCode(activePlanCode) }
    val actorBranchLocalId = loggedBranchLocalId?.trim()
    val actorRegisterLocalId = loggedRegisterLocalId?.trim()

    val saleItemsBySaleId = remember(recentSaleItems) {
        recentSaleItems.groupBy { it.saleLocalId }
    }
    val paymentBySaleId = remember(recentPayments) {
        recentPayments.associateBy { it.saleLocalId }
    }
    val recipeLinesByProduct = remember(recipeItems) {
        recipeItems.groupBy(
            keySelector = { row -> row.productLocalId },
            valueTransform = { row ->
                RecipeSeedLineUi(
                    materialProductLocalId = row.materialProductLocalId,
                    materialName = row.materialName,
                    qty = row.qty,
                    unit = row.unit,
                )
            },
        )
    }
    val recipeCountByProduct = remember(recipeLinesByProduct) {
        recipeLinesByProduct.mapValues { (_, lines) -> lines.size }
    }
    val recipeMaterialCandidates = remember(inventoryProducts) {
        inventoryProducts
            .mapNotNull { it.toRecipeMaterialCandidateOrNull() }
            .distinctBy { it.localId }
            .sortedBy { it.name.lowercase() }
    }
    val existingProductCategories = remember(inventoryProducts) {
        inventoryProducts
            .map { it.categoryName.trim() }
            .filter { it.isNotBlank() }
            .distinctBy { it.lowercase() }
            .sortedBy { it.lowercase() }
    }
    val purchaseProducts = remember(inventoryProducts) {
        inventoryProducts.map { product ->
            PurchaseProductOptionUi(
                localId = product.localId,
                name = product.name,
                categoryName = product.categoryName,
                itemType = product.itemType,
                productMode = product.productMode,
                stockOnHand = product.stockOnHand,
            )
        }
    }
    val purchaseProductById = remember(purchaseProducts) {
        purchaseProducts.associateBy { it.localId }
    }

    fun isPurchaseProductAllowed(itemType: String?, productMode: String?): Boolean {
        val normalizedMode = productMode?.trim()?.lowercase().orEmpty().ifBlank { "standard" }
        if (normalizedMode == "bom") {
            return false
        }
        val normalizedType = itemType?.trim()?.lowercase().orEmpty()
        val normalizedItemType = when {
            normalizedMode in setOf("add_on", "addon", "add-on") -> "raw_material"
            normalizedType in setOf("add_on", "addon", "add-on") -> "raw_material"
            else -> normalizedType.ifBlank { "finished_good" }
        }
        return normalizedItemType == "raw_material" || normalizedMode in setOf("standard", "variant", "add_on", "addon", "add-on")
    }
    val activeShift = remember(recentShifts) {
        recentShifts.firstOrNull { shift ->
            shift.status.equals("OPEN", ignoreCase = true) &&
                !isShiftExpiredByDate(shift.openedAt, System.currentTimeMillis())
        }
    }
    val pendingPreviousDayShift = remember(recentShifts) {
        recentShifts
            .asSequence()
            .filter { shift ->
                shift.status.equals("OPEN", ignoreCase = true) &&
                    isShiftExpiredByDate(shift.openedAt, System.currentTimeMillis())
            }
            .sortedBy { it.openedAt }
            .firstOrNull()
    }
    val shiftCashMovements by remember(activeShift?.localId) {
        if (activeShift == null) {
            flowOf(emptyList())
        } else {
            cashMovementDao.observeByShift(activeShift.localId)
        }
    }.collectAsStateWithLifecycle(initialValue = emptyList())
    val todayStartMillis = remember(recentSales) {
        startOfDayMillis(System.currentTimeMillis())
    }
    val todaySales = remember(recentSales, todayStartMillis) {
        recentSales
            .asSequence()
            .filter { it.finalizedAt >= todayStartMillis }
            .sumOf { it.totalAmount }
    }
    val todayTransactions = remember(recentSales, todayStartMillis) {
        recentSales.count { it.finalizedAt >= todayStartMillis }
    }
    val shiftSales = remember(recentSales, activeShift) {
        val openedAt = activeShift?.openedAt ?: return@remember 0L
        val closedAt = activeShift.closedAt
        recentSales
            .asSequence()
            .filter { sale ->
                sale.finalizedAt >= openedAt && (closedAt == null || sale.finalizedAt <= closedAt)
            }
            .sumOf { it.totalAmount }
    }
    val shiftOpenedLabel = remember(activeShift) {
        if (activeShift == null) {
            "Belum ada shift aktif"
        } else {
            "Dimulai: ${formatTime(activeShift.openedAt)}"
        }
    }
    val cashInTotal = remember(shiftCashMovements) {
        shiftCashMovements
            .asSequence()
            .filter { it.direction.equals("in", ignoreCase = true) }
            .sumOf { it.amount }
    }
    val cashOutTotal = remember(shiftCashMovements) {
        shiftCashMovements
            .asSequence()
            .filter { it.direction.equals("out", ignoreCase = true) }
            .sumOf { it.amount }
    }
    val stockValueEstimate = remember(inventoryProducts) {
        inventoryProducts.sumOf { (it.stockOnHand * it.price).toLong() }
    }
    val syncActionSummaryText = remember(actionTypeCounts) {
        if (actionTypeCounts.isEmpty()) {
            "Belum ada antrian sinkronisasi."
        } else {
            actionTypeCounts.take(4).joinToString(" • ") { "${it.actionType}:${it.total}" }
        }
    }
    val syncFailedSummaryText = remember(failedActionTypeCounts) {
        if (failedActionTypeCounts.isEmpty()) {
            "Tidak ada gagal sinkronisasi per aksi."
        } else {
            failedActionTypeCounts.take(4).joinToString(" • ") { "${it.actionType}:${it.total}" }
        }
    }

    LaunchedEffect(connectivityState) {
        posViewModel.setConnectivity(connectivityState)
    }

    LaunchedEffect(activeShift?.localId, activeShift?.status, activeShift?.userLocalId) {
        posViewModel.setActiveShiftContext(
            shiftLocalId = activeShift?.localId,
            shiftStatus = activeShift?.status,
            shiftOwnerUserLocalId = activeShift?.userLocalId,
            shiftBranchLocalId = activeShift?.branchLocalId,
            shiftRegisterLocalId = activeShift?.registerLocalId,
        )
    }

    LaunchedEffect(loggedStoreCode, actorBranchLocalId, actorRegisterLocalId) {
        posViewModel.setActorContext(
            actorUserLocalId = loggedStoreCode?.trim(),
            actorBranchLocalId = actorBranchLocalId,
            actorRegisterLocalId = actorRegisterLocalId,
        )
    }

    LaunchedEffect(pendingPreviousDayShift?.localId) {
        val lateShiftId = pendingPreviousDayShift?.localId
        if (lateShiftId != null && lateShiftId != notifiedLateShiftLocalId) {
            notifiedLateShiftLocalId = lateShiftId
            showLateShiftDialog = true
            showToastSafe("Shift sebelumnya belum ditutup. Isi kas akhir kemarin untuk melanjutkan.")
            appendAuditEvent(
                auditEventDao = auditEventDao,
                message = "Deteksi shift hari sebelumnya belum ditutup: $lateShiftId",
                category = "shift",
            )
        }
    }

    LaunchedEffect(activeTab, pendingHistoryReceiptNo, pendingHistorySaleLocalId) {
        if (activeTab != AppTab.HISTORY) {
            return@LaunchedEffect
        }
        val incomingReceiptNo = pendingHistoryReceiptNo?.trim()?.ifBlank { null }
        val incomingSaleLocalId = pendingHistorySaleLocalId?.trim()?.ifBlank { null }
        if (incomingReceiptNo == null && incomingSaleLocalId == null) {
            return@LaunchedEffect
        }

        appendAuditEvent(
            auditEventDao = auditEventDao,
            message = "Receipt detail receiver: receiptNo=${incomingReceiptNo ?: "-"}, saleLocalId=${incomingSaleLocalId ?: "-"}",
            category = "pos",
        )

        val byLocalId = incomingSaleLocalId?.let { saleDao.getSaleByLocalId(it) }
        val byReceiptNo = incomingReceiptNo?.let { saleDao.getLatestSaleByReceiptNo(it) }
        val canonicalSale = byLocalId ?: byReceiptNo
        appendAuditEvent(
            auditEventDao = auditEventDao,
            message = "Receipt detail canonical resolve: ${canonicalSale?.localId ?: "not_found"}",
            category = "pos",
        )

        if (canonicalSale != null) {
            pendingHistorySaleLocalId = canonicalSale.localId
            pendingHistoryReceiptNo = canonicalSale.receiptNo
        }
    }

    LaunchedEffect(activeShift?.localId, activeTab) {
        if (activeTab == AppTab.POS && activeShift == null) {
            activeTab = AppTab.HOME
            showToastSafe("Shift sudah tidak aktif. Kembali ke Home.")
        }
    }

    LaunchedEffect(activePlanCode) {
        subscriptionPrefs.edit().putString("active_plan_code", activePlanCode).apply()
    }

    LaunchedEffect(posState.lastSaleId, posState.lastReceipt) {
        val latestReceipt = posState.lastReceipt
        if (posState.lastSaleId != null && posState.lastSaleId != observedSaleId && latestReceipt != null) {
            observedSaleId = posState.lastSaleId
            receiptHistory.add(0, latestReceipt)
        }
    }

    LaunchedEffect(posState.errorMessage) {
        val current = posState.errorMessage?.trim().orEmpty()
        if (current.isNotBlank() && current != lastPosErrorToast) {
            showToastSafe(current)
            lastPosErrorToast = current
        }
        if (current.isBlank()) {
            lastPosErrorToast = null
        }
    }

    MaterialTheme {
        if (loggedStoreCode == null || actorBranchLocalId.isNullOrBlank() || actorRegisterLocalId.isNullOrBlank()) {
            LoginScreen(
                onLoginSuccess = { storeCode, branchLocalId, registerLocalId ->
                    loggedStoreCode = storeCode
                    loggedBranchLocalId = branchLocalId
                    loggedRegisterLocalId = registerLocalId
                },
            )
            return@MaterialTheme
        }

        BackHandler {
            when {
                activeTab == AppTab.POS && posState.checkoutStep == com.umkm.offline.feature.pos.presentation.CheckoutStep.PAYMENT -> {
                    posViewModel.backToCart()
                }

                activeTab != AppTab.HOME -> {
                    activeTab = AppTab.HOME
                }

                else -> {
                    showExitAppDialog = true
                }
            }
        }

        Scaffold(
            modifier = androidx.compose.ui.Modifier.fillMaxSize(),
            bottomBar = {
                NavigationBar {
                    NavigationBarItem(
                        selected = activeTab == AppTab.HOME,
                        onClick = { activeTab = AppTab.HOME },
                        icon = { androidx.compose.material3.Icon(Icons.Default.Dashboard, contentDescription = "Home") },
                        label = {
                            Text(
                                text = "Home",
                                fontWeight = if (activeTab == AppTab.HOME) FontWeight.Bold else FontWeight.Medium,
                                fontSize = 10.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                    NavigationBarItem(
                        selected = activeTab == AppTab.POS,
                        onClick = {
                            if (activeShift == null) {
                                showToastSafe("Buka shift dulu sebelum masuk POS.")
                            } else {
                                activeTab = AppTab.POS
                            }
                        },
                        icon = { androidx.compose.material3.Icon(Icons.Default.PointOfSale, contentDescription = "Kasir") },
                        label = {
                            Text(
                                text = "Kasir",
                                fontWeight = if (activeTab == AppTab.POS) FontWeight.Bold else FontWeight.Medium,
                                fontSize = 10.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                    NavigationBarItem(
                        selected = activeTab == AppTab.HISTORY,
                        onClick = { activeTab = AppTab.HISTORY },
                        icon = { androidx.compose.material3.Icon(Icons.Default.History, contentDescription = "Riwayat") },
                        label = {
                            Text(
                                text = "Riwayat",
                                fontWeight = if (activeTab == AppTab.HISTORY) FontWeight.Bold else FontWeight.Medium,
                                fontSize = 10.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                    NavigationBarItem(
                        selected = activeTab == AppTab.PRODUCTS,
                        onClick = { activeTab = AppTab.PRODUCTS },
                        icon = { androidx.compose.material3.Icon(Icons.Default.Inventory2, contentDescription = "Produk") },
                        label = {
                            Text(
                                text = "Produk",
                                fontWeight = if (activeTab == AppTab.PRODUCTS) FontWeight.Bold else FontWeight.Medium,
                                fontSize = 10.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                    NavigationBarItem(
                        selected = activeTab == AppTab.SETTINGS,
                        onClick = { activeTab = AppTab.SETTINGS },
                        icon = { androidx.compose.material3.Icon(Icons.Default.Settings, contentDescription = "Setelan") },
                        label = {
                            Text(
                                text = "Setelan",
                                fontWeight = if (activeTab == AppTab.SETTINGS) FontWeight.Bold else FontWeight.Medium,
                                fontSize = 10.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                }
            },
        ) { innerPadding ->
            Box(
                modifier = androidx.compose.ui.Modifier
                    .fillMaxSize()
                    .padding(innerPadding),
            ) {
                when (activeTab) {
                    AppTab.HOME -> {
                        HomeScreen(
                            cashierName = loggedStoreCode ?: "Kasir",
                            pendingSyncCount = pendingCount,
                            canSeeGlobalNotification = activePlan.includes(SubscriptionPlan.M),
                            canSeeControlSection = true,
                            isShiftOpen = activeShift != null,
                            shiftOpenedLabel = shiftOpenedLabel,
                            openingCash = activeShift?.openingCash ?: 0L,
                            shiftSales = shiftSales,
                            todaySales = todaySales,
                            todayTransactions = todayTransactions,
                            onOpenPos = {
                                if (activeShift == null) {
                                    showToastSafe("Buka shift dulu sebelum masuk POS.")
                                } else {
                                    activeTab = AppTab.POS
                                }
                            },
                            onOpenShift = {
                                if (pendingPreviousDayShift != null) {
                                    showLateShiftDialog = true
                                    showToastSafe("Shift kemarin belum ditutup. Isi kas akhir kemarin dulu.")
                                    return@HomeScreen
                                }
                                openShiftCashInput = ""
                                showOpenShiftDialog = true
                            },
                            onCloseShift = {
                                closeShiftCashInput = ""
                                showCloseShiftDialog = true
                            },
                            onOpenSyncStatus = { activeTab = AppTab.HISTORY },
                        )
                    }

                    AppTab.POS -> {
                        PosScreen(
                            state = posState,
                            products = posViewModel.filteredProducts(),
                            categories = posViewModel.categories(),
                            cartTotal = posViewModel.cartTotal(),
                            canUseCustomerFeature = activePlan.canAccess(GatedFeature.POS_CUSTOMER),
                            isWriteLocked = isWriteLocked,
                            onKeywordChanged = posViewModel::onSearchChanged,
                            onCategorySelected = posViewModel::selectCategory,
                            onCustomerSelected = posViewModel::selectCustomer,
                            onAddProduct = posViewModel::addToCart,
                            onRemoveFromCart = posViewModel::removeFromCart,
                            onOpenPayment = posViewModel::openPaymentStep,
                            onBackToCart = posViewModel::backToCart,
                            onPaymentMethodSelected = posViewModel::selectPaymentMethod,
                            onConfirmPayment = { paidAmount, discountAmount ->
                                coroutineScope.launch {
                                    val validation = validateShiftBeforeTransaction(
                                        shiftDao = shiftDao,
                                        saleDao = saleDao,
                                        cashMovementDao = cashMovementDao,
                                        syncActionDao = syncActionDao,
                                        auditEventDao = auditEventDao,
                                        now = System.currentTimeMillis(),
                                    )
                                    if (!validation.allowed) {
                                        showToastOnMain(validation.message)
                                        appendAuditEvent(auditEventDao, "Blokir checkout POS: ${validation.message}", category = "shift")
                                        return@launch
                                    }

                                    val validShift = validation.activeShift
                                    posViewModel.setActiveShiftContext(
                                        shiftLocalId = validShift?.localId,
                                        shiftStatus = validShift?.status,
                                        shiftOwnerUserLocalId = validShift?.userLocalId,
                                        shiftBranchLocalId = validShift?.branchLocalId,
                                        shiftRegisterLocalId = validShift?.registerLocalId,
                                    )

                                    if (isWriteLocked) {
                                        showToastOnMain("Periode terkunci. Transaksi baru dinonaktifkan.")
                                        appendAuditEvent(auditEventDao, "Blokir checkout POS: lock period aktif", category = "finance")
                                        return@launch
                                    }

                                    posViewModel.finalizeSaleOffline(
                                        paidAmount = paidAmount,
                                        discountAmount = discountAmount,
                                    )
                                    appendAuditEvent(auditEventDao, "Checkout POS berhasil diproses", category = "pos")
                                    syncScheduler.triggerImmediateSync()
                                }
                            },
                            onOpenReceiptDetail = { saleLocalId, receiptNo ->
                                coroutineScope.launch {
                                    appendAuditEvent(
                                        auditEventDao = auditEventDao,
                                        message = "Receipt detail sender: receiptNo=$receiptNo, saleLocalId=$saleLocalId",
                                        category = "pos",
                                    )
                                }
                                pendingHistoryReceiptNo = receiptNo
                                pendingHistorySaleLocalId = saleLocalId
                                activeTab = AppTab.HISTORY
                            },
                        )
                    }

                    AppTab.HISTORY -> {
                        HistoryScreen(
                            receipts = mergeHistoryReceipts(
                                sessionReceipts = receiptHistory,
                                recentSales = recentSales,
                                refundedSaleIds = refundedSaleIds.toSet(),
                            ),
                            shifts = recentShifts.map { shift ->
                                HistoryShiftUi(
                                    dateLabel = formatDate(shift.openedAt),
                                    timeRange = formatShiftRange(shift.openedAt, shift.closedAt),
                                    cashierName = shift.userLocalId,
                                    openingBalance = shift.openingCash,
                                    closingBalance = shift.closingCash ?: shift.openingCash,
                                    isMatched = (shift.closingCash ?: shift.openingCash) >= shift.openingCash,
                                )
                            },
                            saleItemsBySaleId = saleItemsBySaleId,
                            paymentBySaleId = paymentBySaleId,
                            pendingCount = pendingCount,
                            failedCount = failedCount,
                            canPrintShiftSummary = activePlan.canAccess(GatedFeature.SHIFT_PRINT_SUMMARY),
                            canDownloadReport = activePlan.canAccess(GatedFeature.REPORT_DOWNLOAD),
                            canViewPurchasesReport = activePlan.canAccess(GatedFeature.REPORT_PURCHASES_VIEW),
                            canViewStockCard = activePlan.canAccess(GatedFeature.REPORT_INVENTORY_STOCK_CARD),
                            canViewFifoLayers = false,
                            showAuditLog = false,
                            auditEvents = recentAuditEvents,
                            purchaseSuppliers = purchaseState.suppliers,
                            purchaseProducts = purchaseProducts,
                            purchaseSaving = purchaseState.isSaving,
                            purchaseMessage = purchaseState.message,
                            purchaseError = purchaseState.errorMessage,
                            purchaseCreateCanSubmit = purchaseState.createCanSubmit,
                            purchaseCreateFieldErrors = purchaseState.createFieldErrors,
                            purchaseCreateFormError = purchaseState.createFormError,
                            purchaseReceiveCanSubmit = purchaseState.receiveCanSubmit,
                            purchaseReceiveActionEnabled = purchaseState.receiveActionEnabled,
                            purchaseReceiveFieldErrors = purchaseState.receiveFieldErrors,
                            purchaseReceiveActionError = purchaseState.receiveActionError,
                            purchaseReturnCanSubmit = purchaseState.returnCanSubmit,
                            purchaseReturnActionEnabled = purchaseState.returnActionEnabled,
                            purchaseReturnFieldErrors = purchaseState.returnFieldErrors,
                            purchaseReturnActionError = purchaseState.returnActionError,
                            onCreateSupplier = { name, phone, address, notes ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Aksi purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Create supplier ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@HistoryScreen
                                }
                                purchaseViewModel.createSupplier(
                                    name = name,
                                    phone = phone,
                                    address = address,
                                    notes = notes,
                                    requestedByRole = governanceRole,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onFinalizePurchase = { purchaseDraft ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Finalize purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Finalize purchase ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@HistoryScreen
                                }
                                val invalidLine = purchaseDraft.items.firstOrNull { line ->
                                    val product = purchaseProductById[line.productLocalId]
                                    !isPurchaseProductAllowed(product?.itemType, product?.productMode)
                                }
                                if (invalidLine != null) {
                                    showToastSafe("Produk resep tidak dapat dibeli sebagai stok")
                                    Log.d("PurchaseValidation", "finalize_blocked productLocalId=${invalidLine.productLocalId} result=blocked")
                                    return@HistoryScreen
                                }
                                purchaseViewModel.finalizePurchase(
                                    purchaseNo = purchaseDraft.purchaseNo,
                                    supplierLocalId = purchaseDraft.supplierLocalId,
                                    items = purchaseDraft.items.map { line ->
                                        PurchaseItemDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.unitCost,
                                        )
                                    },
                                    discountAmount = purchaseDraft.discountAmount,
                                    dueDate = purchaseDraft.dueDate,
                                    paymentMethod = purchaseDraft.paymentMethod,
                                    paymentStatus = purchaseDraft.paymentStatus,
                                    cashSource = purchaseDraft.cashSource,
                                    requestedByRole = governanceRole,
                                    note = purchaseDraft.note,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onEvaluateFinalizePurchaseDraft = { purchaseDraft ->
                                purchaseViewModel.evaluateFinalizePurchaseDraft(
                                    supplierLocalId = purchaseDraft.supplierLocalId,
                                    items = purchaseDraft.items.map { line ->
                                        PurchaseItemDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.unitCost,
                                        )
                                    },
                                    paymentMethod = purchaseDraft.paymentMethod,
                                    dueDate = purchaseDraft.dueDate,
                                    transactionDate = null,
                                )
                            },
                            onEvaluateMarkPurchaseReceivedDraft = { receiveDraft ->
                                purchaseViewModel.evaluateMarkPurchaseReceivedDraft(
                                    purchaseLocalId = receiveDraft.purchaseLocalId,
                                    purchaseStatus = receiveDraft.purchaseStatus,
                                    receiveLines = receiveDraft.items.map { line ->
                                        PurchaseReceiveLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            remainingQty = line.remainingQty,
                                        )
                                    },
                                    payloadTransactionDate = receiveDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                )
                            },
                            onMarkPurchaseReceived = { receiveDraft ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Receiving purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Receiving purchase ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@HistoryScreen
                                }
                                purchaseViewModel.markPurchaseReceived(
                                    purchaseLocalId = receiveDraft.purchaseLocalId,
                                    purchaseStatus = receiveDraft.purchaseStatus,
                                    receiveLines = receiveDraft.items.map { line ->
                                        PurchaseReceiveLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            remainingQty = line.remainingQty,
                                        )
                                    },
                                    payloadTransactionDate = receiveDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                    requestedByRole = governanceRole,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onEvaluateRecordPurchaseReturnDraft = { returnDraft ->
                                purchaseViewModel.evaluateRecordPurchaseReturnDraft(
                                    purchaseLocalId = returnDraft.purchaseLocalId,
                                    purchaseStatus = returnDraft.purchaseStatus,
                                    supplierLocalId = returnDraft.supplierLocalId,
                                    purchaseSupplierLocalId = returnDraft.purchaseSupplierLocalId,
                                    reason = returnDraft.reason,
                                    items = returnDraft.items.map { line ->
                                        PurchaseReturnLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.price,
                                            localRemainingQty = line.localRemainingQty,
                                        )
                                    },
                                    payloadTransactionDate = returnDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                )
                            },
                            onRecordPurchaseReturn = { returnDraft ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Retur purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Retur purchase ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@HistoryScreen
                                }
                                purchaseViewModel.recordPurchaseReturn(
                                    purchaseLocalId = returnDraft.purchaseLocalId,
                                    purchaseStatus = returnDraft.purchaseStatus,
                                    supplierLocalId = returnDraft.supplierLocalId,
                                    purchaseSupplierLocalId = returnDraft.purchaseSupplierLocalId,
                                    reason = returnDraft.reason,
                                    items = returnDraft.items.map { line ->
                                        PurchaseReturnLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.price,
                                            localRemainingQty = line.localRemainingQty,
                                        )
                                    },
                                    payloadTransactionDate = returnDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                    requestedByRole = governanceRole,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            inventoryOpsSaving = inventoryOpsSaving,
                            inventoryOpsMessage = inventoryOpsMessage,
                            inventoryOpsError = inventoryOpsError,
                            onRecordStockAdjustment = { productId, qtyDelta, reason ->
                                coroutineScope.launch {
                                    if (governanceStockAdjustmentRequiresApproval && governanceRole == "kasir") {
                                        inventoryOpsError = "Aksi stok perlu approval Supervisor/Admin sesuai kebijakan governance."
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Stock adjustment ditolak: role kasir memerlukan approval",
                                            category = "governance",
                                        )
                                        return@launch
                                    }
                                    inventoryOpsSaving = true
                                    inventoryOpsMessage = null
                                    inventoryOpsError = null
                                    runCatching {
                                        inventoryRepository.recordStockAdjustmentOffline(
                                            productLocalId = productId,
                                            qtyDelta = qtyDelta,
                                            reason = reason,
                                            requestedByRole = governanceRole,
                                            approvalRequired = governanceStockAdjustmentRequiresApproval,
                                        )
                                    }.onSuccess {
                                        inventoryOpsMessage = "Stock adjustment tersimpan lokal dan siap sinkronisasi."
                                        syncScheduler.triggerImmediateSync()
                                    }.onFailure { error ->
                                        inventoryOpsError = error.message ?: "Gagal mencatat stock adjustment."
                                    }
                                    inventoryOpsSaving = false
                                }
                            },
                            onRecordStockTransfer = { productId, qty, fromLocation, toLocation ->
                                coroutineScope.launch {
                                    if (governanceStockTransferRequiresApproval && governanceRole == "kasir") {
                                        inventoryOpsError = "Aksi stok perlu approval Supervisor/Admin sesuai kebijakan governance."
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Stock transfer ditolak: role kasir memerlukan approval",
                                            category = "governance",
                                        )
                                        return@launch
                                    }
                                    inventoryOpsSaving = true
                                    inventoryOpsMessage = null
                                    inventoryOpsError = null
                                    runCatching {
                                        inventoryRepository.recordStockTransferOffline(
                                            productLocalId = productId,
                                            qty = qty,
                                            fromLocation = fromLocation,
                                            toLocation = toLocation,
                                            requestedByRole = governanceRole,
                                            approvalRequired = governanceStockTransferRequiresApproval,
                                        )
                                    }.onSuccess {
                                        inventoryOpsMessage = "Stock transfer tersimpan lokal dan siap sinkronisasi."
                                        syncScheduler.triggerImmediateSync()
                                    }.onFailure { error ->
                                        inventoryOpsError = error.message ?: "Gagal mencatat stock transfer."
                                    }
                                    inventoryOpsSaving = false
                                }
                            },
                            taxPercent = taxPercent,
                            onVoidSale = { receipt ->
                                coroutineScope.launch {
                                    val byReceipt = saleDao.getLatestSaleByReceiptNo(receipt.receiptNo)
                                    val byId = receipt.saleLocalId
                                        .takeIf { it.isNotBlank() }
                                        ?.let { saleDao.getSaleByLocalId(it) }
                                    val canonicalSale = byReceipt
                                        ?: byId
                                    val canonicalSource = when {
                                        byReceipt != null -> "db_by_receipt"
                                        byId != null -> "db_by_id"
                                        else -> "db_not_found"
                                    }

                                    if (canonicalSale == null) {
                                        val message = "Permintaan void tidak valid: sale canonical tidak ditemukan."
                                        showToastSafe(message)
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Void ditolak untuk ${receipt.receiptNo}: canonical_sale_not_found",
                                            category = "governance",
                                        )
                                        return@launch
                                    }

                                    if (canonicalSale.syncStatus == SyncStatus.DRAFT) {
                                        val message = "Permintaan void tidak valid: status=${canonicalSale.syncStatus.name}"
                                        showToastSafe(message)
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Void ditolak untuk ${receipt.receiptNo}: status_not_eligible_${canonicalSale.syncStatus.name}",
                                            category = "governance",
                                        )
                                        return@launch
                                    }

                                    val receiptForVoid = receipt.copy(
                                        saleLocalId = canonicalSale.localId,
                                        totalAmount = canonicalSale.totalAmount,
                                        syncStatusLabel = canonicalSale.syncStatus.name,
                                    )

                                    val validation = validateVoidRequest(
                                        saleLocalId = receiptForVoid.saleLocalId,
                                        receiptNo = receiptForVoid.receiptNo,
                                        totalAmount = receiptForVoid.totalAmount,
                                        subtotalAmount = canonicalSale.subtotal,
                                        discountAmount = canonicalSale.discountAmount,
                                        paidTotal = receipt.paidAmount,
                                        actorRoleRaw = governanceRole,
                                        currentSyncStatusLabel = receiptForVoid.syncStatusLabel,
                                    )
                                    if (!validation.canEnqueue) {
                                        showToastSafe(validation.userMessage)
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Void ditolak untuk ${receiptForVoid.receiptNo}: ${validation.reasonCode}",
                                            category = "governance",
                                        )
                                        return@launch
                                    }

                                    if (resolveVoidReceiptStatus(receiptForVoid.syncStatusLabel) == VoidReceiptStatus.NONE) {
                                        refundedSaleIds.add(receiptForVoid.saleLocalId)
                                        val index = receiptHistory.indexOfFirst {
                                            it.saleLocalId == receiptForVoid.saleLocalId || it.receiptNo == receiptForVoid.receiptNo
                                        }
                                        if (index >= 0) {
                                            receiptHistory[index] = receiptHistory[index].copy(syncStatusLabel = validation.nextStatusLabel ?: "VOID_REQUESTED")
                                        }
                                    }

                                    val fifoReversalPayload = runCatching {
                                        repository.reverseSaleFifoAllocationsOffline(
                                            saleLocalId = receiptForVoid.saleLocalId,
                                            reversalRef = receiptForVoid.receiptNo,
                                        )
                                    }.getOrDefault("[]")

                                    appendAuditEvent(
                                        auditEventDao,
                                        "Void transaksi diminta untuk ${receiptForVoid.receiptNo} (approval_required=${validation.approvalRequired}, approver_level=${validation.requiredApproverLevel})",
                                        category = "pos",
                                    )
                                    val voidPayload = buildVoidSaleOutboxPayload(
                                        saleLocalId = receiptForVoid.saleLocalId,
                                        receiptNo = receiptForVoid.receiptNo,
                                        requestedByRole = governanceRole,
                                        validation = validation,
                                        fifoReversalAllocationsJson = fifoReversalPayload,
                                    )
                                    syncActionDao.enqueue(
                                        com.umkm.offline.core.database.entity.SyncActionEntity(
                                            actionId = UUID.randomUUID().toString(),
                                            entityType = com.umkm.offline.core.common.EntityType.SALE,
                                            entityId = receiptForVoid.saleLocalId,
                                            actionType = com.umkm.offline.core.common.SyncActionType.FINALIZE_SALE,
                                            payloadJson = voidPayload,
                                            idempotencyKey = UUID.randomUUID().toString(),
                                            retryCount = 0,
                                            nextRetryTime = System.currentTimeMillis(),
                                            actionStatus = com.umkm.offline.core.common.OutboxStatus.PENDING,
                                            lockedUntil = null,
                                            lastError = null,
                                            createdAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    syncScheduler.triggerImmediateSync()
                                }
                            },
                            onOpenSync = { activeTab = AppTab.SETTINGS },
                            initialReceiptNo = pendingHistoryReceiptNo,
                            initialSaleLocalId = pendingHistorySaleLocalId,
                            onInitialDetailConsumed = {
                                pendingHistoryReceiptNo = null
                                pendingHistorySaleLocalId = null
                            },
                            onRequestExit = { activeTab = AppTab.HOME },
                        )
                    }

                    AppTab.PRODUCTS -> {
                        ProductsScreen(
                            products = posState.products,
                            recipeMaterialCandidates = recipeMaterialCandidates,
                            canUseMaterials = activePlan.canAccess(GatedFeature.PRODUCT_MATERIALS),
                            existingCategories = existingProductCategories,
                            recipeCountByProduct = recipeCountByProduct,
                            recipeLinesByProduct = recipeLinesByProduct,
                            loadFifoLayers = { productLocalId ->
                                withContext(Dispatchers.IO) {
                                    inventoryLayerDao.findAvailableLayersByProduct(productLocalId).map { layer ->
                                        ProductFifoLayerUi(
                                            localId = layer.localId,
                                            sourceRef = layer.sourceRef,
                                            qtyOriginal = layer.qtyOriginal,
                                            qtyAvailable = layer.qtyAvailable,
                                            unitCost = layer.unitCost,
                                            receivedAt = layer.receivedAt,
                                        )
                                    }
                                }
                            },
                            onRequestExit = { activeTab = AppTab.HOME },
                            onCreateProduct = { draft ->
                                coroutineScope.launch {
                                    val trimmedName = draft.name.trim()
                                    if (trimmedName.isBlank()) {
                                        showToastSafe("Nama produk wajib diisi.")
                                        return@launch
                                    }

                                    val editingLocalId = draft.editingProductLocalId?.trim()?.ifBlank { null }
                                    val existingProduct = editingLocalId?.let { productDao.findByLocalId(it) }
                                    if (editingLocalId != null && existingProduct == null) {
                                        showToastSafe("Produk yang akan diedit tidak ditemukan.")
                                        Log.d("ProductEdit", "save_result productLocalId=$editingLocalId result=failed_not_found")
                                        return@launch
                                    }

                                    val now = System.currentTimeMillis()

                                    suspend fun upsertSingleProduct(
                                        name: String,
                                        price: Long,
                                        stockOnHand: Double,
                                        categoryName: String,
                                        itemType: String,
                                        productMode: String,
                                        preferredLocalId: String? = null,
                                    ): String {
                                        val existing = preferredLocalId?.let { productDao.findByLocalId(it) }
                                        val localId = preferredLocalId ?: UUID.randomUUID().toString()
                                        productDao.upsert(
                                            ProductEntity(
                                                localId = localId,
                                                serverId = existing?.serverId,
                                                name = name,
                                                categoryName = categoryName,
                                                itemType = itemType,
                                                productMode = productMode,
                                                barcode = existing?.barcode,
                                                price = price.coerceAtLeast(0L),
                                                stockOnHand = stockOnHand.coerceAtLeast(0.0),
                                                syncStatus = SyncStatus.PENDING_SYNC,
                                                version = (existing?.version ?: 0L) + 1L,
                                                updatedAtLocal = now,
                                                updatedAtServer = null,
                                            ),
                                        )

                                        enqueueOutboxIfAbsent(
                                            syncActionDao = syncActionDao,
                                            action = buildProductOutboxAction(
                                                entityId = localId,
                                                payloadJson = "{\"product_local_id\":\"$localId\",\"name\":\"${name.replace("\"", "\\\"")}\",\"category_name\":\"${categoryName.replace("\"", "\\\"")}\",\"item_type\":\"$itemType\",\"product_mode\":\"$productMode\",\"price\":${price.coerceAtLeast(0L)},\"stock_on_hand\":${stockOnHand.coerceAtLeast(0.0)}}",
                                                now = now,
                                                idempotencyKey = "product:upsert:$localId",
                                            ),
                                        )

                                        return localId
                                    }

                                    var primaryProductId: String? = null
                                    val modeMapping = when (draft.mode) {
                                        NewProductMode.DEFAULT -> "finished_good" to "standard"
                                        NewProductMode.ADD_ON -> "raw_material" to "add_on"
                                        NewProductMode.RECIPE -> "finished_good" to "bom"
                                        NewProductMode.VARIANT -> "finished_good" to "variant"
                                    }
                                    val productItemType = modeMapping.first
                                    val productMode = modeMapping.second
                                    Log.d(
                                        "ProductModeDomain",
                                        "save_attempt mode=${draft.mode.name.lowercase()} itemType=$productItemType productMode=$productMode",
                                    )
                                    if (editingLocalId != null) {
                                        val changedFields = mutableListOf<String>()
                                        if (existingProduct?.name != trimmedName) changedFields.add("name")
                                        if (existingProduct?.categoryName != draft.category) changedFields.add("category")
                                        if ((existingProduct?.price ?: 0L) != draft.sellPrice) changedFields.add("sell_price")
                                        if ((existingProduct?.itemType ?: "") != productItemType) changedFields.add("item_type")
                                        if ((existingProduct?.productMode ?: "") != productMode) changedFields.add("product_mode")
                                        Log.d(
                                            "ProductEdit",
                                            "save_attempt productLocalId=$editingLocalId mode=${draft.mode.name.lowercase()} changedFields=${changedFields.ifEmpty { listOf("none") }}",
                                        )
                                        primaryProductId = upsertSingleProduct(
                                            name = trimmedName,
                                            price = draft.sellPrice,
                                            stockOnHand = existingProduct?.stockOnHand ?: draft.openingStock.toDouble(),
                                            categoryName = draft.category,
                                            itemType = productItemType,
                                            productMode = productMode,
                                            preferredLocalId = editingLocalId,
                                        )
                                    } else {
                                        when (draft.mode) {
                                            NewProductMode.DEFAULT,
                                            NewProductMode.RECIPE,
                                            NewProductMode.ADD_ON -> {
                                                primaryProductId = upsertSingleProduct(
                                                    name = trimmedName,
                                                    price = draft.sellPrice,
                                                    stockOnHand = draft.openingStock.toDouble(),
                                                    categoryName = draft.category,
                                                    itemType = productItemType,
                                                    productMode = productMode,
                                                )
                                            }

                                            NewProductMode.VARIANT -> {
                                                if (draft.variants.isEmpty()) {
                                                    primaryProductId = upsertSingleProduct(
                                                        name = trimmedName,
                                                        price = draft.sellPrice,
                                                        stockOnHand = draft.openingStock.toDouble(),
                                                        categoryName = draft.category,
                                                        itemType = productItemType,
                                                        productMode = productMode,
                                                    )
                                                } else {
                                                    val unitStock = if (draft.variants.isNotEmpty()) {
                                                        draft.openingStock.toDouble() / draft.variants.size
                                                    } else {
                                                        0.0
                                                    }
                                                    draft.variants.forEachIndexed { index, variant ->
                                                        val productId = upsertSingleProduct(
                                                            name = "$trimmedName - ${variant.name}",
                                                            price = variant.price,
                                                            stockOnHand = unitStock,
                                                            categoryName = draft.category,
                                                            itemType = productItemType,
                                                            productMode = productMode,
                                                        )
                                                        if (index == 0) {
                                                            primaryProductId = productId
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    Log.d(
                                        "ProductModeDomain",
                                        "saved_primary_product mode=${draft.mode.name.lowercase()} itemType=$productItemType productMode=$productMode localId=${primaryProductId.orEmpty()}",
                                    )

                                    val recipeProductId = primaryProductId
                                    if (draft.mode == NewProductMode.RECIPE && recipeProductId != null && draft.recipeLines.isNotEmpty()) {
                                        val materialLookup = inventoryProducts
                                            .groupBy { it.name.trim().lowercase() }
                                            .mapValues { (_, items) -> items.firstOrNull()?.localId }
                                        recipeRepository.replaceRecipeOffline(
                                            productLocalId = recipeProductId,
                                            lines = draft.recipeLines.map { line ->
                                                val normalizedName = line.materialName.trim().lowercase()
                                                val resolvedMaterialId = line.materialProductLocalId
                                                    ?: materialLookup[normalizedName]
                                                RecipeDraftLine(
                                                    materialProductLocalId = resolvedMaterialId,
                                                    materialName = line.materialName,
                                                    qty = line.qty,
                                                    unit = line.unit,
                                                )
                                            },
                                        )
                                    }

                                    appendAuditEvent(
                                        auditEventDao,
                                        if (editingLocalId != null) {
                                            "Produk diupdate via APK: ${draft.name} (mode=${draft.mode.name.lowercase()})"
                                        } else {
                                            "Produk dibuat via APK: ${draft.name} (mode=${draft.mode.name.lowercase()})"
                                        },
                                        category = "product",
                                    )
                                    if (editingLocalId != null) {
                                        Log.d("ProductEdit", "save_result productLocalId=$editingLocalId result=success")
                                    }
                                    showToastSafe(if (editingLocalId != null) "Perubahan produk tersimpan lokal." else "Produk tersimpan lokal.")
                                    syncScheduler.triggerImmediateSync()
                                }
                            },
                            onSaveRecipe = { productLocalId, lines ->
                                coroutineScope.launch {
                                    runCatching {
                                        recipeRepository.replaceRecipeOffline(
                                            productLocalId = productLocalId,
                                            lines = lines.map { line ->
                                                RecipeDraftLine(
                                                    materialProductLocalId = line.materialProductLocalId,
                                                    materialName = line.materialName,
                                                    qty = line.qty,
                                                    unit = line.unit,
                                                )
                                            },
                                        )
                                    }.onSuccess {
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Resep produk diperbarui untuk $productLocalId (${lines.size} bahan)",
                                            category = "product",
                                        )
                                        showToastSafe("Resep tersimpan lokal.")
                                    }.onFailure { error ->
                                        showToastSafe(error.message ?: "Gagal menyimpan resep.")
                                    }
                                }
                            },
                        )
                    }

                    AppTab.SETTINGS -> {
                        SettingsScreen(
                            storeName = loggedStoreCode ?: "-",
                            storeAddress = "Jl. Merdeka No. 10",
                            appVersion = "2.4.0 (Build 108)",
                            activePlan = activePlan,
                            connectivityState = connectivityState,
                            pendingCount = pendingCount,
                            failedCount = failedCount,
                            isSyncing = isSyncing,
                            canUseProFinance = activePlan.canAccess(GatedFeature.FINANCE_BALANCE_SHEET),
                            canUsePurchaseManagement = activePlan.canAccess(GatedFeature.REPORT_PURCHASES_VIEW),
                            canUseGovernance = false,
                            governanceCurrentRole = governanceRole,
                            governanceVoidRequiresApproval = governanceVoidRequiresApproval,
                            governanceStockAdjustmentRequiresApproval = governanceStockAdjustmentRequiresApproval,
                            governanceStockTransferRequiresApproval = governanceStockTransferRequiresApproval,
                            governanceCashMovementRequiresApproval = governanceCashMovementRequiresApproval,
                            governanceDailySnapshotRequiresApproval = governanceDailySnapshotRequiresApproval,
                            governancePurchaseMutationRequiresApproval = governancePurchaseMutationRequiresApproval,
                            isWriteLocked = isWriteLocked,
                            taxPercent = taxPercent,
                            todaySales = todaySales,
                            todayTransactions = todayTransactions,
                            grossProfitEstimate = (todaySales * 0.35).toLong(),
                            cashInTotal = cashInTotal,
                            cashOutTotal = cashOutTotal,
                            stockValueEstimate = stockValueEstimate,
                            syncActionSummaryText = syncActionSummaryText,
                            syncFailedSummaryText = syncFailedSummaryText,
                            financeSaving = financeSaving,
                            financeMessage = financeMessage,
                            financeError = financeError,
                            auditEvents = recentAuditEvents,
                            purchaseSuppliers = purchaseState.suppliers,
                            purchaseProducts = purchaseProducts,
                            purchaseSaving = purchaseState.isSaving,
                            purchaseMessage = purchaseState.message,
                            purchaseError = purchaseState.errorMessage,
                            purchaseCreateCanSubmit = purchaseState.createCanSubmit,
                            purchaseCreateFieldErrors = purchaseState.createFieldErrors,
                            purchaseCreateFormError = purchaseState.createFormError,
                            purchaseReceiveCanSubmit = purchaseState.receiveCanSubmit,
                            purchaseReceiveActionEnabled = purchaseState.receiveActionEnabled,
                            purchaseReceiveFieldErrors = purchaseState.receiveFieldErrors,
                            purchaseReceiveActionError = purchaseState.receiveActionError,
                            purchaseReturnCanSubmit = purchaseState.returnCanSubmit,
                            purchaseReturnActionEnabled = purchaseState.returnActionEnabled,
                            purchaseReturnFieldErrors = purchaseState.returnFieldErrors,
                            purchaseReturnActionError = purchaseState.returnActionError,
                            onCreateSupplier = { name, phone, address, notes ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Aksi purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Create supplier ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@SettingsScreen
                                }
                                purchaseViewModel.createSupplier(
                                    name = name,
                                    phone = phone,
                                    address = address,
                                    notes = notes,
                                    requestedByRole = governanceRole,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onFinalizePurchase = { purchaseDraft ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Finalize purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Finalize purchase ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@SettingsScreen
                                }
                                val invalidLine = purchaseDraft.items.firstOrNull { line ->
                                    val product = purchaseProductById[line.productLocalId]
                                    !isPurchaseProductAllowed(product?.itemType, product?.productMode)
                                }
                                if (invalidLine != null) {
                                    showToastSafe("Produk resep tidak dapat dibeli sebagai stok")
                                    Log.d("PurchaseValidation", "finalize_blocked productLocalId=${invalidLine.productLocalId} result=blocked")
                                    return@SettingsScreen
                                }
                                purchaseViewModel.finalizePurchase(
                                    purchaseNo = purchaseDraft.purchaseNo,
                                    supplierLocalId = purchaseDraft.supplierLocalId,
                                    items = purchaseDraft.items.map { line ->
                                        PurchaseItemDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.unitCost,
                                        )
                                    },
                                    discountAmount = purchaseDraft.discountAmount,
                                    dueDate = purchaseDraft.dueDate,
                                    paymentMethod = purchaseDraft.paymentMethod,
                                    paymentStatus = purchaseDraft.paymentStatus,
                                    cashSource = purchaseDraft.cashSource,
                                    requestedByRole = governanceRole,
                                    note = purchaseDraft.note,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onEvaluateFinalizePurchaseDraft = { purchaseDraft ->
                                purchaseViewModel.evaluateFinalizePurchaseDraft(
                                    supplierLocalId = purchaseDraft.supplierLocalId,
                                    items = purchaseDraft.items.map { line ->
                                        PurchaseItemDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.unitCost,
                                        )
                                    },
                                    paymentMethod = purchaseDraft.paymentMethod,
                                    dueDate = purchaseDraft.dueDate,
                                    transactionDate = null,
                                )
                            },
                            onEvaluateMarkPurchaseReceivedDraft = { receiveDraft ->
                                purchaseViewModel.evaluateMarkPurchaseReceivedDraft(
                                    purchaseLocalId = receiveDraft.purchaseLocalId,
                                    purchaseStatus = receiveDraft.purchaseStatus,
                                    receiveLines = receiveDraft.items.map { line ->
                                        PurchaseReceiveLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            remainingQty = line.remainingQty,
                                        )
                                    },
                                    payloadTransactionDate = receiveDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                )
                            },
                            onMarkPurchaseReceived = { receiveDraft ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Receiving purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Receiving purchase ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@SettingsScreen
                                }
                                purchaseViewModel.markPurchaseReceived(
                                    purchaseLocalId = receiveDraft.purchaseLocalId,
                                    purchaseStatus = receiveDraft.purchaseStatus,
                                    receiveLines = receiveDraft.items.map { line ->
                                        PurchaseReceiveLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            remainingQty = line.remainingQty,
                                        )
                                    },
                                    payloadTransactionDate = receiveDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                    requestedByRole = governanceRole,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onEvaluateRecordPurchaseReturnDraft = { returnDraft ->
                                purchaseViewModel.evaluateRecordPurchaseReturnDraft(
                                    purchaseLocalId = returnDraft.purchaseLocalId,
                                    purchaseStatus = returnDraft.purchaseStatus,
                                    supplierLocalId = returnDraft.supplierLocalId,
                                    purchaseSupplierLocalId = returnDraft.purchaseSupplierLocalId,
                                    reason = returnDraft.reason,
                                    items = returnDraft.items.map { line ->
                                        PurchaseReturnLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.price,
                                            localRemainingQty = line.localRemainingQty,
                                        )
                                    },
                                    payloadTransactionDate = returnDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                )
                            },
                            onRecordPurchaseReturn = { returnDraft ->
                                if (governancePurchaseMutationRequiresApproval && governanceRole == "kasir") {
                                    showToastSafe("Retur purchase perlu approval Supervisor/Admin.")
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Retur purchase ditolak: role kasir memerlukan approval", category = "governance")
                                    }
                                    return@SettingsScreen
                                }
                                purchaseViewModel.recordPurchaseReturn(
                                    purchaseLocalId = returnDraft.purchaseLocalId,
                                    purchaseStatus = returnDraft.purchaseStatus,
                                    supplierLocalId = returnDraft.supplierLocalId,
                                    purchaseSupplierLocalId = returnDraft.purchaseSupplierLocalId,
                                    reason = returnDraft.reason,
                                    items = returnDraft.items.map { line ->
                                        PurchaseReturnLineDraft(
                                            productLocalId = line.productLocalId,
                                            qty = line.qty,
                                            unitCost = line.price,
                                            localRemainingQty = line.localRemainingQty,
                                        )
                                    },
                                    payloadTransactionDate = returnDraft.transactionDate,
                                    isWriteLocked = isWriteLocked,
                                    requestedByRole = governanceRole,
                                    approvalRequired = governancePurchaseMutationRequiresApproval,
                                )
                            },
                            onRecordCashMovement = { direction, amount, note ->
                                coroutineScope.launch {
                                    if (governanceCashMovementRequiresApproval && governanceRole == "kasir") {
                                        financeError = "Aksi finance perlu approval Supervisor/Admin sesuai kebijakan governance."
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Cash movement ditolak: role kasir memerlukan approval",
                                            category = "governance",
                                        )
                                        return@launch
                                    }
                                    financeSaving = true
                                    financeMessage = null
                                    financeError = null
                                    runCatching {
                                        financeRepository.recordCashMovementOffline(
                                            shiftLocalId = activeShift?.localId ?: "manual-shift",
                                            direction = direction,
                                            amount = amount,
                                            note = note,
                                            requestedByRole = governanceRole,
                                            approvalRequired = governanceCashMovementRequiresApproval,
                                        )
                                    }.onSuccess {
                                        financeMessage = "Arus kas tersimpan lokal dan siap sinkronisasi."
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Arus kas ${direction.lowercase()} dicatat sebesar $amount",
                                            category = "finance",
                                        )
                                        syncScheduler.triggerImmediateSync()
                                    }.onFailure { error ->
                                        financeError = error.message ?: "Gagal mencatat arus kas."
                                    }
                                    financeSaving = false
                                }
                            },
                            onSnapshotDailyReport = {
                                coroutineScope.launch {
                                    if (governanceDailySnapshotRequiresApproval && governanceRole == "kasir") {
                                        financeError = "Aksi finance perlu approval Supervisor/Admin sesuai kebijakan governance."
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Snapshot laporan harian ditolak: role kasir memerlukan approval",
                                            category = "governance",
                                        )
                                        return@launch
                                    }
                                    financeSaving = true
                                    financeMessage = null
                                    financeError = null
                                    runCatching {
                                        financeRepository.snapshotDailyReportOffline(
                                            salesTotal = todaySales,
                                            transactionCount = todayTransactions,
                                            grossProfitEstimate = (todaySales * 0.35).toLong(),
                                            cashInTotal = cashInTotal,
                                            cashOutTotal = cashOutTotal,
                                            stockValueEstimate = stockValueEstimate,
                                            requestedByRole = governanceRole,
                                            approvalRequired = governanceDailySnapshotRequiresApproval,
                                        )
                                    }.onSuccess {
                                        financeMessage = "Snapshot laporan harian tersimpan lokal."
                                        appendAuditEvent(
                                            auditEventDao,
                                            "Snapshot laporan harian dibuat",
                                            category = "finance",
                                        )
                                        syncScheduler.triggerImmediateSync()
                                    }.onFailure { error ->
                                        financeError = error.message ?: "Gagal membuat snapshot laporan harian."
                                    }
                                    financeSaving = false
                                }
                            },
                            onPlanChanged = { plan ->
                                val previousCode = activePlanCode
                                activePlanCode = plan.code
                                if (plan.code != previousCode) {
                                    coroutineScope.launch {
                                        appendAuditEvent(auditEventDao, "Paket diubah ${previousCode} -> ${plan.code}", category = "subscription")
                                    }
                                }
                            },
                            onGovernanceRoleChanged = { role ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_CURRENT_ROLE,
                                            value = role,
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(auditEventDao, "Role governance aktif diubah ke $role", category = "governance")
                                }
                            },
                            onGovernanceVoidApprovalChanged = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_VOID_REQUIRES_APPROVAL,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(
                                        auditEventDao,
                                        if (enabled) "Policy void requires approval diaktifkan" else "Policy void requires approval dinonaktifkan",
                                        category = "governance",
                                    )
                                }
                            },
                            onGovernanceStockAdjustmentApprovalChanged = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_STOCK_ADJUSTMENT_REQUIRES_APPROVAL,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(auditEventDao, "Policy stock adjustment approval diubah ke $enabled", category = "governance")
                                }
                            },
                            onGovernanceStockTransferApprovalChanged = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_STOCK_TRANSFER_REQUIRES_APPROVAL,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(auditEventDao, "Policy stock transfer approval diubah ke $enabled", category = "governance")
                                }
                            },
                            onGovernanceCashMovementApprovalChanged = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_CASH_MOVEMENT_REQUIRES_APPROVAL,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(auditEventDao, "Policy cash movement approval diubah ke $enabled", category = "governance")
                                }
                            },
                            onGovernanceDailySnapshotApprovalChanged = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_DAILY_SNAPSHOT_REQUIRES_APPROVAL,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(auditEventDao, "Policy daily snapshot approval diubah ke $enabled", category = "governance")
                                }
                            },
                            onGovernancePurchaseMutationApprovalChanged = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_GOV_PURCHASE_MUTATION_REQUIRES_APPROVAL,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(auditEventDao, "Policy purchase mutation approval diubah ke $enabled", category = "governance")
                                }
                            },
                            onToggleWriteLock = { enabled ->
                                coroutineScope.launch {
                                    appPolicyDao.upsert(
                                        AppPolicyEntity(
                                            key = POLICY_WRITE_LOCK_ENABLED,
                                            value = enabled.toString(),
                                            updatedAt = System.currentTimeMillis(),
                                        ),
                                    )
                                    appendAuditEvent(
                                        auditEventDao,
                                        if (enabled) "Lock period diaktifkan" else "Lock period dinonaktifkan",
                                        category = "finance",
                                    )
                                }
                            },
                            onSyncNow = {
                                coroutineScope.launch {
                                    appendAuditEvent(auditEventDao, "Sinkronisasi manual dijalankan", category = "sync")
                                }
                                syncScheduler.triggerImmediateSync()
                            },
                            onRequestExit = { activeTab = AppTab.HOME },
                        )
                    }
                }

                if (pendingPreviousDayShift != null) {
                    Card(
                        modifier = androidx.compose.ui.Modifier
                            .align(androidx.compose.ui.Alignment.TopCenter)
                            .padding(horizontal = 12.dp, vertical = 8.dp)
                            .fillMaxWidth()
                            .clickable { showLateShiftDialog = true },
                    ) {
                        Row(
                            modifier = androidx.compose.ui.Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 12.dp, vertical = 10.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(
                                text = "Shift sebelumnya belum ditutup. Isi kas akhir kemarin.",
                                fontWeight = FontWeight.SemiBold,
                                fontSize = 12.sp,
                            )
                            Text(
                                text = "Isi",
                                color = MaterialTheme.colorScheme.primary,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                    }
                }
            }
        }

        if (showOpenShiftDialog) {
            ShiftCashInputDialog(
                title = "Buka Shift",
                label = "Kas awal",
                placeholder = "Contoh: 100000",
                confirmLabel = "Buka",
                value = openShiftCashInput,
                onValueChange = { openShiftCashInput = it },
                onDismiss = { showOpenShiftDialog = false },
                onConfirm = {
                    val openingCash = parseCashAmountOrNull(openShiftCashInput)
                    if (openingCash == null || openingCash <= 0L) {
                        showToastSafe("Kas awal wajib diisi dan harus lebih dari 0.")
                        return@ShiftCashInputDialog
                    }

                    coroutineScope.launch {
                        val now = System.currentTimeMillis()
                        if (pendingPreviousDayShift != null) {
                            showToastOnMain("Shift kemarin belum ditutup. Isi kas akhir kemarin dulu.")
                            showLateShiftDialog = true
                            return@launch
                        }

                        val existingActiveShift = getActiveShift(shiftDao)
                        if (existingActiveShift != null) {
                            showToastOnMain("Masih ada shift aktif. Tutup shift dulu.")
                            return@launch
                        }

                        val userLocalId = loggedStoreCode?.trim().orEmpty()
                        if (userLocalId.isBlank() || actorBranchLocalId.isNullOrBlank() || actorRegisterLocalId.isNullOrBlank()) {
                            showToastOnMain("Session kasir/branch/register belum lengkap.")
                            return@launch
                        }

                        val shiftLocalId = UUID.randomUUID().toString()
                        shiftDao.upsert(
                            ShiftEntity(
                                localId = shiftLocalId,
                                serverId = null,
                                userLocalId = userLocalId,
                                branchLocalId = actorBranchLocalId,
                                registerLocalId = actorRegisterLocalId,
                                openedAt = now,
                                closedAt = null,
                                openingCash = openingCash,
                                closingCash = null,
                                status = "OPEN",
                                syncStatus = SyncStatus.PENDING_SYNC,
                                version = 1L,
                                updatedAtLocal = now,
                                updatedAtServer = null,
                            ),
                        )

                        enqueueOutboxIfAbsent(
                            syncActionDao = syncActionDao,
                            action = buildShiftOutboxAction(
                                entityId = shiftLocalId,
                                actionType = SyncActionType.OPEN_SHIFT,
                                payloadJson = "{\"shift_local_id\":\"$shiftLocalId\",\"opened_at\":$now,\"opening_cash\":$openingCash,\"branch_local_id\":\"$actorBranchLocalId\",\"register_local_id\":\"$actorRegisterLocalId\"}",
                                now = now,
                                idempotencyKey = "shift:open:$shiftLocalId",
                            ),
                        )

                        appendAuditEvent(auditEventDao, "Shift dibuka (local): $shiftLocalId, kas_awal=$openingCash", category = "shift")
                        showToastOnMain("Shift berhasil dibuka.")
                        showOpenShiftDialog = false
                        openShiftCashInput = ""
                        syncScheduler.triggerImmediateSync()
                    }
                },
            )
        }

        if (showLateShiftDialog && pendingPreviousDayShift != null) {
            ShiftCashInputDialog(
                title = "Shift Kemarin Belum Ditutup",
                label = "Kas akhir kemarin",
                placeholder = "Contoh: 175000",
                confirmLabel = "Simpan & Tutup Shift",
                value = lateShiftCashInput,
                onValueChange = { lateShiftCashInput = it },
                onDismiss = { showLateShiftDialog = false },
                onConfirm = {
                    val lateClosingCash = parseCashAmountOrNull(lateShiftCashInput)
                    if (lateClosingCash == null || lateClosingCash < 0L) {
                        showToastSafe("Kas akhir kemarin wajib diisi.")
                        return@ShiftCashInputDialog
                    }

                    coroutineScope.launch {
                        val lateShiftId = pendingPreviousDayShift.localId
                        val shiftFromDb = shiftDao.getByLocalId(lateShiftId)
                        if (shiftFromDb == null || !shiftFromDb.status.equals("OPEN", ignoreCase = true)) {
                            showToastOnMain("Shift kemarin sudah tidak aktif.")
                            showLateShiftDialog = false
                            lateShiftCashInput = ""
                            return@launch
                        }

                        val now = System.currentTimeMillis()
                        val expectedClosingCash = calculateExpectedClosingCash(
                            shift = shiftFromDb,
                            closedAt = now,
                            saleDao = saleDao,
                            cashMovementDao = cashMovementDao,
                        )
                        val difference = calculateShiftCashDifference(
                            expectedClosingCash = expectedClosingCash,
                            actualClosingCash = lateClosingCash,
                        )

                        shiftDao.upsert(
                            shiftFromDb.copy(
                                closedAt = now,
                                closingCash = lateClosingCash,
                                status = "CLOSED",
                                syncStatus = SyncStatus.PENDING_SYNC,
                                version = shiftFromDb.version + 1,
                                updatedAtLocal = now,
                            ),
                        )

                        enqueueOutboxIfAbsent(
                            syncActionDao = syncActionDao,
                            action = buildShiftOutboxAction(
                                entityId = shiftFromDb.localId,
                                actionType = SyncActionType.CLOSE_SHIFT,
                                payloadJson = "{\"shift_local_id\":\"${shiftFromDb.localId}\",\"closed_at\":$now,\"closing_cash\":$lateClosingCash}",
                                now = now,
                                idempotencyKey = "shift:lateclose:${shiftFromDb.localId}",
                            ),
                        )

                        appendAuditEvent(
                            auditEventDao,
                            "Shift ditutup terlambat: ${shiftFromDb.localId}, kas_sistem=$expectedClosingCash, kas_akhir=$lateClosingCash, selisih=$difference",
                            category = "shift",
                        )
                        showToastOnMain("Shift kemarin berhasil ditutup. Selisih kas: $difference")
                        showLateShiftDialog = false
                        lateShiftCashInput = ""
                        syncScheduler.triggerImmediateSync()
                    }
                },
            )
        }

        if (showCloseShiftDialog) {
            ShiftCashInputDialog(
                title = "Tutup Shift",
                label = "Kas akhir aktual",
                placeholder = "Contoh: 175000",
                confirmLabel = "Tutup",
                value = closeShiftCashInput,
                onValueChange = { closeShiftCashInput = it },
                onDismiss = { showCloseShiftDialog = false },
                onConfirm = {
                    val actualClosingCash = parseCashAmountOrNull(closeShiftCashInput)
                    if (actualClosingCash == null || actualClosingCash < 0L) {
                        showToastSafe("Kas akhir wajib diisi.")
                        return@ShiftCashInputDialog
                    }

                    coroutineScope.launch {
                        val now = System.currentTimeMillis()
                        val currentShift = getActiveShift(shiftDao)
                        if (currentShift == null) {
                            showToastOnMain("Tidak ada shift aktif.")
                            showCloseShiftDialog = false
                            return@launch
                        }

                        val expectedClosingCash = calculateExpectedClosingCash(
                            shift = currentShift,
                            closedAt = now,
                            saleDao = saleDao,
                            cashMovementDao = cashMovementDao,
                        )
                        val difference = calculateShiftCashDifference(
                            expectedClosingCash = expectedClosingCash,
                            actualClosingCash = actualClosingCash,
                        )

                        shiftDao.upsert(
                            currentShift.copy(
                                closedAt = now,
                                closingCash = actualClosingCash,
                                status = "CLOSED",
                                syncStatus = SyncStatus.PENDING_SYNC,
                                version = currentShift.version + 1,
                                updatedAtLocal = now,
                            ),
                        )

                        enqueueOutboxIfAbsent(
                            syncActionDao = syncActionDao,
                            action = buildShiftOutboxAction(
                                entityId = currentShift.localId,
                                actionType = SyncActionType.CLOSE_SHIFT,
                                payloadJson = "{\"shift_local_id\":\"${currentShift.localId}\",\"closed_at\":$now,\"closing_cash\":$actualClosingCash}",
                                now = now,
                                idempotencyKey = "shift:close:${currentShift.localId}",
                            ),
                        )

                        appendAuditEvent(
                            auditEventDao,
                            "Shift ditutup (local): ${currentShift.localId}, kas_sistem=$expectedClosingCash, kas_akhir=$actualClosingCash, selisih=$difference",
                            category = "shift",
                        )
                        showToastOnMain("Shift ditutup. Selisih kas: $difference")
                        showCloseShiftDialog = false
                        closeShiftCashInput = ""
                        syncScheduler.triggerImmediateSync()
                    }
                },
            )
        }

        if (showExitAppDialog) {
            AlertDialog(
                onDismissRequest = { showExitAppDialog = false },
                title = { Text("Keluar aplikasi?") },
                text = { Text("Anda berada di halaman utama. Tekan keluar untuk menutup aplikasi.") },
                dismissButton = {
                    TextButton(onClick = { showExitAppDialog = false }) {
                        Text("Batal")
                    }
                },
                confirmButton = {
                    TextButton(
                        onClick = {
                            showExitAppDialog = false
                            (context as? ComponentActivity)?.finish()
                        },
                    ) {
                        Text("Keluar")
                    }
                },
            )
        }
    }
}

private fun buildShiftOutboxAction(
    entityId: String,
    actionType: SyncActionType,
    payloadJson: String,
    now: Long,
    idempotencyKey: String,
): SyncActionEntity {
    return SyncActionEntity(
        actionId = UUID.randomUUID().toString(),
        entityType = EntityType.SHIFT,
        entityId = entityId,
        actionType = actionType,
        payloadJson = payloadJson,
        idempotencyKey = idempotencyKey,
        retryCount = 0,
        nextRetryTime = now,
        actionStatus = OutboxStatus.PENDING,
        lockedUntil = null,
        lastError = null,
        createdAt = now,
    )
}

private fun buildProductOutboxAction(
    entityId: String,
    payloadJson: String,
    now: Long,
    idempotencyKey: String,
): SyncActionEntity {
    return SyncActionEntity(
        actionId = UUID.randomUUID().toString(),
        entityType = EntityType.PRODUCT,
        entityId = entityId,
        actionType = SyncActionType.UPSERT_PRODUCT,
        payloadJson = payloadJson,
        idempotencyKey = idempotencyKey,
        retryCount = 0,
        nextRetryTime = now,
        actionStatus = OutboxStatus.PENDING,
        lockedUntil = null,
        lastError = null,
        createdAt = now,
    )
}

private suspend fun enqueueOutboxIfAbsent(
    syncActionDao: SyncActionDao,
    action: SyncActionEntity,
) {
    if (!syncActionDao.existsByIdempotencyKey(action.idempotencyKey)) {
        syncActionDao.enqueue(action)
    }
}

private data class ShiftValidationResult(
    val allowed: Boolean,
    val message: String,
    val activeShift: ShiftEntity?,
)

private suspend fun getActiveShift(
    shiftDao: ShiftDao,
): ShiftEntity? {
    return shiftDao.getOpenShifts()
        .asSequence()
        .filter { !isShiftExpiredByDate(it.openedAt, System.currentTimeMillis()) }
        .sortedByDescending { it.openedAt }
        .firstOrNull()
}

private suspend fun validateShiftBeforeTransaction(
    shiftDao: ShiftDao,
    saleDao: SaleDao,
    cashMovementDao: CashMovementDao,
    syncActionDao: SyncActionDao,
    auditEventDao: AuditEventDao,
    now: Long,
): ShiftValidationResult {
    val openShifts = shiftDao.getOpenShifts()
    val pendingPreviousDayShift = openShifts.firstOrNull { shift ->
        isShiftExpiredByDate(shift.openedAt, now)
    }
    if (pendingPreviousDayShift != null) {
        return ShiftValidationResult(
            allowed = false,
            message = "Shift sebelumnya belum ditutup. Isi kas akhir kemarin dulu.",
            activeShift = null,
        )
    }

    val activeShifts = openShifts
        .filter { shift -> !isShiftExpiredByDate(shift.openedAt, now) }
        .sortedByDescending { it.openedAt }
    if (activeShifts.size > 1) {
        return ShiftValidationResult(
            allowed = false,
            message = "Terdapat lebih dari satu shift aktif. Selesaikan shift terlebih dahulu.",
            activeShift = null,
        )
    }

    val activeShift = activeShifts.firstOrNull()
    return if (activeShift == null) {
        ShiftValidationResult(
            allowed = false,
            message = "Transaksi diblokir. Buka shift dulu.",
            activeShift = null,
        )
    } else {
        ShiftValidationResult(
            allowed = true,
            message = "OK",
            activeShift = activeShift,
        )
    }
}

private suspend fun closeExpiredShiftIfAny(
    shiftDao: ShiftDao,
    saleDao: SaleDao,
    cashMovementDao: CashMovementDao,
    syncActionDao: SyncActionDao,
    auditEventDao: AuditEventDao,
    now: Long,
): Int {
    val expiredCount = shiftDao.getOpenShifts().count { shift ->
        isShiftExpiredByDate(shift.openedAt, now)
    }
    if (expiredCount > 0) {
        appendAuditEvent(
            auditEventDao = auditEventDao,
            message = "Deteksi shift expired tertunda: $expiredCount",
            category = "shift",
        )
    }
    return expiredCount
}

private suspend fun calculateExpectedClosingCash(
    shift: ShiftEntity,
    closedAt: Long,
    saleDao: SaleDao,
    cashMovementDao: CashMovementDao,
): Long {
    val salesTotal = saleDao.sumTotalAmountBetween(
        openedAt = shift.openedAt,
        closedAt = closedAt,
    )
    val cashInTotal = cashMovementDao.sumCashInByShift(shift.localId)
    val cashOutTotal = cashMovementDao.sumCashOutByShift(shift.localId)
    return (shift.openingCash + salesTotal + cashInTotal - cashOutTotal)
        .coerceAtLeast(0L)
}

internal fun calculateShiftCashDifference(
    expectedClosingCash: Long,
    actualClosingCash: Long,
): Long {
    return actualClosingCash - expectedClosingCash
}

internal fun parseCashAmountOrNull(input: String): Long? {
    val digitsOnly = input.filter { it.isDigit() }
    if (digitsOnly.isBlank()) {
        return null
    }
    return digitsOnly.toLongOrNull()
}

internal fun isShiftExpiredByDate(
    openedAt: Long,
    now: Long,
): Boolean {
    val todayStart = startOfDayMillis(now)
    return openedAt < todayStart
}

private fun ProductEntity.toRecipeMaterialCandidateOrNull(): RecipeMaterialCandidateUi? {
    val normalizedType = itemType?.trim()?.lowercase().orEmpty()
    val normalizedMode = productMode?.trim()?.lowercase().orEmpty()
    val normalizedItemType = when (normalizedType) {
        "add_on", "addon", "add-on" -> "raw_material"
        else -> normalizedType
    }

    if (normalizedMode == "bom") {
        return null
    }

    val isRawMaterialType = normalizedItemType in setOf(
        "raw_material",
        "raw",
        "material",
        "ingredient",
        "bahan_baku",
    )
    val isAddOnMode = normalizedMode in setOf("add_on", "addon", "add-on")
    val isValidRecipeMaterial = isRawMaterialType || isAddOnMode
    if (!isValidRecipeMaterial) {
        return null
    }

    return RecipeMaterialCandidateUi(
        localId = localId,
        name = name,
        categoryName = categoryName,
        itemType = if (isRawMaterialType || isAddOnMode) "raw_material" else normalizedItemType,
        productMode = normalizedMode,
        unitCost = price,
    )
}

@Composable
private fun ShiftCashInputDialog(
    title: String,
    label: String,
    placeholder: String,
    confirmLabel: String,
    value: String,
    onValueChange: (String) -> Unit,
    onDismiss: () -> Unit,
    onConfirm: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(text = title) },
        text = {
            OutlinedTextField(
                value = value,
                onValueChange = onValueChange,
                label = { Text(label) },
                placeholder = { Text(placeholder) },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            )
        },
        confirmButton = {
            TextButton(onClick = onConfirm) {
                Text(confirmLabel)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Batal")
            }
        },
    )
}

private fun mergeHistoryReceipts(
    sessionReceipts: List<SaleReceiptUi>,
    recentSales: List<SaleEntity>,
    refundedSaleIds: Set<String>,
): List<SaleReceiptUi> {
    val fromSales = recentSales.map { sale ->
        val statusLabel = if (refundedSaleIds.contains(sale.localId)) {
            "VOID_REQUESTED"
        } else {
            sale.syncStatus.name
        }

        SaleReceiptUi(
            saleLocalId = sale.localId,
            receiptNo = sale.receiptNo,
            customerName = "Walk-in",
            items = emptyList(),
            totalAmount = sale.totalAmount,
            paymentMethodLabel = "Tersimpan Lokal",
            syncStatusLabel = statusLabel,
        )
    }

    return (fromSales + sessionReceipts)
        .distinctBy { it.receiptNo }
        .sortedByDescending { it.saleLocalId }
}

private fun formatDate(timestamp: Long): String {
    val formatter = java.text.SimpleDateFormat("dd MMM yyyy", java.util.Locale("id", "ID"))
    return formatter.format(java.util.Date(timestamp))
}

private fun formatTime(timestamp: Long): String {
    val formatter = java.text.SimpleDateFormat("HH:mm", java.util.Locale("id", "ID"))
    return formatter.format(java.util.Date(timestamp))
}

private fun startOfDayMillis(timestamp: Long): Long {
    val calendar = java.util.Calendar.getInstance().apply {
        timeInMillis = timestamp
        set(java.util.Calendar.HOUR_OF_DAY, 0)
        set(java.util.Calendar.MINUTE, 0)
        set(java.util.Calendar.SECOND, 0)
        set(java.util.Calendar.MILLISECOND, 0)
    }
    return calendar.timeInMillis
}

private fun formatShiftRange(openedAt: Long, closedAt: Long?): String {
    val formatter = java.text.SimpleDateFormat("HH:mm", java.util.Locale("id", "ID"))
    val start = formatter.format(java.util.Date(openedAt))
    val end = closedAt?.let { formatter.format(java.util.Date(it)) } ?: "--:--"
    return "$start - $end"
}

private suspend fun seedDemoDataIfNeeded(
    productDao: ProductDao,
    customerDao: CustomerDao,
    supplierDao: SupplierDao,
    purchaseDao: PurchaseDao,
) {
    if (
        productDao.countProducts() > 0 &&
        customerDao.countCustomers() > 0 &&
        supplierDao.countSuppliers() > 0 &&
        purchaseDao.countPurchases() > 0
    ) {
        return
    }

    val now = System.currentTimeMillis()

    if (productDao.countProducts() == 0L) {
        productDao.upsertAll(
            listOf(
                ProductEntity(
                    localId = "prod-kopi-gula-aren",
                    serverId = null,
                    name = "Kopi Gula Aren",
                    categoryName = "Minuman",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000001",
                    price = 18000,
                    stockOnHand = 120.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-es-kopi-susu",
                    serverId = null,
                    name = "Es Kopi Susu",
                    categoryName = "Minuman",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000004",
                    price = 20000,
                    stockOnHand = 95.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-teh-leci",
                    serverId = null,
                    name = "Teh Leci",
                    categoryName = "Minuman",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000005",
                    price = 14000,
                    stockOnHand = 80.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-roti-bakar-coklat",
                    serverId = null,
                    name = "Roti Bakar Coklat",
                    categoryName = "Makanan",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000002",
                    price = 15000,
                    stockOnHand = 60.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-mie-goreng-special",
                    serverId = null,
                    name = "Mie Goreng Special",
                    categoryName = "Makanan",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000003",
                    price = 22000,
                    stockOnHand = 75.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-nasi-ayam-geprek",
                    serverId = null,
                    name = "Nasi Ayam Geprek",
                    categoryName = "Makanan",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000006",
                    price = 25000,
                    stockOnHand = 70.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-keripik-singkong",
                    serverId = null,
                    name = "Keripik Singkong",
                    categoryName = "Snack",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000007",
                    price = 8000,
                    stockOnHand = 150.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                ProductEntity(
                    localId = "prod-kacang-pedas",
                    serverId = null,
                    name = "Kacang Pedas",
                    categoryName = "Snack",
                    itemType = "finished_good",
                    productMode = "standard",
                    barcode = "899000008",
                    price = 9000,
                    stockOnHand = 130.0,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
            )
        )
    }

    if (customerDao.countCustomers() == 0L) {
        customerDao.upsertAll(
            listOf(
                CustomerEntity(
                    localId = "cust-umum",
                    serverId = null,
                    name = "Pelanggan Umum",
                    phone = null,
                    address = null,
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                CustomerEntity(
                    localId = "cust-anggota-rina",
                    serverId = null,
                    name = "Rina Anggota",
                    phone = "081234560001",
                    address = "Perum Griya Asri Blok A2",
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                CustomerEntity(
                    localId = "cust-kantor-sejahtera",
                    serverId = null,
                    name = "PT Sejahtera Abadi",
                    phone = "0215550101",
                    address = "Jl. Merdeka No. 12, Jakarta",
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
            )
        )
    }

    if (supplierDao.countSuppliers() == 0L) {
        supplierDao.upsertAll(
            listOf(
                SupplierEntity(
                    localId = "sup-kopi-nusantara",
                    serverId = null,
                    name = "CV Kopi Nusantara",
                    phone = "08135550011",
                    address = "Jl. Industri Kopi No. 88",
                    notes = "Supplier biji kopi premium",
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
                SupplierEntity(
                    localId = "sup-sembako-maju",
                    serverId = null,
                    name = "PT Sembako Maju",
                    phone = "02170002002",
                    address = "Jl. Gudang Raya Blok C3",
                    notes = "Bahan baku umum dan packaging",
                    syncStatus = SyncStatus.PENDING_SYNC,
                    version = 1,
                    updatedAtLocal = now,
                    updatedAtServer = null,
                ),
            )
        )
    }

    if (purchaseDao.countPurchases() == 0L) {
        purchaseDao.insertFinalizedPurchase(
            purchase = PurchaseEntity(
                localId = "pur-demo-0001",
                serverId = null,
                purchaseNo = "PO-20260317-0001",
                supplierLocalId = "sup-kopi-nusantara",
                subtotal = 1_050_000,
                discountAmount = 50_000,
                totalAmount = 1_000_000,
                status = "received",
                purchasedAt = now,
                dueDate = null,
                syncStatus = SyncStatus.PENDING_SYNC,
                version = 1,
                updatedAtLocal = now,
                updatedAtServer = null,
            ),
            items = listOf(
                PurchaseItemEntity(
                    localId = "pur-item-0001",
                    purchaseLocalId = "pur-demo-0001",
                    productLocalId = "prod-kopi-gula-aren",
                    qty = 25.0,
                    unitCost = 20_000,
                    lineTotal = 500_000,
                ),
                PurchaseItemEntity(
                    localId = "pur-item-0002",
                    purchaseLocalId = "pur-demo-0001",
                    productLocalId = "prod-es-kopi-susu",
                    qty = 20.0,
                    unitCost = 27_500,
                    lineTotal = 550_000,
                ),
            ),
        )
    }
}

