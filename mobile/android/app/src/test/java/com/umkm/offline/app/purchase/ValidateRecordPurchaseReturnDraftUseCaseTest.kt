package com.umkm.offline.app.purchase

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ValidateRecordPurchaseReturnDraftUseCaseTest {

    private val useCase = ValidateRecordPurchaseReturnDraftUseCase()

    @Test
    fun invoke_rejectsSupplierMismatch() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.RECEIVED,
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-b",
            reason = "Barang rusak",
            returnLines = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    localRemainingQty = 2.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = false,
        )

        assertFalse(result.canSubmit)
        assertEquals(
            "Supplier return harus sama dengan supplier pada purchase asal.",
            result.fieldErrors["supplier_match"],
        )
    }

    @Test
    fun invoke_rejectsInvalidItemFields() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.RECEIVED,
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-a",
            reason = "Barang rusak",
            returnLines = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "",
                    qty = 0.0,
                    unitCost = -1,
                    localRemainingQty = 0.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = false,
        )

        assertFalse(result.canSubmit)
        assertTrue(result.fieldErrors.containsKey("item_0.product_id"))
        assertTrue(result.fieldErrors.containsKey("item_0.qty"))
        assertTrue(result.fieldErrors.containsKey("item_0.price"))
    }

    @Test
    fun invoke_rejectsWhenWriteLocked() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.RECEIVED,
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-a",
            reason = "Barang rusak",
            returnLines = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = true,
        )

        assertFalse(result.canSubmit)
        assertFalse(result.actionEnabled)
        assertEquals("Periode terkunci. Aksi retur tidak dapat diproses.", result.actionError)
    }

    @Test
    fun invoke_acceptsValidReturnDraft() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.RECEIVED,
            supplierLocalId = "sup-a",
            purchaseSupplierLocalId = "sup-a",
            reason = "Barang rusak",
            returnLines = listOf(
                PurchaseReturnLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    unitCost = 10_000,
                    localRemainingQty = 2.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = false,
        )

        assertTrue(result.canSubmit)
        assertTrue(result.actionEnabled)
        assertTrue(result.fieldErrors.isEmpty())
        assertEquals(1, result.effectiveItems.size)
    }
}
