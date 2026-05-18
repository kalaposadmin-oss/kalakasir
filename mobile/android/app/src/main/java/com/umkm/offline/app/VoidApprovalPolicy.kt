package com.umkm.offline.app

private const val VOID_TIER_ONE_LIMIT = 250_000L
private const val VOID_TIER_TWO_LIMIT = 1_500_000L

internal enum class VoidAuthorizationReasonCode {
    ALLOWED_DIRECT,
    ALLOWED_WITH_MANAGER_APPROVAL,
    DENIED_ROLE_NOT_ALLOWED,
    DENIED_INVALID_AMOUNT,
    DENIED_INVALID_RECEIPT,
    DENIED_ALREADY_REQUESTED,
}

internal enum class VoidReceiptStatus {
    NONE,
    REQUESTED,
    COMPLETED,
}

internal data class VoidAuthorizationDecision(
    val reasonCode: VoidAuthorizationReasonCode,
    val directAllowed: Boolean,
    val approvalRequired: Boolean,
    val allowedApproverRoles: List<String>,
    val requiredApproverLevel: String,
)

internal data class VoidRequestValidationResult(
    val canEnqueue: Boolean,
    val reasonCode: VoidAuthorizationReasonCode,
    val approvalRequired: Boolean,
    val requiredApproverLevel: String,
    val allowedApproverRoles: List<String>,
    val userMessage: String,
    val nextStatusLabel: String? = null,
)

internal fun resolveVoidReceiptStatus(syncStatusLabel: String): VoidReceiptStatus {
    val normalized = syncStatusLabel.trim().lowercase()
    return when {
        normalized.contains("void_requested") -> VoidReceiptStatus.REQUESTED
        normalized.contains("pending_refund") -> VoidReceiptStatus.REQUESTED
        normalized.contains("refund") -> VoidReceiptStatus.COMPLETED
        normalized.contains("voided") -> VoidReceiptStatus.COMPLETED
        else -> VoidReceiptStatus.NONE
    }
}

internal fun evaluateVoidAuthorization(totalAmount: Long, actorRoleRaw: String): VoidAuthorizationDecision {
    if (totalAmount <= 0L) {
        return VoidAuthorizationDecision(
            reasonCode = VoidAuthorizationReasonCode.DENIED_INVALID_AMOUNT,
            directAllowed = false,
            approvalRequired = false,
            allowedApproverRoles = emptyList(),
            requiredApproverLevel = "invalid",
        )
    }

    val actorRole = normalizeVoidActorRole(actorRoleRaw)

    if (totalAmount <= VOID_TIER_ONE_LIMIT) {
        val directAllowed = actorRole in setOf("cashier", "supervisor", "admin", "owner")
        return if (directAllowed) {
            VoidAuthorizationDecision(
                reasonCode = VoidAuthorizationReasonCode.ALLOWED_DIRECT,
                directAllowed = true,
                approvalRequired = false,
                allowedApproverRoles = listOf("admin", "owner"),
                requiredApproverLevel = "cashier+",
            )
        } else {
            VoidAuthorizationDecision(
                reasonCode = VoidAuthorizationReasonCode.DENIED_ROLE_NOT_ALLOWED,
                directAllowed = false,
                approvalRequired = false,
                allowedApproverRoles = listOf("admin", "owner"),
                requiredApproverLevel = "cashier+",
            )
        }
    }

    if (totalAmount <= VOID_TIER_TWO_LIMIT) {
        return if (actorRole in setOf("admin", "owner")) {
            VoidAuthorizationDecision(
                reasonCode = VoidAuthorizationReasonCode.ALLOWED_DIRECT,
                directAllowed = true,
                approvalRequired = false,
                allowedApproverRoles = listOf("admin", "owner"),
                requiredApproverLevel = "admin",
            )
        } else {
            VoidAuthorizationDecision(
                reasonCode = VoidAuthorizationReasonCode.ALLOWED_WITH_MANAGER_APPROVAL,
                directAllowed = false,
                approvalRequired = true,
                allowedApproverRoles = listOf("admin", "owner"),
                requiredApproverLevel = "admin",
            )
        }
    }

    return if (actorRole == "owner") {
        VoidAuthorizationDecision(
            reasonCode = VoidAuthorizationReasonCode.ALLOWED_DIRECT,
            directAllowed = true,
            approvalRequired = false,
            allowedApproverRoles = listOf("owner"),
            requiredApproverLevel = "owner",
        )
    } else {
        VoidAuthorizationDecision(
            reasonCode = VoidAuthorizationReasonCode.ALLOWED_WITH_MANAGER_APPROVAL,
            directAllowed = false,
            approvalRequired = true,
            allowedApproverRoles = listOf("owner"),
            requiredApproverLevel = "owner",
        )
    }
}

internal fun validateVoidRequest(
    saleLocalId: String,
    receiptNo: String,
    totalAmount: Long?,
    subtotalAmount: Long? = null,
    discountAmount: Long? = null,
    paidTotal: Long? = null,
    actorRoleRaw: String,
    currentSyncStatusLabel: String,
): VoidRequestValidationResult {
    if (saleLocalId.isBlank() || receiptNo.isBlank()) {
        return VoidRequestValidationResult(
            canEnqueue = false,
            reasonCode = VoidAuthorizationReasonCode.DENIED_INVALID_RECEIPT,
            approvalRequired = false,
            requiredApproverLevel = "invalid",
            allowedApproverRoles = emptyList(),
            userMessage = "Data transaksi tidak valid untuk proses void.",
        )
    }

    if (resolveVoidReceiptStatus(currentSyncStatusLabel) != VoidReceiptStatus.NONE) {
        return VoidRequestValidationResult(
            canEnqueue = false,
            reasonCode = VoidAuthorizationReasonCode.DENIED_ALREADY_REQUESTED,
            approvalRequired = false,
            requiredApproverLevel = "n/a",
            allowedApproverRoles = emptyList(),
            userMessage = "Void untuk transaksi ini sudah diminta atau sudah selesai.",
        )
    }

    val resolvedTotalAmount = resolveVoidNominalAmount(
        totalAmount = totalAmount,
        subtotalAmount = subtotalAmount,
        discountAmount = discountAmount,
        paidTotal = paidTotal,
    )

    if (resolvedTotalAmount == null) {
        return VoidRequestValidationResult(
            canEnqueue = false,
            reasonCode = VoidAuthorizationReasonCode.DENIED_INVALID_AMOUNT,
            approvalRequired = false,
            requiredApproverLevel = "invalid",
            allowedApproverRoles = emptyList(),
            userMessage = "Permintaan void tidak valid: nominal transaksi tidak valid.",
        )
    }

    val decision = evaluateVoidAuthorization(totalAmount = resolvedTotalAmount, actorRoleRaw = actorRoleRaw)

    return when (decision.reasonCode) {
        VoidAuthorizationReasonCode.ALLOWED_DIRECT,
        VoidAuthorizationReasonCode.ALLOWED_WITH_MANAGER_APPROVAL,
        -> VoidRequestValidationResult(
            canEnqueue = true,
            reasonCode = decision.reasonCode,
            approvalRequired = decision.approvalRequired,
            requiredApproverLevel = decision.requiredApproverLevel,
            allowedApproverRoles = decision.allowedApproverRoles,
            userMessage = if (decision.approvalRequired) {
                "Void diminta. Approval ${decision.requiredApproverLevel} dibutuhkan sebelum finalisasi."
            } else {
                "Void diminta dan menunggu sinkronisasi."
            },
            nextStatusLabel = "VOID_REQUESTED",
        )

        VoidAuthorizationReasonCode.DENIED_ROLE_NOT_ALLOWED -> VoidRequestValidationResult(
            canEnqueue = false,
            reasonCode = decision.reasonCode,
            approvalRequired = false,
            requiredApproverLevel = decision.requiredApproverLevel,
            allowedApproverRoles = decision.allowedApproverRoles,
            userMessage = "Role saat ini tidak diizinkan melakukan void untuk nominal transaksi ini.",
        )

        VoidAuthorizationReasonCode.DENIED_INVALID_AMOUNT,
        VoidAuthorizationReasonCode.DENIED_INVALID_RECEIPT,
        VoidAuthorizationReasonCode.DENIED_ALREADY_REQUESTED,
        -> VoidRequestValidationResult(
            canEnqueue = false,
            reasonCode = decision.reasonCode,
            approvalRequired = false,
            requiredApproverLevel = decision.requiredApproverLevel,
            allowedApproverRoles = decision.allowedApproverRoles,
            userMessage = when (decision.reasonCode) {
                VoidAuthorizationReasonCode.DENIED_INVALID_AMOUNT ->
                    "Permintaan void tidak valid: nominal transaksi tidak valid."
                VoidAuthorizationReasonCode.DENIED_INVALID_RECEIPT ->
                    "Permintaan void tidak valid: data transaksi tidak ditemukan."
                VoidAuthorizationReasonCode.DENIED_ALREADY_REQUESTED ->
                    "Permintaan void tidak valid: transaksi sudah diproses untuk void/refund."
                else -> "Permintaan void tidak valid."
            },
        )
    }
}

internal fun resolveVoidNominalAmount(
    totalAmount: Long?,
    subtotalAmount: Long?,
    discountAmount: Long?,
    paidTotal: Long?,
): Long? {
    val subtotalAfterDiscount = subtotalAmount
        ?.let { subtotal -> subtotal - (discountAmount ?: 0L) }
        ?.takeIf { it > 0L }

    return totalAmount
        ?.takeIf { it > 0L }
        ?: subtotalAfterDiscount
        ?: paidTotal?.takeIf { it > 0L }
}

internal fun buildVoidSaleOutboxPayload(
    saleLocalId: String,
    receiptNo: String,
    requestedByRole: String,
    validation: VoidRequestValidationResult,
    fifoReversalAllocationsJson: String,
): String {
    val allowedRoles = validation.allowedApproverRoles.joinToString(",")
    val reversalJson = fifoReversalAllocationsJson.ifBlank { "[]" }

    return "{\"operation\":\"void_sale\",\"sale_local_id\":\"${escapeJson(receiptNo = saleLocalId)}\",\"receipt_no\":\"${escapeJson(receiptNo)}\",\"requested_by_role\":\"${escapeJson(requestedByRole)}\",\"approval_required\":${validation.approvalRequired},\"required_approver_level\":\"${escapeJson(validation.requiredApproverLevel)}\",\"allowed_approver_roles\":\"${escapeJson(allowedRoles)}\",\"fifo_reversal_allocations\":$reversalJson}"
}

private fun normalizeVoidActorRole(rawRole: String): String {
    return when (rawRole.trim().lowercase()) {
        "kasir", "cashier" -> "cashier"
        "supervisor", "spv" -> "supervisor"
        "admin", "administrator" -> "admin"
        "owner", "pemilik" -> "owner"
        else -> rawRole.trim().lowercase()
    }
}

private fun escapeJson(receiptNo: String): String {
    return receiptNo.replace("\\", "\\\\").replace("\"", "\\\"")
}
