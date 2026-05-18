package com.umkm.offline.app

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.Calendar

class MainActivityShiftLogicTest {

    @Test
    fun `parse cash input accepts digit separators`() {
        assertEquals(100000L, parseCashAmountOrNull("100.000"))
        assertEquals(250000L, parseCashAmountOrNull("Rp 250000"))
        assertNull(parseCashAmountOrNull(""))
        assertNull(parseCashAmountOrNull("abc"))
    }

    @Test
    fun `cash difference is actual minus expected`() {
        assertEquals(15000L, calculateShiftCashDifference(expectedClosingCash = 100000L, actualClosingCash = 115000L))
        assertEquals(-5000L, calculateShiftCashDifference(expectedClosingCash = 100000L, actualClosingCash = 95000L))
    }

    @Test
    fun `shift expires when crossing local midnight`() {
        val now = Calendar.getInstance().apply {
            set(Calendar.HOUR_OF_DAY, 10)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }.timeInMillis

        val yesterdayOpenedAt = Calendar.getInstance().apply {
            timeInMillis = now
            add(Calendar.DAY_OF_MONTH, -1)
            set(Calendar.HOUR_OF_DAY, 23)
            set(Calendar.MINUTE, 59)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }.timeInMillis

        val todayOpenedAt = Calendar.getInstance().apply {
            timeInMillis = now
            set(Calendar.HOUR_OF_DAY, 8)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }.timeInMillis

        assertTrue(isShiftExpiredByDate(openedAt = yesterdayOpenedAt, now = now))
        assertFalse(isShiftExpiredByDate(openedAt = todayOpenedAt, now = now))
    }
}
