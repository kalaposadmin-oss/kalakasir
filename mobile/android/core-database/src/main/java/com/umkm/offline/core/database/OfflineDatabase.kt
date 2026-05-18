package com.umkm.offline.core.database

import androidx.room.Database
import androidx.room.AutoMigration
import androidx.room.RoomDatabase
import androidx.room.TypeConverters
import com.umkm.offline.core.database.dao.AppPolicyDao
import com.umkm.offline.core.database.dao.AuditEventDao
import com.umkm.offline.core.database.dao.InventoryLayerDao
import com.umkm.offline.core.database.dao.CustomerDao
import com.umkm.offline.core.database.dao.PurchaseDao
import com.umkm.offline.core.database.dao.ProductDao
import com.umkm.offline.core.database.dao.RecipeDao
import com.umkm.offline.core.database.dao.SaleDao
import com.umkm.offline.core.database.dao.SaleFifoAllocationDao
import com.umkm.offline.core.database.dao.StockMovementDao
import com.umkm.offline.core.database.dao.SupplierDao
import com.umkm.offline.core.database.dao.SyncActionDao
import com.umkm.offline.core.database.dao.CashMovementDao
import com.umkm.offline.core.database.dao.DailyReportDao
import com.umkm.offline.core.database.dao.ShiftDao
import com.umkm.offline.core.database.dao.SyncCursorDao
import com.umkm.offline.core.database.dao.SyncEffectDao
import com.umkm.offline.core.database.entity.CashMovementEntity
import com.umkm.offline.core.database.entity.CustomerEntity
import com.umkm.offline.core.database.entity.DailyReportEntity
import com.umkm.offline.core.database.entity.AppPolicyEntity
import com.umkm.offline.core.database.entity.AuditEventEntity
import com.umkm.offline.core.database.entity.InventoryLayerEntity
import com.umkm.offline.core.database.entity.PaymentEntity
import com.umkm.offline.core.database.entity.PurchaseEntity
import com.umkm.offline.core.database.entity.PurchaseItemEntity
import com.umkm.offline.core.database.entity.PurchaseReturnItemEntity
import com.umkm.offline.core.database.entity.ProductEntity
import com.umkm.offline.core.database.entity.RecipeItemEntity
import com.umkm.offline.core.database.entity.SaleEntity
import com.umkm.offline.core.database.entity.SaleFifoAllocationEntity
import com.umkm.offline.core.database.entity.SaleItemEntity
import com.umkm.offline.core.database.entity.SupplierEntity
import com.umkm.offline.core.database.entity.ShiftEntity
import com.umkm.offline.core.database.entity.StockMovementEntity
import com.umkm.offline.core.database.entity.SyncActionEntity
import com.umkm.offline.core.database.entity.SyncCursorEntity
import com.umkm.offline.core.database.entity.SyncEffectEntity

@Database(
    entities = [
        ProductEntity::class,
        RecipeItemEntity::class,
        CustomerEntity::class,
        SaleEntity::class,
        SaleItemEntity::class,
        PaymentEntity::class,
        SupplierEntity::class,
        PurchaseEntity::class,
        PurchaseItemEntity::class,
        PurchaseReturnItemEntity::class,
        ShiftEntity::class,
        CashMovementEntity::class,
        DailyReportEntity::class,
        StockMovementEntity::class,
        InventoryLayerEntity::class,
        SaleFifoAllocationEntity::class,
        SyncActionEntity::class,
        SyncCursorEntity::class,
        SyncEffectEntity::class,
        AuditEventEntity::class,
        AppPolicyEntity::class,
    ],
    version = 11,
    autoMigrations = [
        AutoMigration(from = 5, to = 6),
        AutoMigration(from = 6, to = 7),
        AutoMigration(from = 7, to = 8),
        AutoMigration(from = 8, to = 9),
        AutoMigration(from = 9, to = 10),
        AutoMigration(from = 10, to = 11),
    ],
    exportSchema = true,
)
@TypeConverters(RoomConverters::class)
abstract class OfflineDatabase : RoomDatabase() {
    abstract fun productDao(): ProductDao
    abstract fun recipeDao(): RecipeDao
    abstract fun customerDao(): CustomerDao
    abstract fun supplierDao(): SupplierDao
    abstract fun saleDao(): SaleDao
    abstract fun stockMovementDao(): StockMovementDao
    abstract fun purchaseDao(): PurchaseDao
    abstract fun shiftDao(): ShiftDao
    abstract fun cashMovementDao(): CashMovementDao
    abstract fun dailyReportDao(): DailyReportDao
    abstract fun inventoryLayerDao(): InventoryLayerDao
    abstract fun saleFifoAllocationDao(): SaleFifoAllocationDao
    abstract fun syncActionDao(): SyncActionDao
    abstract fun syncCursorDao(): SyncCursorDao
    abstract fun syncEffectDao(): SyncEffectDao
    abstract fun auditEventDao(): AuditEventDao
    abstract fun appPolicyDao(): AppPolicyDao
}
