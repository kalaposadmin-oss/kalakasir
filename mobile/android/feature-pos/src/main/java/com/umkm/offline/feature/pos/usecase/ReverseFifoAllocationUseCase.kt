package com.umkm.offline.feature.pos.usecase

data class ReverseFifoAllocationOutcome(
    val restoredByLayer: Map<String, Double>,
)

class ReverseFifoAllocationUseCase {
    fun reverse(
        allocations: List<FifoAllocationResult>,
        currentByLayer: Map<String, Double>,
    ): ReverseFifoAllocationOutcome {
        if (allocations.isEmpty()) {
            return ReverseFifoAllocationOutcome(restoredByLayer = emptyMap())
        }

        val restored = currentByLayer.toMutableMap()
        for (allocation in allocations) {
            val current = restored[allocation.layerLocalId] ?: 0.0
            restored[allocation.layerLocalId] = current + allocation.qtyIssued
        }

        return ReverseFifoAllocationOutcome(restoredByLayer = restored)
    }
}
