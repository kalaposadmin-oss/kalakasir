package com.umkm.offline.feature.pos.usecase

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class ValidateCheckoutContextUseCaseTest {

    private val useCase = ValidateCheckoutContextUseCase()

    @Test
    fun rejectsWhenItemsAreEmpty() {
        val result = useCase(
            baseContext(
                items = emptyList(),
            ),
        )

        assertEquals(
            CheckoutValidationFailureReason.CHECKOUT_ITEMS_EMPTY,
            (result as CheckoutValidationResult.Rejected).reason,
        )
    }

    @Test
    fun rejectsWhenLineTotalDoesNotMatchQtyAndPrice() {
        val result = useCase(
            baseContext(
                items = listOf(
                    CheckoutValidationItem(
                        productLocalId = "prd-1",
                        qty = 1.0,
                        unitPrice = 10_000,
                        lineTotal = 9_000,
                    ),
                ),
                expectedSubtotal = 9_000,
                expectedTotal = 9_000,
            ),
        )

        assertEquals(
            CheckoutValidationFailureReason.CHECKOUT_TOTAL_MISMATCH,
            (result as CheckoutValidationResult.Rejected).reason,
        )
    }

    @Test
    fun rejectsWhenPaymentMethodIsBlank() {
        val result = useCase(
            baseContext(
                paymentMethod = " ",
            ),
        )

        assertEquals(
            CheckoutValidationFailureReason.CHECKOUT_PAYMENT_MISMATCH,
            (result as CheckoutValidationResult.Rejected).reason,
        )
    }

    @Test
    fun rejectsWhenPaidAmountIsLessThanTotal() {
        val result = useCase(
            baseContext(
                expectedTotal = 10_000,
                paidAmount = 9_000,
            ),
        )

        assertEquals(
            CheckoutValidationFailureReason.CHECKOUT_UNDERPAID,
            (result as CheckoutValidationResult.Rejected).reason,
        )
    }

    @Test
    fun rejectsWhenMandatoryContextMissing() {
        val result = useCase(
            baseContext(
                actorBranchLocalId = null,
            ),
        )

        assertEquals(
            CheckoutValidationFailureReason.CHECKOUT_MISSING_CONTEXT,
            (result as CheckoutValidationResult.Rejected).reason,
        )
    }

    @Test
    fun allowsWhenContextTotalsAndPaymentAreValid() {
        val result = useCase(baseContext())

        assertTrue(result is CheckoutValidationResult.Allowed)
    }

    private fun baseContext(
        items: List<CheckoutValidationItem> = listOf(
            CheckoutValidationItem(
                productLocalId = "prd-1",
                qty = 1.0,
                unitPrice = 10_000,
                lineTotal = 10_000,
            ),
        ),
        discountAmount: Long = 0,
        expectedSubtotal: Long = 10_000,
        expectedTotal: Long = 10_000,
        paidAmount: Long = 10_000,
        paymentMethod: String = "cash",
        shiftLocalId: String? = "shift-1",
        actorUserLocalId: String? = "cashier-1",
        actorBranchLocalId: String? = "branch-a",
        actorRegisterLocalId: String? = "register-a",
    ): CheckoutValidationContext {
        return CheckoutValidationContext(
            items = items,
            discountAmount = discountAmount,
            expectedSubtotal = expectedSubtotal,
            expectedTotal = expectedTotal,
            paidAmount = paidAmount,
            paymentMethod = paymentMethod,
            shiftLocalId = shiftLocalId,
            actorUserLocalId = actorUserLocalId,
            actorBranchLocalId = actorBranchLocalId,
            actorRegisterLocalId = actorRegisterLocalId,
        )
    }
}
