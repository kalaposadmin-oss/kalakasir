package com.umkm.offline.app.purchase

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ValidateFinalizePurchaseDraftUseCaseTest {

    private val useCase = ValidateFinalizePurchaseDraftUseCase()

    @Test
    fun invoke_rejectsUnsupportedPaymentMethodForCreate() {
        val result = useCase(
            supplierLocalId = "sup-1",
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            paymentMethod = "transfer",
            dueDate = null,
        )

        assertFalse(result.canSubmit)
        assertEquals("Metode pembayaran hanya boleh Tunai atau Tempo.", result.fieldErrors["payment_method"])
    }

    @Test
    fun invoke_requiresDueDateWhenPaymentMethodTempo() {
        val result = useCase(
            supplierLocalId = "sup-1",
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 1.0, unitCost = 10_000)),
            paymentMethod = PurchaseContract.PaymentMethod.TEMPO,
            dueDate = null,
        )

        assertFalse(result.canSubmit)
        assertEquals("Jatuh tempo wajib diisi untuk pembayaran tempo.", result.fieldErrors["due_date"])
    }

    @Test
    fun invoke_rejectsMissingSupplierAndItems() {
        val result = useCase(
            supplierLocalId = " ",
            items = emptyList(),
            paymentMethod = PurchaseContract.PaymentMethod.CASH,
            dueDate = null,
        )

        assertFalse(result.canSubmit)
        assertTrue(result.fieldErrors.containsKey("supplier"))
        assertTrue(result.fieldErrors.containsKey("items"))
    }

    @Test
    fun invoke_acceptsValidCashDraft() {
        val result = useCase(
            supplierLocalId = "sup-1",
            items = listOf(PurchaseItemDraft(productLocalId = "prod-1", qty = 2.0, unitCost = 5_000)),
            paymentMethod = PurchaseContract.PaymentMethod.CASH,
            dueDate = null,
        )

        assertTrue(result.canSubmit)
        assertTrue(result.fieldErrors.isEmpty())
        assertEquals(null, result.formError)
    }
}
