package com.umkm.offline.app

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class VoidApprovalPolicyTest {

    @Test
    fun `PARITY-VOID-001 nominal kecil cashier direct allowed`() {
        val decision = evaluateVoidAuthorization(
            totalAmount = 200_000L,
            actorRoleRaw = "kasir",
        )

        assertEquals(VoidAuthorizationReasonCode.ALLOWED_DIRECT, decision.reasonCode)
        assertTrue(decision.directAllowed)
        assertFalse(decision.approvalRequired)
        assertEquals("cashier+", decision.requiredApproverLevel)
    }

    @Test
    fun `PARITY-VOID-002 nominal besar cashier requires manager approval`() {
        val validation = validateVoidRequest(
            saleLocalId = "sale-1",
            receiptNo = "TRX-001",
            totalAmount = 1_200_000L,
            actorRoleRaw = "kasir",
            currentSyncStatusLabel = "SYNCED",
        )

        assertTrue(validation.canEnqueue)
        assertEquals(VoidAuthorizationReasonCode.ALLOWED_WITH_MANAGER_APPROVAL, validation.reasonCode)
        assertTrue(validation.approvalRequired)
        assertEquals("admin", validation.requiredApproverLevel)
        assertEquals("VOID_REQUESTED", validation.nextStatusLabel)
    }

    @Test
    fun `PARITY-VOID-003 manager path keeps requested state not completed`() {
        val validation = validateVoidRequest(
            saleLocalId = "sale-2",
            receiptNo = "TRX-002",
            totalAmount = 1_600_000L,
            actorRoleRaw = "supervisor",
            currentSyncStatusLabel = "SYNCED",
        )

        assertTrue(validation.canEnqueue)
        assertTrue(validation.approvalRequired)
        assertEquals("owner", validation.requiredApproverLevel)
        assertEquals(VoidReceiptStatus.REQUESTED, resolveVoidReceiptStatus(validation.nextStatusLabel.orEmpty()))
    }

    @Test
    fun `denies duplicate void request when status already requested`() {
        val validation = validateVoidRequest(
            saleLocalId = "sale-3",
            receiptNo = "TRX-003",
            totalAmount = 150_000L,
            actorRoleRaw = "admin",
            currentSyncStatusLabel = "VOID_REQUESTED",
        )

        assertFalse(validation.canEnqueue)
        assertEquals(VoidAuthorizationReasonCode.DENIED_ALREADY_REQUESTED, validation.reasonCode)
    }

    @Test
    fun `denies invalid amount`() {
        val decision = evaluateVoidAuthorization(
            totalAmount = 0L,
            actorRoleRaw = "owner",
        )

        assertEquals(VoidAuthorizationReasonCode.DENIED_INVALID_AMOUNT, decision.reasonCode)
        assertFalse(decision.directAllowed)
    }

    @Test
    fun `denies invalid receipt`() {
        val validation = validateVoidRequest(
            saleLocalId = "",
            receiptNo = "",
            totalAmount = 100_000L,
            actorRoleRaw = "admin",
            currentSyncStatusLabel = "SYNCED",
        )

        assertFalse(validation.canEnqueue)
        assertEquals(VoidAuthorizationReasonCode.DENIED_INVALID_RECEIPT, validation.reasonCode)
    }

    @Test
    fun `PARITY-FIFO-E2E-HISTORY void payload includes governance and fifo reversal allocations`() {
        val validation = validateVoidRequest(
            saleLocalId = "sale-e2e-1",
            receiptNo = "TRX-E2E-001",
            totalAmount = 1_200_000L,
            actorRoleRaw = "kasir",
            currentSyncStatusLabel = "SYNCED",
        )

        val payload = buildVoidSaleOutboxPayload(
            saleLocalId = "sale-e2e-1",
            receiptNo = "TRX-E2E-001",
            requestedByRole = "kasir",
            validation = validation,
            fifoReversalAllocationsJson = "[{\"layer_local_id\":\"layer-1\",\"qty\":2.0}]",
        )

        assertTrue(payload.contains("\"operation\":\"void_sale\""))
        assertTrue(payload.contains("\"sale_local_id\":\"sale-e2e-1\""))
        assertTrue(payload.contains("\"requested_by_role\":\"kasir\""))
        assertTrue(payload.contains("\"approval_required\":true"))
        assertTrue(payload.contains("\"fifo_reversal_allocations\":[{\"layer_local_id\":\"layer-1\",\"qty\":2.0}]"))
    }
}
