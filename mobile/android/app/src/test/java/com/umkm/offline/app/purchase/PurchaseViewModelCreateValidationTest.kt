package com.umkm.offline.app.purchase

import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.SupplierDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.SyncActionTypeCountRow
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.PurchaseReturnItemEntity
import com.umkm.offline.core.database.entity.SupplierEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TestWatcher
import org.junit.runner.Description

@OptIn(ExperimentalCoroutinesApi::class)
class PurchaseViewModelCreateValidationTest {

    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun evaluateFinalizePurchaseDraft_setsValidationErrorsForTempoWithoutDueDate() = runTest {
        val viewModel = buildViewModel()

        viewModel.evaluateFinalizePurchaseDraft(
            supplierLocalId = "sup-1",
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            paymentMethod = PurchaseContract.PaymentMethod.TEMPO,
            dueDate = null,
            transactionDate = null,
        )

        val state = viewModel.uiState.value
        assertFalse(state.createCanSubmit)
        assertEquals("Jatuh tempo wajib diisi untuk pembayaran tempo.", state.createFieldErrors["due_date"])
        assertEquals("Jatuh tempo wajib diisi untuk pembayaran tempo.", state.createFormError)
    }

    @Test
    fun finalizePurchase_blocksWhenCreateDraftInvalid() = runTest {
        val viewModel = buildViewModel()

        viewModel.finalizePurchase(
            purchaseNo = "PO-UI-001",
            supplierLocalId = "",
            items = emptyList(),
            discountAmount = 0,
            dueDate = null,
            paymentMethod = PurchaseContract.PaymentMethod.CASH,
        )
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertFalse(state.createCanSubmit)
        assertTrue(state.createFieldErrors.containsKey("supplier"))
        assertTrue(state.createFieldErrors.containsKey("items"))
        assertNull(state.lastPurchaseId)
        assertFalse(state.isSaving)
        assertEquals("Supplier wajib dipilih.", state.errorMessage)
    }

    @Test
    fun evaluateMarkPurchaseReceivedDraft_setsStatusErrorWhenStatusInvalid() = runTest {
        val viewModel = buildViewModel()

        viewModel.evaluateMarkPurchaseReceivedDraft(
            purchaseLocalId = "purchase-1",
            purchaseStatus = "open",
            receiveLines = listOf(
                PurchaseReceiveLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    remainingQty = 2.0,
                ),
            ),
            payloadTransactionDate = 1L,
            isWriteLocked = false,
        )

        val state = viewModel.uiState.value
        assertFalse(state.receiveCanSubmit)
        assertFalse(state.receiveActionEnabled)
        assertEquals(
            "Receive hanya dapat diproses dari status approved atau partially_received.",
            state.receiveFieldErrors["status"],
        )
    }

    @Test
    fun markPurchaseReceived_blocksWhenReceiveDraftInvalid() = runTest {
        val viewModel = buildViewModel()

        viewModel.markPurchaseReceived(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.APPROVED,
            receiveLines = listOf(
                PurchaseReceiveLineDraft(
                    productLocalId = "prod-1",
                    qty = 5.0,
                    remainingQty = 1.0,
                ),
            ),
            payloadTransactionDate = 1L,
            isWriteLocked = false,
        )
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertFalse(state.receiveCanSubmit)
        assertTrue(state.receiveFieldErrors.containsKey("item_0.qty"))
        assertFalse(state.isSaving)
        assertEquals("Qty receive pada baris 1 tidak boleh melebihi sisa qty.", state.errorMessage)
    }

    @Test
    fun evaluateRecordPurchaseReturnDraft_setsSupplierMismatchError() = runTest {
        val viewModel = buildViewModel()

        viewModel.evaluateRecordPurchaseReturnDraft(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.RECEIVED,
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-b",
            reason = "Barang rusak",
            items = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                ),
            ),
            payloadTransactionDate = 1L,
            isWriteLocked = false,
        )

        val state = viewModel.uiState.value
        assertFalse(state.returnCanSubmit)
        assertTrue(state.returnFieldErrors.containsKey("supplier_match"))
    }

    @Test
    fun evaluateRecordPurchaseReturnDraft_setsStatusErrorWhenStatusInvalid() = runTest {
        val viewModel = buildViewModel()

        viewModel.evaluateRecordPurchaseReturnDraft(
            purchaseLocalId = "purchase-1",
            purchaseStatus = "open",
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-a",
            reason = "Barang rusak",
            items = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                ),
            ),
            payloadTransactionDate = 1L,
            isWriteLocked = false,
        )

        val state = viewModel.uiState.value
        assertFalse(state.returnCanSubmit)
        assertFalse(state.returnActionEnabled)
        assertTrue(state.returnFieldErrors.containsKey("status"))
    }

    @Test
    fun recordPurchaseReturn_blocksWhenDraftInvalid() = runTest {
        val viewModel = buildViewModel()

        viewModel.recordPurchaseReturn(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.RECEIVED,
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-a",
            reason = "Barang rusak",
            items = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "prod-1",
                    qty = 5.0,
                    unitCost = 10_000,
                    localRemainingQty = 1.0,
                ),
            ),
            payloadTransactionDate = 1L,
            isWriteLocked = false,
        )
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertFalse(state.returnCanSubmit)
        assertTrue(state.returnFieldErrors.containsKey("item_0.qty"))
        assertFalse(state.isSaving)
        assertEquals("Qty retur pada baris 1 melebihi sisa qty lokal.", state.errorMessage)
    }

    private fun buildViewModel(): PurchaseViewModel {
        val repository = PurchaseRepository(
            supplierDao = InMemorySupplierDao(),
            purchaseDao = NoopPurchaseDao(),
            syncActionDao = NoopSyncActionDao(),
        )
        val validateUseCase = ValidateFinalizePurchaseDraftUseCase()
        val validateReceiveUseCase = ValidateMarkPurchaseReceivedDraftUseCase()
        val validateReturnUseCase = ValidateRecordPurchaseReturnDraftUseCase()
        return PurchaseViewModel(
            observeSuppliersUseCase = ObserveSuppliersUseCase(repository),
            createSupplierUseCase = CreateSupplierUseCase(repository),
            finalizePurchaseUseCase = FinalizePurchaseUseCase(repository, validateUseCase),
            validateFinalizePurchaseDraftUseCase = validateUseCase,
            validateMarkPurchaseReceivedDraftUseCase = validateReceiveUseCase,
            markPurchaseReceivedUseCase = MarkPurchaseReceivedUseCase(repository, validateReceiveUseCase),
            validateRecordPurchaseReturnDraftUseCase = validateReturnUseCase,
            recordPurchaseReturnUseCase = RecordPurchaseReturnUseCase(repository, validateReturnUseCase),
        )
    }
}

@OptIn(ExperimentalCoroutinesApi::class)
class MainDispatcherRule : TestWatcher() {
    private val dispatcher = StandardTestDispatcher()

    override fun starting(description: Description) {
        Dispatchers.setMain(dispatcher)
    }

    override fun finished(description: Description) {
        Dispatchers.resetMain()
    }
}

private class InMemorySupplierDao : SupplierDao {
    private val state = MutableStateFlow<List<SupplierEntity>>(emptyList())

    override fun observeSuppliers(): Flow<List<SupplierEntity>> = state

    override suspend fun upsert(supplier: SupplierEntity) {
        state.value = state.value.filterNot { it.localId == supplier.localId } + supplier
    }

    override suspend fun upsertAll(suppliers: List<SupplierEntity>) {
        suppliers.forEach { upsert(it) }
    }

    override suspend fun findByLocalId(localId: String): SupplierEntity? {
        return state.value.firstOrNull { it.localId == localId }
    }

    override suspend fun countSuppliers(): Long = state.value.size.toLong()
}

private class NoopPurchaseDao : PurchaseDao {
    override suspend fun upsertPurchase(purchase: PurchaseEntity) {
        throw AssertionError("NoopPurchaseDao.upsertPurchase should not be called in this test")
    }

    override suspend fun insertPurchaseItems(items: List<PurchaseItemEntity>) {
        throw AssertionError("NoopPurchaseDao.insertPurchaseItems should not be called in this test")
    }

    override suspend fun countPurchases(): Long = 0

    override suspend fun findPurchaseByLocalId(purchaseLocalId: String): PurchaseEntity? = null

    override fun observeRecentPurchases(limit: Int): Flow<List<PurchaseEntity>> = flowOf(emptyList())

    override fun observeItemsByPurchase(purchaseLocalId: String): Flow<List<PurchaseItemEntity>> = flowOf(emptyList())

    override suspend fun findPurchaseItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseItemEntity> = emptyList()

    override suspend fun insertPurchaseReturnItems(items: List<PurchaseReturnItemEntity>) {
        throw AssertionError("NoopPurchaseDao.insertPurchaseReturnItems should not be called in this test")
    }

    override suspend fun findPurchaseReturnItemsByPurchaseLocalId(purchaseLocalId: String): List<PurchaseReturnItemEntity> = emptyList()
}

private class NoopSyncActionDao : SyncActionDao {
    override suspend fun enqueue(action: SyncActionEntity) {
        throw AssertionError("NoopSyncActionDao.enqueue should not be called in this test")
    }

    override suspend fun findDueActions(now: Long, limit: Int): List<SyncActionEntity> = emptyList()

    override suspend fun claimForProcessing(actionId: String, lockedUntil: Long): Int = 0

    override suspend fun delete(actionId: String) {
        // No-op
    }

    override suspend fun markFailed(actionId: String, nextRetryTime: Long, errorMessage: String) {
        // No-op
    }

    override suspend fun countByIdempotencyKey(idempotencyKey: String): Int = 0

    override fun observePendingCount(): Flow<Int> = flowOf(0)

    override fun observeFailedCount(): Flow<Int> = flowOf(0)

    override fun observeActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> = flowOf(emptyList())

    override fun observeFailedActionTypeCounts(): Flow<List<SyncActionTypeCountRow>> = flowOf(emptyList())
}
