package com.umkm.offline.app.purchase

import java.util.Locale

object PurchaseContract {
    object PaymentMethod {
        const val CASH = "cash"
        const val TEMPO = "tempo"
        const val UNPAID = "unpaid"

        val CREATE_ALLOWED: Set<String> = setOf(CASH, TEMPO)

        fun normalize(raw: String?): String {
            val normalized = raw?.trim()?.lowercase(Locale.ROOT).orEmpty()
            return when (normalized) {
                CASH, TEMPO, UNPAID -> normalized
                else -> CASH
            }
        }

        fun normalizeForCreate(raw: String?): String {
            val normalized = normalize(raw)
            return if (normalized in CREATE_ALLOWED) normalized else CASH
        }

        fun isAllowedForCreate(raw: String?): Boolean {
            val normalized = raw?.trim()?.lowercase(Locale.ROOT).orEmpty()
            return normalized in CREATE_ALLOWED
        }
    }

    object PaymentStatus {
        const val PAID = "paid"
        const val UNPAID = "unpaid"
        const val PARTIAL = "partial"

        fun normalize(raw: String?): String {
            val normalized = raw?.trim()?.lowercase(Locale.ROOT).orEmpty()
            return when (normalized) {
                PAID, UNPAID, PARTIAL -> normalized
                else -> PAID
            }
        }
    }

    object Status {
        const val APPROVED = "approved"
        const val PARTIALLY_RECEIVED = "partially_received"
        const val RECEIVED = "received"
        const val RECEIVED_UNPAID = "received_unpaid"
        const val RECEIVED_PARTIAL_PAID = "received_partial_paid"
        const val RETURNED_PARTIAL = "returned_partial"
        const val RETURNED_FULL = "returned_full"

        val RECEIVE_ALLOWED: Set<String> = setOf(APPROVED, PARTIALLY_RECEIVED)

        fun normalize(raw: String?): String {
            return raw?.trim()?.lowercase(Locale.ROOT).orEmpty()
        }

        fun isReceivedFamily(raw: String?): Boolean {
            return normalize(raw).startsWith(RECEIVED)
        }

        fun isReturnFamily(raw: String?): Boolean {
            return when (normalize(raw)) {
                RETURNED_PARTIAL, RETURNED_FULL -> true
                else -> false
            }
        }

        fun allowsReturn(raw: String?): Boolean {
            return isReceivedFamily(raw) || isReturnFamily(raw)
        }

        fun allowsReceive(raw: String?): Boolean {
            return normalize(raw) in RECEIVE_ALLOWED
        }
    }

    fun resolveEffectivePaymentStatus(
        paymentMethod: String,
        paymentStatus: String,
    ): String {
        return when {
            paymentMethod == PaymentMethod.UNPAID -> PaymentStatus.UNPAID
            paymentMethod == PaymentMethod.TEMPO && paymentStatus == PaymentStatus.PAID -> PaymentStatus.UNPAID
            else -> paymentStatus
        }
    }

    fun resolvePersistedPurchaseStatus(
        paymentMethod: String,
        effectivePaymentStatus: String,
    ): String {
        return when {
            effectivePaymentStatus == PaymentStatus.PAID -> Status.RECEIVED
            paymentMethod == PaymentMethod.TEMPO -> Status.RECEIVED_UNPAID
            effectivePaymentStatus == PaymentStatus.PARTIAL -> Status.RECEIVED_PARTIAL_PAID
            else -> Status.RECEIVED_UNPAID
        }
    }
}