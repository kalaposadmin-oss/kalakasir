package com.umkm.offline.feature.pos.usecase

data class FifoLayerSnapshot(
    val layerLocalId: String,
    val qtyAvailable: Double,
    val unitCost: Double,
)

data class FifoAllocationResult(
    val layerLocalId: String,
    val qtyIssued: Double,
    val unitCost: Double,
    val totalCost: Double,
    val allocationOrder: Int,
)

data class AllocateFifoLayersOutcome(
    val allocations: List<FifoAllocationResult>,
    val remainingByLayer: Map<String, Double>,
)

class AllocateFifoLayersUseCase {
    fun allocate(layers: List<FifoLayerSnapshot>, requestedQty: Double): AllocateFifoLayersOutcome {
        require(requestedQty > 0.0) { "Qty issue harus lebih besar dari 0." }

        var remaining = requestedQty
        val allocations = mutableListOf<FifoAllocationResult>()
        val remainingByLayer = mutableMapOf<String, Double>()

        layers.forEach { layer -> remainingByLayer[layer.layerLocalId] = layer.qtyAvailable }

        for (layer in layers) {
            if (remaining <= 0.0) {
                break
            }
            if (layer.qtyAvailable <= 0.0) {
                continue
            }

            val issued = minOf(layer.qtyAvailable, remaining)
            val nextQty = (remainingByLayer[layer.layerLocalId] ?: layer.qtyAvailable) - issued
            remainingByLayer[layer.layerLocalId] = nextQty

            allocations += FifoAllocationResult(
                layerLocalId = layer.layerLocalId,
                qtyIssued = issued,
                unitCost = layer.unitCost,
                totalCost = issued * layer.unitCost,
                allocationOrder = allocations.size + 1,
            )

            remaining -= issued
        }

        if (remaining > 1e-9) {
            throw IllegalStateException("FIFO_LAYER_INSUFFICIENT")
        }

        return AllocateFifoLayersOutcome(
            allocations = allocations,
            remainingByLayer = remainingByLayer,
        )
    }
}