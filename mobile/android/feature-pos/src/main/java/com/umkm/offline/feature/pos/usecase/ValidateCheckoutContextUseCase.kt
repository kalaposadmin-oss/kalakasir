package com.umkm.offline.feature.pos.usecase

enum class CheckoutValidationFailureReason {
    CHECKOUT_ITEMS_EMPTY,
    CHECKOUT_TOTAL_MISMATCH,
    CHECKOUT_PAYMENT_MISMATCH,
    CHECKOUT_UNDERPAID,
    CHECKOUT_MISSING_CONTEXT,
    CHECKOUT_INVALID_ITEM_QTY,
    CHECKOUT_INVALID_ITEM_PRICE,
}

data class CheckoutValidationItem(
    val productLocalId: String,
    val qty: Double,
    val unitPrice: Long,
    val lineTotal: Long,
)

data class CheckoutValidationContext(
    val items: List<CheckoutValidationItem>,
    val discountAmount: Long,
    val expectedSubtotal: Long,
    val expectedTotal: Long,
    val paidAmount: Long,
    val paymentMethod: String,
    val shiftLocalId: String?,
    val actorUserLocalId: String?,
    val actorBranchLocalId: String?,
    val actorRegisterLocalId: String?,
)

sealed class CheckoutValidationResult {
    data object Allowed : CheckoutValidationResult()

    data class Rejected(
        val reason: CheckoutValidationFailureReason,
    ) : CheckoutValidationResult()
}

class ValidateCheckoutContextUseCase {
    operator fun invoke(context: CheckoutValidationContext): CheckoutValidationResult {
        if (
            context.shiftLocalId.isNullOrBlank() ||
            context.actorUserLocalId.isNullOrBlank() ||
            context.actorBranchLocalId.isNullOrBlank() ||
            context.actorRegisterLocalId.isNullOrBlank()
        ) {
            return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_MISSING_CONTEXT)
        }

        if (context.items.isEmpty()) {
            return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_ITEMS_EMPTY)
        }

        var recomputedSubtotal = 0L
        for (item in context.items) {
            if (item.qty <= 0.0) {
                return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_INVALID_ITEM_QTY)
            }
            if (item.unitPrice <= 0L) {
                return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_INVALID_ITEM_PRICE)
            }
            val expectedLineTotal = (item.qty * item.unitPrice).toLong()
            if (item.lineTotal != expectedLineTotal) {
                return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_TOTAL_MISMATCH)
            }
            recomputedSubtotal += expectedLineTotal
        }

        if (context.expectedSubtotal != recomputedSubtotal) {
            return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_TOTAL_MISMATCH)
        }

        val normalizedDiscount = context.discountAmount.coerceAtLeast(0L).coerceAtMost(context.expectedSubtotal)
        val recomputedTotal = context.expectedSubtotal - normalizedDiscount
        if (context.expectedTotal != recomputedTotal) {
            return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_TOTAL_MISMATCH)
        }

        if (context.paymentMethod.isBlank() || context.paidAmount < 0) {
            return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_PAYMENT_MISMATCH)
        }

        if (context.paidAmount < context.expectedTotal) {
            return CheckoutValidationResult.Rejected(CheckoutValidationFailureReason.CHECKOUT_UNDERPAID)
        }

        return CheckoutValidationResult.Allowed
    }
}