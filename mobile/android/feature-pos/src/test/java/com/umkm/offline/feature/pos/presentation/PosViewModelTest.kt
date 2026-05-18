package com.umkm.offline.feature.pos.presentation

import com.umkm.offline.feature.pos.domain.CheckoutItem
import com.umkm.offline.feature.pos.domain.FinalizeSaleUseCase
import com.umkm.offline.feature.pos.domain.PosCustomer
import com.umkm.offline.feature.pos.domain.PosProduct
import com.umkm.offline.feature.pos.domain.PosRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PosViewModelTest {

    @Before
    fun setup() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun finalizeSaleOffline_rejectsUnderpaidAmount() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(
            actorUserLocalId = "cashier-1",
            actorBranchLocalId = "branch-a",
            actorRegisterLocalId = "register-a",
        )
        viewModel.setActiveShiftContext(
            shiftLocalId = "shift-1",
            shiftStatus = "OPEN",
            shiftOwnerUserLocalId = "cashier-1",
            shiftBranchLocalId = "branch-a",
            shiftRegisterLocalId = "register-a",
        )
        viewModel.addToCart("prd-1")
        advanceUntilIdle()

        // Total cart = 10.000, but paid only 5.000
        viewModel.finalizeSaleOffline(paidAmount = 5_000)
        advanceUntilIdle()

        assertEquals("Nominal bayar kurang dari total tagihan.", viewModel.uiState.value.errorMessage)
        assertEquals(0, repository.finalizeCalls)
        assertNull(viewModel.uiState.value.lastReceipt)
    }

    @Test
    fun finalizeSaleOffline_setsPaidAndChangeInReceipt() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(
            actorUserLocalId = "cashier-1",
            actorBranchLocalId = "branch-a",
            actorRegisterLocalId = "register-a",
        )
        viewModel.setActiveShiftContext(
            shiftLocalId = "shift-1",
            shiftStatus = "OPEN",
            shiftOwnerUserLocalId = "cashier-1",
            shiftBranchLocalId = "branch-a",
            shiftRegisterLocalId = "register-a",
        )
        viewModel.addToCart("prd-1")
        viewModel.selectPaymentMethod("transfer")
        advanceUntilIdle()

        // Total cart = 10.000, paid 20.000
        viewModel.finalizeSaleOffline(paidAmount = 20_000)
        advanceUntilIdle()

        val receipt = viewModel.uiState.value.lastReceipt
        assertNotNull(receipt)
        assertEquals(1, repository.finalizeCalls)
        assertEquals(10_000L, receipt?.totalAmount)
        assertEquals(20_000L, receipt?.paidAmount)
        assertEquals(10_000L, receipt?.changeAmount)
        assertEquals("Transfer Bank", receipt?.paymentMethodLabel)
        assertEquals(0, viewModel.uiState.value.cartItems.size)
    }

    @Test
    fun finalizeSaleOffline_rejectsWhenShiftNotOpened() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(actorUserLocalId = "cashier-1")
        viewModel.addToCart("prd-1")
        advanceUntilIdle()

        viewModel.finalizeSaleOffline(paidAmount = 10_000)
        advanceUntilIdle()

        assertEquals("Shift belum aktif. Buka shift dulu sebelum checkout.", viewModel.uiState.value.errorMessage)
        assertEquals(0, repository.finalizeCalls)
    }

    @Test
    fun finalizeSaleOffline_rejectsWhenShiftOwnerDoesNotMatchActor() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(actorUserLocalId = "cashier-1")
        viewModel.setActiveShiftContext(
            shiftLocalId = "shift-owned-by-other-actor",
            shiftStatus = "OPEN",
            shiftOwnerUserLocalId = "cashier-2",
        )
        viewModel.addToCart("prd-1")
        advanceUntilIdle()

        viewModel.finalizeSaleOffline(paidAmount = 10_000)
        advanceUntilIdle()

        assertEquals(0, repository.finalizeCalls)
        assertEquals("Akun kasir tidak sesuai dengan pemilik shift aktif.", viewModel.uiState.value.errorMessage)
        assertNull(viewModel.uiState.value.lastReceipt)
    }

    @Test
    fun finalizeSaleOffline_rejectsWhenShiftBranchDoesNotMatchActorBranch() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(
            actorUserLocalId = "cashier-1",
            actorBranchLocalId = "branch-a",
        )
        viewModel.setActiveShiftContext(
            shiftLocalId = "shift-1",
            shiftStatus = "OPEN",
            shiftOwnerUserLocalId = "cashier-1",
            shiftBranchLocalId = "branch-b",
        )
        viewModel.addToCart("prd-1")
        advanceUntilIdle()

        viewModel.finalizeSaleOffline(paidAmount = 10_000)
        advanceUntilIdle()

        assertEquals(0, repository.finalizeCalls)
        assertEquals("Cabang kasir tidak sesuai dengan shift aktif.", viewModel.uiState.value.errorMessage)
        assertNull(viewModel.uiState.value.lastReceipt)
    }

    @Test
    fun finalizeSaleOffline_rejectsWhenShiftRegisterDoesNotMatchActorRegister() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(
            actorUserLocalId = "cashier-1",
            actorRegisterLocalId = "register-a",
        )
        viewModel.setActiveShiftContext(
            shiftLocalId = "shift-1",
            shiftStatus = "OPEN",
            shiftOwnerUserLocalId = "cashier-1",
            shiftRegisterLocalId = "register-b",
        )
        viewModel.addToCart("prd-1")
        advanceUntilIdle()

        viewModel.finalizeSaleOffline(paidAmount = 10_000)
        advanceUntilIdle()

        assertEquals(0, repository.finalizeCalls)
        assertEquals("Register kasir tidak sesuai dengan shift aktif.", viewModel.uiState.value.errorMessage)
        assertNull(viewModel.uiState.value.lastReceipt)
    }

    @Test
    fun finalizeSaleOffline_rejectsWhenCheckoutContextIsMissing() = runTest {
        val repository = FakePosRepository()
        val viewModel = PosViewModel(repository, FinalizeSaleUseCase(repository))

        advanceUntilIdle()
        viewModel.setActorContext(actorUserLocalId = "cashier-1")
        viewModel.setActiveShiftContext(
            shiftLocalId = "shift-1",
            shiftStatus = "OPEN",
            shiftOwnerUserLocalId = "cashier-1",
        )
        viewModel.addToCart("prd-1")
        advanceUntilIdle()

        viewModel.finalizeSaleOffline(paidAmount = 10_000)
        advanceUntilIdle()

        assertEquals(0, repository.finalizeCalls)
        assertEquals("Konteks kasir/shift belum lengkap.", viewModel.uiState.value.errorMessage)
        assertNull(viewModel.uiState.value.lastReceipt)
    }

    private class FakePosRepository : PosRepository {
        private val products = MutableStateFlow(
            listOf(
                PosProduct(
                    localId = "prd-1",
                    name = "Produk Test",
                    categoryName = "Demo",
                    price = 10_000,
                ),
            ),
        )
        private val customers = MutableStateFlow(
            listOf(
                PosCustomer(
                    localId = "cust-1",
                    name = "Pelanggan Test",
                ),
            ),
        )

        var finalizeCalls: Int = 0

        override fun observeProducts(keyword: String): Flow<List<PosProduct>> {
            return if (keyword.isBlank()) {
                products
            } else {
                products.map { list ->
                    list.filter { it.name.contains(keyword, ignoreCase = true) }
                }
            }
        }

        override fun observeCustomers(): Flow<List<PosCustomer>> = customers

        override suspend fun finalizeSaleOffline(
            receiptNo: String,
            customerLocalId: String?,
            items: List<CheckoutItem>,
            discountAmount: Long,
            paymentMethod: String,
            paidAmount: Long,
            shiftLocalId: String?,
        ): String {
            finalizeCalls += 1
            return "sale-local-1"
        }
    }
}
