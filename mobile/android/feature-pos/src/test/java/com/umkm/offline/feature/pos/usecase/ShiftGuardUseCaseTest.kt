package com.umkm.offline.feature.pos.usecase

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class ShiftGuardUseCaseTest {

    private val useCase = ShiftGuardUseCase()

    @Test
    fun returnsShiftNotOpen_whenShiftIsMissing() {
        val result = useCase(
            ShiftGuardContext(
                shiftLocalId = null,
                shiftStatus = null,
                shiftOwnerUserLocalId = null,
                shiftBranchLocalId = null,
                shiftRegisterLocalId = null,
                actorUserLocalId = "cashier-1",
                actorBranchLocalId = null,
                actorRegisterLocalId = null,
            ),
        )

        assertEquals(
            ShiftGuardFailureReason.SHIFT_NOT_OPEN,
            (result as ShiftGuardResult.Rejected).reason,
        )
    }

    @Test
    fun returnsUserMismatch_whenActorDoesNotOwnOpenShift() {
        val result = useCase(
            ShiftGuardContext(
                shiftLocalId = "shift-1",
                shiftStatus = "OPEN",
                shiftOwnerUserLocalId = "cashier-2",
                shiftBranchLocalId = null,
                shiftRegisterLocalId = null,
                actorUserLocalId = "cashier-1",
                actorBranchLocalId = null,
                actorRegisterLocalId = null,
            ),
        )

        assertEquals(
            ShiftGuardFailureReason.SHIFT_USER_MISMATCH,
            (result as ShiftGuardResult.Rejected).reason,
        )
    }

    @Test
    fun allows_whenShiftContextMatchesActorContext() {
        val result = useCase(
            ShiftGuardContext(
                shiftLocalId = "shift-1",
                shiftStatus = "OPEN",
                shiftOwnerUserLocalId = "cashier-1",
                shiftBranchLocalId = "branch-a",
                shiftRegisterLocalId = "register-a",
                actorUserLocalId = "cashier-1",
                actorBranchLocalId = "branch-a",
                actorRegisterLocalId = "register-a",
            ),
        )

        assertTrue(result is ShiftGuardResult.Allowed)
    }
}
