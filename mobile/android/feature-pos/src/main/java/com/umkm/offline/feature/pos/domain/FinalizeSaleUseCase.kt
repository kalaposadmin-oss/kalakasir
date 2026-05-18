package com.umkm.offline.feature.pos.domain

class FinalizeSaleUseCase(
    private val repository: PosRepository,
) {
    suspend operator fun invoke(
        receiptNo: String,
        customerLocalId: String?,
        items: List<CheckoutItem>,
        discountAmount: Long,
        paymentMethod: String,
        paidAmount: Long,
        shiftLocalId: String?,
    ): String {
        return repository.finalizeSaleOffline(
            receiptNo = receiptNo,
            customerLocalId = customerLocalId,
            items = items,
            discountAmount = discountAmount,
            paymentMethod = paymentMethod,
            paidAmount = paidAmount,
            shiftLocalId = shiftLocalId,
        )
    }
}
