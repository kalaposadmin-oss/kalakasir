package com.umkm.offline.core.database

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import androidx.room.Room
import androidx.room.testing.MigrationTestHelper
import androidx.sqlite.db.SupportSQLiteDatabase
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class OfflineDatabaseMigrationInstrumentedTest {

    private val dbName = "offline-migration-test.db"

    @get:Rule
    val helper: MigrationTestHelper = MigrationTestHelper(
        InstrumentationRegistry.getInstrumentation(),
        OfflineDatabase::class.java,
        emptyList(),
    )

    @Test
    fun migrate5To8_preservesPurchaseData_andAddsRecipeAndPurchaseReturnTables() {
        helper.createDatabase(dbName, 5).apply {
            seedVersion5PurchaseData(this)
            close()
        }

        val context = ApplicationProvider.getApplicationContext<Context>()
        Room.databaseBuilder(context, OfflineDatabase::class.java, dbName)
            .build()
            .apply {
                openHelper.writableDatabase
                close()
            }

        val migrated = context.openOrCreateDatabase(dbName, Context.MODE_PRIVATE, null)
        assertTableExists(migrated, "purchase_return_items")
        assertTableExists(migrated, "recipe_items")
        assertEquals(1L, scalarCount(migrated, "SELECT COUNT(*) FROM purchases WHERE localId = 'purchase-1'"))
        assertEquals(1L, scalarCount(migrated, "SELECT COUNT(*) FROM purchase_items WHERE purchaseLocalId = 'purchase-1'"))

        migrated.close()
        context.deleteDatabase(dbName)
    }

    private fun seedVersion5PurchaseData(db: SupportSQLiteDatabase) {
        db.execSQL(
            """
            INSERT INTO purchases(
                localId, serverId, purchaseNo, supplierLocalId, subtotal, discountAmount, totalAmount,
                status, purchasedAt, dueDate, syncStatus, version, updatedAtLocal, updatedAtServer
            ) VALUES(
                'purchase-1', NULL, 'PO-MIG-001', NULL, 10000, 0, 10000,
                'received', 1000, NULL, 'PENDING_SYNC', 1, 1000, NULL
            )
            """.trimIndent(),
        )

        db.execSQL(
            """
            INSERT INTO purchase_items(
                localId, purchaseLocalId, productLocalId, qty, unitCost, lineTotal
            ) VALUES(
                'item-1', 'purchase-1', 'prod-1', 1.0, 10000, 10000
            )
            """.trimIndent(),
        )
    }

    private fun assertTableExists(db: SQLiteDatabase, tableName: String) {
        db.rawQuery("SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'", null).use { cursor ->
            assertTrue(cursor.moveToFirst())
            assertEquals(tableName, cursor.getString(0))
        }
    }

    private fun scalarCount(db: SQLiteDatabase, query: String): Long {
        db.rawQuery(query, null).use { cursor ->
            return if (cursor.moveToFirst()) cursor.getLong(0) else 0L
        }
    }
}
