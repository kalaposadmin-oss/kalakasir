package com.umkm.offline.app

import android.content.Context
import androidx.compose.ui.test.assertCountEquals
import androidx.compose.ui.test.hasAnyDescendant
import androidx.compose.ui.test.hasClickAction
import androidx.compose.ui.test.hasContentDescription
import androidx.compose.ui.test.hasSetTextAction
import androidx.compose.ui.test.hasTestTag
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.test.onNodeWithContentDescription
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performTextInput
import androidx.compose.ui.test.performTextReplacement
import androidx.compose.ui.test.assertTextContains
import androidx.sqlite.db.SimpleSQLiteQuery
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.umkm.offline.core.database.OfflineDatabase
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class E2EStatusConsistencyTest {

    @get:Rule
    val composeRule = createAndroidComposeRule<MainActivity>()

    private val appContext: Context
        get() = ApplicationProvider.getApplicationContext()

    @Before
    fun setUp() {
        clearTransactionalTables(appContext)
    }

    @Test
    fun e2e_status_consistency_checkout_history_detail_void_back_to_list() {
        loginAndOpenShift()
        openPosAndCheckoutOnce()

        composeRule.waitUntil(timeoutMillis = 20_000) {
            latestSale(appContext) != null
        }
        val sale = latestSale(appContext)
        assertNotNull("Sale harus ada", sale)
        val saleLocalId = sale!!.first
        val receiptNo = sale.second

        composeRule.onNodeWithText("Riwayat").performClick()
        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodes(hasText("#$receiptNo"), useUnmergedTree = true).fetchSemanticsNodes().isNotEmpty()
        }

        composeRule.assertNodeDisplayedEventually(hasText("Sukses"), timeoutMillis = 20_000)
        composeRule.onNodeWithText("#$receiptNo", useUnmergedTree = true).performClick()

        composeRule.assertNodeDisplayedEventually(hasText("Detail Transaksi"), timeoutMillis = 20_000)
        composeRule.assertNodeDisplayedEventually(hasText("Lunas"), timeoutMillis = 20_000)
        composeRule.assertNodeDisplayedEventually(hasTestTag("sale_status_label"), timeoutMillis = 20_000)
        composeRule.onNodeWithTag("sale_status_label", useUnmergedTree = true)
            .assertTextContains("Lunas", substring = true)
        composeRule.assertNodeDisplayedEventually(hasTestTag("sale_total_label"), timeoutMillis = 20_000)
        composeRule.onNodeWithTag("sale_total_label", useUnmergedTree = true)
            .assertTextContains("Rp", substring = true)

        composeRule.assertNodeDisplayedEventually(hasTestTag("void_button"), timeoutMillis = 20_000)
        composeRule.onNodeWithTag("void_button", useUnmergedTree = true).performClick()

        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodes(hasText("#$receiptNo"), useUnmergedTree = true).fetchSemanticsNodes().isNotEmpty()
        }

        composeRule.assertNodeDisplayedEventually(
            hasText("Void Diminta") or hasText("Sudah Di-refund"),
            timeoutMillis = 30_000,
        )

        composeRule.onNodeWithText("#$receiptNo", useUnmergedTree = true).performClick()
        composeRule.assertNodeDisplayedEventually(hasText("Detail Transaksi"), timeoutMillis = 20_000)
        composeRule.onAllNodes(hasContentDescription("Void Transaksi"), useUnmergedTree = true).assertCountEquals(0)

        val saleCount = queryCount(
            appContext,
            "SELECT COUNT(*) FROM sales WHERE localId = ?",
            arrayOf(saleLocalId),
        )
        assertTrue("Data sale tidak boleh hilang setelah perpindahan screen", saleCount == 1)
    }

    private fun loginAndOpenShift() {
        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodes(hasSetTextAction()).fetchSemanticsNodes().size >= 4
        }

        val textFields = composeRule.onAllNodes(hasSetTextAction())
        textFields[0].performTextInput("kasir-e2e")
        textFields[1].performTextInput("branch-e2e")
        textFields[2].performTextInput("register-e2e")
        textFields[3].performTextInput("1234")

        composeRule.onNodeWithText("Masuk").performClick()

        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodes(hasAnyDescendant(hasText("Buka Shift")) and hasClickAction(), useUnmergedTree = true)
                .fetchSemanticsNodes().isNotEmpty() ||
                composeRule.onAllNodes(hasText("Shift Aktif"), useUnmergedTree = true)
                    .fetchSemanticsNodes().isNotEmpty()
        }

        val openShiftNodes = composeRule.onAllNodes(
            hasAnyDescendant(hasText("Buka Shift")) and hasClickAction(),
            useUnmergedTree = true,
        ).fetchSemanticsNodes()
        if (openShiftNodes.isNotEmpty()) {
            composeRule.onAllNodes(
                hasAnyDescendant(hasText("Buka Shift")) and hasClickAction(),
                useUnmergedTree = true,
            )[0].performClick()
        }

        composeRule.waitUntil(timeoutMillis = 15_000) {
            composeRule.onAllNodes(hasText("Shift Aktif"), useUnmergedTree = true).fetchSemanticsNodes().isNotEmpty()
        }
    }

    private fun openPosAndCheckoutOnce() {
        composeRule.onNodeWithText("Kasir").performClick()

        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodes(
                hasAnyDescendant(hasText("Kopi Gula Aren")) and hasClickAction(),
                useUnmergedTree = true,
            ).fetchSemanticsNodes().isNotEmpty()
        }

        composeRule.onAllNodes(
            hasAnyDescendant(hasText("Kopi Gula Aren")) and hasClickAction(),
            useUnmergedTree = true,
        )[0].performClick()

        composeRule.onNodeWithText("Lanjut Bayar").performClick()
        composeRule.waitUntil(timeoutMillis = 20_000) {
            composeRule.onAllNodes(hasText("Konfirmasi Pembayaran"), useUnmergedTree = true).fetchSemanticsNodes().isNotEmpty()
        }

        composeRule.onNodeWithTag("paid_amount_input", useUnmergedTree = true)
            .performTextReplacement("50000")
        composeRule.onNodeWithText("Konfirmasi Pembayaran").performClick()

        composeRule.assertNodeDisplayedEventually(hasText("Transaksi Berhasil!"), timeoutMillis = 20_000)
    }

    private fun clearTransactionalTables(context: Context) {
        val db = openDb(context)
        db.openHelper.writableDatabase.execSQL("DELETE FROM sync_actions_outbox")
        db.openHelper.writableDatabase.execSQL("DELETE FROM sale_fifo_allocations")
        db.openHelper.writableDatabase.execSQL("DELETE FROM payments")
        db.openHelper.writableDatabase.execSQL("DELETE FROM sale_items")
        db.openHelper.writableDatabase.execSQL("DELETE FROM sales")
        db.openHelper.writableDatabase.execSQL("DELETE FROM shifts")
        db.openHelper.writableDatabase.execSQL("DELETE FROM inventory_layers_local")
        db.close()
    }

    private fun latestSale(context: Context): Pair<String, String>? {
        val db = openDb(context)
        val cursor = db.openHelper.readableDatabase.query(SimpleSQLiteQuery("SELECT localId, receiptNo FROM sales ORDER BY finalizedAt DESC LIMIT 1"))
        val result = if (cursor.moveToFirst()) {
            cursor.getString(0) to cursor.getString(1)
        } else {
            null
        }
        cursor.close()
        db.close()
        return result
    }

    private fun queryCount(context: Context, sql: String, args: Array<Any?> = emptyArray()): Int {
        val db = openDb(context)
        val cursor = db.openHelper.readableDatabase.query(SimpleSQLiteQuery(sql, args))
        val result = if (cursor.moveToFirst()) cursor.getInt(0) else 0
        cursor.close()
        db.close()
        return result
    }

    private fun openDb(context: Context): OfflineDatabase {
        return androidx.room.Room.databaseBuilder(
            context,
            OfflineDatabase::class.java,
            "umkm-offline.db",
        ).fallbackToDestructiveMigration().build()
    }
}
