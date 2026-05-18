package com.umkm.offline.app

enum class SubscriptionPlan(
    val code: String,
    val label: String,
    private val rank: Int,
) {
    S(code = "S", label = "Starter", rank = 0),
    M(code = "M", label = "Medium", rank = 1),
    P(code = "P", label = "Pro", rank = 2),
    ;

    fun includes(required: SubscriptionPlan): Boolean = rank >= required.rank

    companion object {
        fun fromCode(code: String): SubscriptionPlan {
            return entries.firstOrNull { it.code.equals(code, ignoreCase = true) } ?: S
        }
    }
}

enum class GatedFeature(
    val key: String,
    val minPlan: SubscriptionPlan,
) {
    POS_CUSTOMER("pos.customer", SubscriptionPlan.M),
    PRODUCT_MATERIALS("product.materials", SubscriptionPlan.M),
    SHIFT_PRINT_SUMMARY("shift.print_summary", SubscriptionPlan.M),
    REPORT_DOWNLOAD("reports.download_pdf", SubscriptionPlan.M),
    REPORT_EXPORT("reports.export_csv", SubscriptionPlan.M),
    REPORT_PURCHASES_VIEW("reports.purchases_view", SubscriptionPlan.M),
    REPORT_INVENTORY_STOCK_CARD("reports.inventory_stock_card", SubscriptionPlan.P),
    FINANCE_BALANCE_SHEET("finance.balance_sheet", SubscriptionPlan.P),
    FINANCE_LOCK_PERIOD("finance.lock_period", SubscriptionPlan.P),
}

fun SubscriptionPlan.canAccess(feature: GatedFeature): Boolean = includes(feature.minPlan)
