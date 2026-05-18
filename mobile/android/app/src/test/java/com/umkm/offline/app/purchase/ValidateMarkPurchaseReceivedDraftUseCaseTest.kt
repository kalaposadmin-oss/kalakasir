package com.umkm.offline.app.purchase

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ValidateMarkPurchaseReceivedDraftUseCaseTest {

    private val useCase = ValidateMarkPurchaseReceivedDraftUseCase()

    @Test
    fun invoke_rejectsWhenStatusNotAllowed() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = "open",
            receiveLines = listOf(
                PurchaseReceiveLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    remainingQty = 2.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = false,
        )

        assertFalse(result.canSubmit)
        assertFalse(result.actionEnabled)
        assertEquals(
            "Receive hanya dapat diproses dari status approved atau partially_received.",
            result.fieldErrors["status"],
        )
    }

    @Test
    fun invoke_rejectsWhenQtyExceedsRemaining() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.APPROVED,
            receiveLines = listOf(
                PurchaseReceiveLineDraft(
                    productLocalId = "prod-1",
                    qty = 10.0,
                    remainingQty = 3.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = false,
        )

        assertFalse(result.canSubmit)
        assertEquals("Qty receive pada baris 1 tidak boleh melebihi sisa qty.", result.fieldErrors["item_0.qty"])
    }

    @Test
    fun invoke_rejectsWhenWriteLocked() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.APPROVED,
            receiveLines = listOf(
                PurchaseReceiveLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    remainingQty = 2.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = true,
        )

        assertFalse(result.canSubmit)
        assertFalse(result.actionEnabled)
        assertEquals("Periode terkunci. Aksi receive tidak dapat diproses.", result.actionError)
    }

    @Test
    fun invoke_acceptsValidDraftAndBuildsEffectiveItems() {
        val result = useCase(
            purchaseLocalId = "purchase-1",
            purchaseStatus = PurchaseContract.Status.PARTIALLY_RECEIVED,
            receiveLines = listOf(
                PurchaseReceiveLineDraft(
                    productLocalId = "prod-1",
                    qty = 1.0,
                    remainingQty = 2.0,
                ),
            ),
            transactionDate = 1L,
            isWriteLocked = false,
        )

        assertTrue(result.canSubmit)
        assertTrue(result.actionEnabled)
        assertTrue(result.fieldErrors.isEmpty())
        assertEquals(1, result.effectiveItems.size)
        assertEquals("prod-1", result.effectiveItems.first().productLocalId)
    }
}
