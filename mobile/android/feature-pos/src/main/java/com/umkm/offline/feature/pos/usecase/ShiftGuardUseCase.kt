package com.umkm.offline.feature.pos.usecase

enum class ShiftGuardFailureReason {
    SHIFT_NOT_OPEN,
    SHIFT_USER_MISMATCH,
    SHIFT_BRANCH_MISMATCH,
    SHIFT_REGISTER_MISMATCH,
}

data class ShiftGuardContext(
    val shiftLocalId: String?,
    val shiftStatus: String?,
    val shiftOwnerUserLocalId: String?,
    val shiftBranchLocalId: String?,
    val shiftRegisterLocalId: String?,
    val actorUserLocalId: String?,
    val actorBranchLocalId: String?,
    val actorRegisterLocalId: String?,
)

sealed class ShiftGuardResult {
    data object Allowed : ShiftGuardResult()

    data class Rejected(
        val reason: ShiftGuardFailureReason,
    ) : ShiftGuardResult()
}

class ShiftGuardUseCase {
    operator fun invoke(context: ShiftGuardContext): ShiftGuardResult {
        if (context.shiftLocalId.isNullOrBlank()) {
            return ShiftGuardResult.Rejected(ShiftGuardFailureReason.SHIFT_NOT_OPEN)
        }

        if (!context.shiftStatus.equals("OPEN", ignoreCase = true)) {
            return ShiftGuardResult.Rejected(ShiftGuardFailureReason.SHIFT_NOT_OPEN)
        }

        if (
            context.shiftOwnerUserLocalId.isNullOrBlank() ||
            context.actorUserLocalId.isNullOrBlank() ||
            context.shiftOwnerUserLocalId != context.actorUserLocalId
        ) {
            return ShiftGuardResult.Rejected(ShiftGuardFailureReason.SHIFT_USER_MISMATCH)
        }

        if (!context.actorBranchLocalId.isNullOrBlank()) {
            if (context.shiftBranchLocalId.isNullOrBlank() || context.shiftBranchLocalId != context.actorBranchLocalId) {
                return ShiftGuardResult.Rejected(ShiftGuardFailureReason.SHIFT_BRANCH_MISMATCH)
            }
        }

        if (!context.actorRegisterLocalId.isNullOrBlank()) {
            if (context.shiftRegisterLocalId.isNullOrBlank() || context.shiftRegisterLocalId != context.actorRegisterLocalId) {
                return ShiftGuardResult.Rejected(ShiftGuardFailureReason.SHIFT_REGISTER_MISMATCH)
            }
        }

        return ShiftGuardResult.Allowed
    }
}