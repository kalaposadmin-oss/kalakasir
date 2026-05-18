package com.umkm.offline.feature.home

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.AddShoppingCart
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.LockClock
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.PowerSettingsNew
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import java.text.NumberFormat
import java.util.Locale

@Composable
fun HomeScreen(
    cashierName: String,
    pendingSyncCount: Int,
    canSeeGlobalNotification: Boolean,
    canSeeControlSection: Boolean,
    isShiftOpen: Boolean,
    shiftOpenedLabel: String,
    openingCash: Long,
    shiftSales: Long,
    todaySales: Long,
    todayTransactions: Int,
    onOpenPos: () -> Unit,
    onOpenShift: () -> Unit,
    onCloseShift: () -> Unit,
    onOpenSyncStatus: () -> Unit,
) {
    val bg = Color(0xFFF8FAF9)
    val primary = Color(0xFF7BAE9E)

    Surface(color = bg) {
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 14.dp),
            contentPadding = PaddingValues(top = 10.dp, bottom = 96.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                HeaderBlock(
                    cashierName = cashierName,
                    canSeeGlobalNotification = canSeeGlobalNotification,
                )
            }

            item {
                ShiftStatusCard(
                    primary = primary,
                    isShiftOpen = isShiftOpen,
                    shiftOpenedLabel = shiftOpenedLabel,
                    openingCash = openingCash,
                    shiftSales = shiftSales,
                    onShiftAction = if (isShiftOpen) onCloseShift else onOpenShift,
                )
            }

            item {
                SummaryStrip(
                    primary = primary,
                    todaySales = todaySales,
                    todayTransactions = todayTransactions,
                )
            }

            item {
                QuickActionSection(
                    primary = primary,
                    canSeeControlSection = canSeeControlSection,
                    isShiftOpen = isShiftOpen,
                    onOpenPos = onOpenPos,
                    onOpenShift = onOpenShift,
                    onCloseShift = onCloseShift,
                    onOpenSyncStatus = onOpenSyncStatus,
                )
            }

            item {
                BestSellerCard()
            }

            item {
                LowStockCard(pendingSyncCount = pendingSyncCount)
            }
        }
    }
}

@Composable
private fun HeaderBlock(cashierName: String, canSeeGlobalNotification: Boolean) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = "Dashboard Kasir",
                    color = Color(0xFF0F172A),
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp,
                )
                Text(
                    text = "Akses cepat transaksi harian • $cashierName",
                    color = Color(0xFF64748B),
                    fontWeight = FontWeight.Medium,
                    fontSize = 11.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(Color(0xFFF1F5F9)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.Logout,
                    contentDescription = "Logout",
                    tint = if (canSeeGlobalNotification) Color(0xFF64748B) else Color(0xFF94A3B8),
                )
            }
        }
    }
}

@Composable
private fun ShiftStatusCard(
    primary: Color,
    isShiftOpen: Boolean,
    shiftOpenedLabel: String,
    openingCash: Long,
    shiftSales: Long,
    onShiftAction: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Row(
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        modifier = Modifier
                            .size(9.dp)
                            .clip(CircleShape)
                            .background(primary),
                    )
                    Text(
                        text = if (isShiftOpen) "Status: Shift Aktif" else "Status: Shift Belum Dibuka",
                        color = Color(0xFF334155),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                    )
                }
                Text(
                    text = shiftOpenedLabel,
                    color = Color(0xFF94A3B8),
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                )
            }

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(text = "Kas Awal", color = Color(0xFF64748B), fontSize = 13.sp)
                Text(text = formatRupiah(openingCash), color = Color(0xFF0F172A), fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
            }

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(text = "Penjualan Shift", color = Color(0xFF64748B), fontSize = 13.sp)
                Text(text = formatRupiah(shiftSales), color = primary, fontWeight = FontWeight.Bold, fontSize = 18.sp)
            }

            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .clickable(onClick = onShiftAction),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF1F2)),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center,
                ) {
                    Icon(
                        imageVector = Icons.Default.PowerSettingsNew,
                        contentDescription = null,
                        tint = Color(0xFFE11D48),
                        modifier = Modifier.size(18.dp),
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = if (isShiftOpen) "Tutup Shift" else "Buka Shift",
                        color = Color(0xFFE11D48),
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.sp,
                    )
                }
            }
        }
    }
}

@Composable
private fun SummaryStrip(
    primary: Color,
    todaySales: Long,
    todayTransactions: Int,
) {
    val averageTicket = if (todayTransactions > 0) todaySales / todayTransactions else 0L

    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        item {
            SummaryMiniCard(
                title = "Penjualan Hari Ini",
                value = formatRupiah(todaySales),
                width = 148.dp,
            )
        }
        item {
            SummaryMiniCard(
                title = "Transaksi",
                value = todayTransactions.toString(),
                width = 108.dp,
            )
        }
        item {
            SummaryMiniCard(
                title = "Rata-rata",
                value = formatRupiah(averageTicket),
                width = 118.dp,
                valueColor = primary,
            )
        }
    }
}

private fun formatRupiah(value: Long): String {
    return NumberFormat.getCurrencyInstance(Locale("id", "ID"))
        .format(value)
        .replace(",00", "")
}

@Composable
private fun SummaryMiniCard(
    title: String,
    value: String,
    width: androidx.compose.ui.unit.Dp,
    valueColor: Color = Color(0xFF0F172A),
) {
    Card(
        modifier = Modifier
            .width(width)
            .height(82.dp),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Text(
                text = title.uppercase(),
                color = Color(0xFF94A3B8),
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
            )
            Text(
                text = value,
                color = valueColor,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

@Composable
private fun QuickActionSection(
    primary: Color,
    canSeeControlSection: Boolean,
    isShiftOpen: Boolean,
    onOpenPos: () -> Unit,
    onOpenShift: () -> Unit,
    onCloseShift: () -> Unit,
    onOpenSyncStatus: () -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = "AKSI CEPAT",
            color = Color(0xFF94A3B8),
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.sp,
            modifier = Modifier.padding(start = 2.dp),
        )

        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            QuickActionButton(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.AddShoppingCart,
                title = "Mulai Transaksi",
                iconColor = Color.White,
                bgColor = primary,
                titleColor = Color.White,
                enabled = true,
                onClick = onOpenPos,
            )
            QuickActionButton(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.History,
                title = "Riwayat Penjualan",
                iconColor = primary,
                bgColor = Color.White,
                titleColor = Color(0xFF0F172A),
                enabled = true,
                onClick = onOpenSyncStatus,
            )
        }

        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            QuickActionButton(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.LockClock,
                title = if (isShiftOpen) "Shift Aktif" else "Buka Shift",
                iconColor = Color(0xFF94A3B8),
                bgColor = Color(0xFFF1F5F9),
                titleColor = Color(0xFF94A3B8),
                enabled = !isShiftOpen,
                onClick = onOpenShift,
            )
            QuickActionButton(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.PowerSettingsNew,
                title = "Tutup Shift",
                iconColor = Color(0xFFFB7185),
                bgColor = Color.White,
                titleColor = Color(0xFF0F172A),
                enabled = isShiftOpen,
                onClick = onCloseShift,
            )
        }
    }
}

@Composable
private fun QuickActionButton(
    modifier: Modifier,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    iconColor: Color,
    bgColor: Color,
    titleColor: Color,
    enabled: Boolean,
    onClick: () -> Unit,
) {
    Card(
        modifier = modifier
            .height(112.dp)
            .clip(RoundedCornerShape(16.dp))
            .then(if (enabled) Modifier.clickable(onClick = onClick) else Modifier),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = bgColor),
        elevation = CardDefaults.cardElevation(defaultElevation = if (enabled) 1.dp else 0.dp),
    ) {
        Column(
            modifier = Modifier
                .background(if (enabled) Color.Transparent else Color(0x55F8FAFC))
                .padding(12.dp)
                .fillMaxWidth(),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(icon, contentDescription = null, tint = iconColor)
            Spacer(modifier = Modifier.height(10.dp))
            Text(
                text = title,
                color = titleColor,
                fontWeight = FontWeight.Bold,
                fontSize = 12.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

@Composable
private fun BestSellerCard() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Text(
                text = "PRODUK TERLARIS",
                color = Color(0xFF94A3B8),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
            )

            BestSellerRow(name = "Kopi Susu Aren", qty = "42 pcs")
            BestSellerRow(name = "Roti Bakar Coklat", qty = "28 pcs")
            BestSellerRow(name = "Es Teh Manis", qty = "15 pcs")
        }
    }
}

@Composable
private fun BestSellerRow(name: String, qty: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text = name, color = Color(0xFF334155), fontSize = 13.sp)
        Text(text = qty, color = Color(0xFF0F172A), fontWeight = FontWeight.Bold, fontSize = 13.sp)
    }
}

@Composable
private fun LowStockCard(pendingSyncCount: Int) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF7ED)),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(
                    imageVector = Icons.Default.Warning,
                    contentDescription = null,
                    tint = Color(0xFFF97316),
                    modifier = Modifier.size(16.dp),
                )
                Text(
                    text = "STOK MENIPIS",
                    color = Color(0xFFF97316),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                )
            }

            StockRow(name = "Susu UHT 1L", stock = "Sisa 2")
            StockRow(name = "Bubuk Coklat Premium", stock = "Sisa 3")

            if (pendingSyncCount > 0) {
                Text(
                    text = "Perlu sinkronisasi: $pendingSyncCount data",
                    color = Color(0xFF9A3412),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                )
            }
        }
    }
}

@Composable
private fun StockRow(name: String, stock: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Icon(
                imageVector = Icons.Default.PointOfSale,
                contentDescription = null,
                tint = Color(0xFFFB923C),
                modifier = Modifier.size(14.dp),
            )
            Text(text = name, color = Color(0xFF334155), fontSize = 13.sp)
        }
        Text(text = stock, color = Color(0xFFEA580C), fontWeight = FontWeight.Bold, fontSize = 12.sp)
    }
}
