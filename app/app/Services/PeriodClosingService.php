<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class PeriodClosingService
{
    public function assistantStatus(string $tenantId, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?: CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta')))->startOfDay();
        $targetStart = $today->subMonthNoOverflow()->startOfMonth();
        $targetEnd = $today->subMonthNoOverflow()->endOfMonth();
        $start = $targetStart->toDateString();
        $end = $targetEnd->toDateString();
        $period = DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->where('start_date', $start)
            ->where('end_date', $end)
            ->orderByDesc('locked_at')
            ->first();
        $isClosed = (bool) ($period?->is_locked ?? false);
        $daysAfterPeriodEnd = max(0, (int) $targetEnd->startOfDay()->diffInDays($today, false));
        $preview = $this->preview($tenantId, $start, $end);
        $checklist = $this->assistantChecklist($tenantId, $start, $end, $preview);
        $openIssueCount = collect($checklist)->where('status', 'blocked')->count();
        $warningIssueCount = collect($checklist)->where('status', 'warning')->count();
        $severity = 'safe';

        if (! $isClosed) {
            $severity = $daysAfterPeriodEnd >= 10 ? 'urgent' : ($daysAfterPeriodEnd >= 4 ? 'due' : 'review');
        }

        return [
            'today' => $today->toDateString(),
            'target_period' => [
                'start_date' => $start,
                'end_date' => $end,
                'label' => $targetStart->locale('id')->isoFormat('MMMM YYYY'),
                'is_closed' => $isClosed,
                'period_id' => $period?->id,
                'days_after_period_end' => $daysAfterPeriodEnd,
            ],
            'status' => [
                'severity' => $severity,
                'title' => $this->assistantTitle($isClosed, $severity, $targetStart),
                'message' => $this->assistantMessage($isClosed, $severity, $targetStart, $openIssueCount, $warningIssueCount),
                'restriction_mode' => $this->restrictionMode($isClosed, $daysAfterPeriodEnd),
                'recommended_restrictions' => $this->recommendedRestrictions($isClosed, $daysAfterPeriodEnd),
            ],
            'checklist' => $checklist,
            'preview' => $preview,
        ];
    }

    public function preview(string $tenantId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->toDateString();
        $end = CarbonImmutable::parse($endDate)->toDateString();

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'checks' => $this->checks($tenantId, $start, $end),
            'summary' => $this->summary($tenantId, $start, $end),
            'carry_forward' => $this->carryForwardSummary($tenantId, $end),
        ];
    }

    public function close(string $tenantId, string $startDate, string $endDate, ?string $actorId, ?string $reason = null, ?string $name = null): array
    {
        $start = CarbonImmutable::parse($startDate)->toDateString();
        $end = CarbonImmutable::parse($endDate)->toDateString();
        $preview = $this->preview($tenantId, $start, $end);

        if ($preview['checks']['open_shift_count'] > 0) {
            throw new RuntimeException('Masih ada shift terbuka pada periode ini. Tutup semua shift sebelum tutup buku.');
        }

        $now = CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($tenantId, $start, $end, $actorId, $reason, $name, $preview, $now): array {
            $period = DB::table('lock_periods')
                ->where('tenant_id', $tenantId)
                ->where('start_date', $start)
                ->where('end_date', $end)
                ->lockForUpdate()
                ->first();

            $periodData = [
                'is_locked' => true,
                'locked_by' => $actorId,
                'locked_at' => $now,
                'reason' => $reason,
            ];

            if (Schema::hasColumn('lock_periods', 'name')) {
                $periodData['name'] = $name;
            }
            if (Schema::hasColumn('lock_periods', 'closed_by')) {
                $periodData['closed_by'] = $actorId;
            }
            if (Schema::hasColumn('lock_periods', 'closed_at')) {
                $periodData['closed_at'] = $now;
            }
            if (Schema::hasColumn('lock_periods', 'close_summary_json')) {
                $periodData['close_summary_json'] = json_encode($preview, JSON_THROW_ON_ERROR);
            }

            if ($period) {
                DB::table('lock_periods')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $period->id)
                    ->update($periodData);
                $periodId = (string) $period->id;
            } else {
                $periodId = (string) Str::uuid();
                DB::table('lock_periods')->insert([
                    'id' => $periodId,
                    'tenant_id' => $tenantId,
                    'start_date' => $start,
                    'end_date' => $end,
                ] + $periodData);
            }

            $this->replaceSnapshots($tenantId, $periodId, $end);
            $this->audit($tenantId, $actorId, 'period_closed', $periodId, [
                'start_date' => $start,
                'end_date' => $end,
                'reason' => $reason,
                'summary' => $preview['summary'],
                'carry_forward' => $preview['carry_forward'],
            ]);

            return [
                'period_id' => $periodId,
                'status' => 'closed',
                'preview' => $preview,
            ];
        });
    }

    public function reopen(string $tenantId, string $periodId, ?string $actorId, ?string $reason = null): array
    {
        $period = DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->where('id', $periodId)
            ->first();

        if (! $period) {
            throw new RuntimeException('Periode lock tidak ditemukan.');
        }

        $data = [
            'is_locked' => false,
            'reason' => $reason ?? $period->reason,
        ];

        if (Schema::hasColumn('lock_periods', 'reopened_by')) {
            $data['reopened_by'] = $actorId;
        }
        if (Schema::hasColumn('lock_periods', 'reopened_at')) {
            $data['reopened_at'] = CarbonImmutable::now('UTC');
        }

        DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->where('id', $periodId)
            ->update($data);

        $this->audit($tenantId, $actorId, 'period_reopened', $periodId, [
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'reason' => $reason,
        ]);

        return [
            'period_id' => $periodId,
            'status' => 'open',
        ];
    }

    private function checks(string $tenantId, string $startDate, string $endDate): array
    {
        $openShiftCount = Schema::hasTable('shifts')
            ? DB::table('shifts')
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->whereDate('opened_at', '<=', $endDate)
                ->count()
            : 0;

        $negativeStockCount = Schema::hasTable('inventory_balances')
            ? DB::table('inventory_balances')
                ->where('tenant_id', $tenantId)
                ->where('qty_on_hand', '<', 0)
                ->count()
            : 0;

        return [
            'open_shift_count' => (int) $openShiftCount,
            'negative_stock_count' => (int) $negativeStockCount,
            'can_close' => $openShiftCount === 0,
        ];
    }

    private function assistantChecklist(string $tenantId, string $startDate, string $endDate, array $preview): array
    {
        $checks = $preview['checks'] ?? [];
        $summary = $preview['summary'] ?? [];
        $carry = $preview['carry_forward'] ?? [];
        $pendingPurchases = $this->pendingPurchaseCounts($tenantId, $startDate, $endDate);
        $stockWithoutCost = $this->stockWithoutCostCount($tenantId);

        return [
            [
                'key' => 'shift_cash',
                'title' => 'Tutup shift dan kas harian',
                'description' => 'Pastikan tidak ada shift terbuka sebelum tutup buku.',
                'review_scope' => 'Data yang dicek: shift yang masih open sampai akhir periode.',
                'correction_action' => 'Kalau masih ada shift terbuka, buka ringkasan shift lalu tutup shift/kas harian yang belum selesai.',
                'status' => ((int) ($checks['open_shift_count'] ?? 0)) > 0 ? 'blocked' : 'done',
                'metric_label' => 'Shift terbuka',
                'metric_value' => (int) ($checks['open_shift_count'] ?? 0),
                'action_url' => '/pos/shifts/summary',
                'action_label' => 'Buka ringkasan shift',
            ],
            [
                'key' => 'sales_review',
                'title' => 'Review penjualan dan retur',
                'description' => 'Cek total penjualan, refund, dan transaksi void bulan ini.',
                'review_scope' => 'Data yang dicek: total penjualan, refund, void, diskon, dan tanggal transaksi pada periode ini.',
                'correction_action' => 'Kalau ada transaksi salah, koreksi lewat refund/void resmi atau buat transaksi pengganti; jangan tutup buku dulu.',
                'status' => 'review',
                'metric_label' => 'Penjualan',
                'metric_value' => (float) ($summary['sales_total'] ?? 0),
                'action_url' => '/backoffice/reports/sales',
                'action_label' => 'Buka laporan penjualan',
            ],
            [
                'key' => 'purchase_review',
                'title' => 'Review PO dan pembelian',
                'description' => 'Selesaikan PO yang memang sudah diterima, atau biarkan terbuka jika barang belum datang.',
                'review_scope' => 'Data yang dicek: PO draft/submitted/approved/partial received pada periode ini.',
                'correction_action' => 'Kalau barang sudah diterima, posting penerimaan. Kalau PO salah, edit/batalkan sesuai statusnya. Kalau barang belum datang, boleh dibiarkan terbuka.',
                'status' => $pendingPurchases['total'] > 0 ? 'warning' : 'done',
                'metric_label' => 'PO aktif',
                'metric_value' => $pendingPurchases['total'],
                'details' => $pendingPurchases,
                'action_url' => '/backoffice/purchases',
                'action_label' => 'Buka daftar pembelian',
            ],
            [
                'key' => 'cash_bank',
                'title' => 'Cek saldo kas dan bank',
                'description' => 'Samakan saldo fisik kas/bank dengan angka sistem.',
                'review_scope' => 'Data yang dicek: saldo kas, bank, mutasi kas masuk/keluar, pembayaran penjualan, dan settlement.',
                'correction_action' => 'Kalau angka tidak sama dengan uang fisik/rekening, input kas masuk/keluar, settlement, atau koreksi transaksi sumbernya.',
                'status' => 'review',
                'metric_label' => 'Saldo kas/bank',
                'metric_value' => (float) ($carry['cash_balance'] ?? 0),
                'action_url' => '/backoffice/finance/summary',
                'action_label' => 'Buka ringkasan keuangan',
            ],
            [
                'key' => 'stock_review',
                'title' => 'Cek stok dan HPP',
                'description' => 'Stok minus harus diperbaiki. Produk tanpa harga modal perlu dicek sebelum laporan final.',
                'review_scope' => 'Data yang dicek: stok minus dan produk yang masih punya stok tetapi harga modalnya kosong/nol.',
                'correction_action' => 'Kalau stok fisik salah, lakukan stock opname atau penyesuaian stok. Kalau HPP kosong, lengkapi pembelian/stok awal/biaya produksi produk tersebut.',
                'status' => ((int) ($checks['negative_stock_count'] ?? 0)) > 0 ? 'blocked' : ($stockWithoutCost > 0 ? 'warning' : 'done'),
                'metric_label' => 'Masalah stok',
                'metric_value' => (int) ($checks['negative_stock_count'] ?? 0) + $stockWithoutCost,
                'details' => [
                    'negative_stock_count' => (int) ($checks['negative_stock_count'] ?? 0),
                    'stock_without_cost_count' => $stockWithoutCost,
                ],
                'action_url' => '/backoffice/reports/inventory',
                'action_label' => 'Buka laporan stok',
            ],
            [
                'key' => 'debt_receivable',
                'title' => 'Cek hutang dan piutang',
                'description' => 'Hutang/piutang tidak dihapus; nilainya dibawa sebagai saldo periode berikutnya.',
                'review_scope' => 'Data yang dicek: saldo hutang supplier dan piutang pelanggan yang masih terbuka.',
                'correction_action' => 'Kalau sudah lunas, catat pembayaran/pelunasan. Kalau tagihan salah, koreksi transaksi pembelian/penjualan sumbernya.',
                'status' => 'review',
                'metric_label' => 'Hutang + piutang',
                'metric_value' => (float) ($carry['supplier_debt'] ?? 0) + (float) ($carry['receivable_balance'] ?? 0),
                'action_url' => '/backoffice/finance/summary',
                'action_label' => 'Buka ringkasan keuangan',
            ],
            [
                'key' => 'close_period',
                'title' => 'Preview dan tutup buku',
                'description' => 'Jika semua tahap sudah aman, tutup periode untuk mengunci laporan.',
                'review_scope' => 'Data yang dicek: hasil preview saldo akhir kas, nilai stok, hutang, dan piutang yang akan disimpan sebagai snapshot.',
                'correction_action' => 'Kalau preview belum cocok, kembali ke langkah yang bermasalah lalu perbaiki datanya sebelum klik Review & Tutup Buku.',
                'status' => (bool) ($checks['can_close'] ?? false) ? 'ready' : 'blocked',
                'metric_label' => 'Siap tutup',
                'metric_value' => (bool) ($checks['can_close'] ?? false) ? 1 : 0,
                'action_url' => '/backoffice/finance/lock-periods',
                'action_label' => 'Review & tutup buku',
            ],
        ];
    }

    private function pendingPurchaseCounts(string $tenantId, string $startDate, string $endDate): array
    {
        if (! Schema::hasTable('purchases')) {
            return ['draft' => 0, 'submitted' => 0, 'approved' => 0, 'partially_received' => 0, 'total' => 0];
        }

        $rows = DB::table('purchases')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'submitted', 'approved', 'partially_received'])
            ->where(function ($query) use ($startDate, $endDate): void {
                $dateColumn = Schema::hasColumn('purchases', 'transaction_date') ? 'transaction_date' : 'created_at';
                if ($dateColumn === 'transaction_date') {
                    $query->whereBetween('transaction_date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
                }
            })
            ->select('status', DB::raw('COUNT(*) as count_value'))
            ->groupBy('status')
            ->pluck('count_value', 'status');

        $counts = [
            'draft' => (int) ($rows['draft'] ?? 0),
            'submitted' => (int) ($rows['submitted'] ?? 0),
            'approved' => (int) ($rows['approved'] ?? 0),
            'partially_received' => (int) ($rows['partially_received'] ?? 0),
        ];
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    private function stockWithoutCostCount(string $tenantId): int
    {
        if (! Schema::hasTable('inventory_balances') || ! Schema::hasTable('products')) {
            return 0;
        }

        return (int) DB::table('inventory_balances as ib')
            ->join('products as p', function ($join): void {
                $join->on('p.id', '=', 'ib.product_id')
                    ->on('p.tenant_id', '=', 'ib.tenant_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->where('ib.qty_on_hand', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('p.hpp_average')->orWhere('p.hpp_average', '<=', 0);
            })
            ->count();
    }

    private function assistantTitle(bool $isClosed, string $severity, CarbonImmutable $targetStart): string
    {
        if ($isClosed) {
            return 'Tutup buku '.$targetStart->locale('id')->isoFormat('MMMM YYYY').' sudah selesai';
        }

        return match ($severity) {
            'urgent' => 'Tutup buku '.$targetStart->locale('id')->isoFormat('MMMM YYYY').' wajib diselesaikan',
            'due' => 'Tutup buku '.$targetStart->locale('id')->isoFormat('MMMM YYYY').' sudah jatuh tempo',
            default => 'Saatnya review tutup buku '.$targetStart->locale('id')->isoFormat('MMMM YYYY'),
        };
    }

    private function assistantMessage(bool $isClosed, string $severity, CarbonImmutable $targetStart, int $openIssueCount, int $warningIssueCount): string
    {
        if ($isClosed) {
            return 'Periode sebelumnya sudah terkunci. Transaksi bulan berjalan bisa dilanjutkan seperti biasa.';
        }

        $issueText = $openIssueCount > 0
            ? $openIssueCount.' tahap wajib dibereskan'
            : ($warningIssueCount > 0 ? $warningIssueCount.' tahap perlu direview' : 'checklist siap direview');

        return match ($severity) {
            'urgent' => 'Periode '.$targetStart->locale('id')->isoFormat('MMMM YYYY').' belum ditutup. '.$issueText.'. Fitur admin sebaiknya dibatasi sampai tutup buku selesai.',
            'due' => 'Periode '.$targetStart->locale('id')->isoFormat('MMMM YYYY').' belum ditutup. '.$issueText.'. Selesaikan sebelum laporan bulan berjalan dipakai.',
            default => 'Sekarang sudah bulan baru. Review '.$targetStart->locale('id')->isoFormat('MMMM YYYY').' lewat checklist sebelum menutup buku.',
        };
    }

    private function restrictionMode(bool $isClosed, int $daysAfterPeriodEnd): string
    {
        if ($isClosed || $daysAfterPeriodEnd <= 3) {
            return 'none';
        }

        return $daysAfterPeriodEnd >= 10 ? 'admin_limited' : 'reminder';
    }

    private function recommendedRestrictions(bool $isClosed, int $daysAfterPeriodEnd): array
    {
        if ($isClosed || $daysAfterPeriodEnd < 10) {
            return [];
        }

        return [
            'Batasi edit transaksi periode lama.',
            'Batasi backdate pembelian/penjualan ke periode yang belum ditutup.',
            'Minta owner/admin menyelesaikan checklist tutup buku.',
            'Kasir tetap boleh menjalankan penjualan bulan berjalan.',
        ];
    }

    private function summary(string $tenantId, string $startDate, string $endDate): array
    {
        return [
            'sales_total' => $this->tableTotal('sales', $tenantId, $startDate, $endDate),
            'sales_count' => $this->tableCount('sales', $tenantId, $startDate, $endDate),
            'purchase_total' => $this->tableTotal('purchases', $tenantId, $startDate, $endDate),
            'purchase_count' => $this->tableCount('purchases', $tenantId, $startDate, $endDate),
            'refund_total' => $this->tableTotal('refunds', $tenantId, $startDate, $endDate),
            'purchase_return_total' => $this->tableTotal('purchase_returns', $tenantId, $startDate, $endDate),
            'cash_in_total' => $this->cashTransactionTotal('cash-in', $tenantId, $startDate, $endDate),
            'cash_out_total' => $this->cashTransactionTotal('cash-out', $tenantId, $startDate, $endDate),
        ];
    }

    private function carryForwardSummary(string $tenantId, string $endDate): array
    {
        return [
            'cash_balance' => round(collect($this->cashSnapshots($tenantId, $endDate))->sum('amount'), 2),
            'stock_qty' => round((float) (Schema::hasTable('inventory_balances')
                ? DB::table('inventory_balances')->where('tenant_id', $tenantId)->sum('qty_on_hand')
                : 0), 4),
            'stock_value' => round(collect($this->stockSnapshots($tenantId))->sum('amount'), 2),
            'supplier_debt' => round((float) (Schema::hasTable('suppliers')
                ? DB::table('suppliers')->where('tenant_id', $tenantId)->sum('balance')
                : 0), 2),
            'receivable_balance' => round((float) (Schema::hasTable('receivables')
                ? DB::table('receivables')->where('tenant_id', $tenantId)->sum('remaining_amount')
                : 0), 2),
        ];
    }

    private function replaceSnapshots(string $tenantId, string $periodId, string $endDate): void
    {
        DB::table('period_closing_balances')
            ->where('tenant_id', $tenantId)
            ->where('lock_period_id', $periodId)
            ->delete();

        $rows = array_merge(
            $this->cashSnapshots($tenantId, $endDate),
            $this->stockSnapshots($tenantId),
            $this->supplierDebtSnapshots($tenantId),
            $this->receivableSnapshots($tenantId)
        );

        if ($rows === []) {
            return;
        }

        DB::table('period_closing_balances')->insert(array_map(function (array $row) use ($tenantId, $periodId): array {
            return [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'lock_period_id' => $periodId,
                'balance_type' => $row['balance_type'],
                'reference_type' => $row['reference_type'] ?? null,
                'reference_id' => $row['reference_id'] ?? null,
                'label' => $row['label'] ?? null,
                'quantity' => $row['quantity'] ?? null,
                'amount' => round((float) ($row['amount'] ?? 0), 2),
                'payload_json' => isset($row['payload']) ? json_encode($row['payload'], JSON_THROW_ON_ERROR) : null,
                'created_at' => CarbonImmutable::now('UTC'),
            ];
        }, $rows));
    }

    private function cashSnapshots(string $tenantId, string $endDate): array
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return [];
        }

        $accountIds = $this->cashAccountIds($tenantId);
        if ($accountIds === []) {
            return [];
        }

        return DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $accountIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(function ($account) use ($tenantId, $endDate): array {
                return [
                    'balance_type' => 'cash',
                    'reference_type' => 'chart_of_account',
                    'reference_id' => (string) $account->id,
                    'label' => trim((string) $account->code.' - '.(string) $account->name),
                    'amount' => $this->accountBalance($tenantId, (string) $account->id, $endDate, (string) $account->type),
                    'payload' => [
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                    ],
                ];
            })
            ->all();
    }

    private function stockSnapshots(string $tenantId): array
    {
        if (! Schema::hasTable('inventory_balances')) {
            return [];
        }

        return DB::table('inventory_balances as ib')
            ->leftJoin('products as p', function ($join): void {
                $join->on('p.id', '=', 'ib.product_id')
                    ->on('p.tenant_id', '=', 'ib.tenant_id');
            })
            ->leftJoin('locations as l', function ($join): void {
                $join->on('l.id', '=', 'ib.location_id')
                    ->on('l.tenant_id', '=', 'ib.tenant_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->where('ib.qty_on_hand', '!=', 0)
            ->select([
                'ib.product_id',
                'ib.product_variant_id',
                'ib.location_id',
                'ib.qty_on_hand',
                'p.name as product_name',
                'p.hpp_average',
                'l.name as location_name',
            ])
            ->get()
            ->map(function ($row): array {
                $qty = (float) $row->qty_on_hand;
                $unitCost = (float) ($row->hpp_average ?? 0);

                return [
                    'balance_type' => 'stock',
                    'reference_type' => 'product',
                    'reference_id' => (string) $row->product_id,
                    'label' => trim((string) ($row->product_name ?? 'Produk').' @ '.(string) ($row->location_name ?? 'Lokasi')),
                    'quantity' => round($qty, 4),
                    'amount' => round($qty * $unitCost, 2),
                    'payload' => [
                        'location_id' => $row->location_id,
                        'product_variant_id' => $row->product_variant_id,
                        'unit_cost' => $unitCost,
                    ],
                ];
            })
            ->all();
    }

    private function supplierDebtSnapshots(string $tenantId): array
    {
        if (! Schema::hasTable('suppliers')) {
            return [];
        }

        return DB::table('suppliers')
            ->where('tenant_id', $tenantId)
            ->where('balance', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'balance'])
            ->map(fn ($supplier): array => [
                'balance_type' => 'supplier_debt',
                'reference_type' => 'supplier',
                'reference_id' => (string) $supplier->id,
                'label' => (string) $supplier->name,
                'amount' => (float) $supplier->balance,
            ])
            ->all();
    }

    private function receivableSnapshots(string $tenantId): array
    {
        if (! Schema::hasTable('receivables')) {
            return [];
        }

        return DB::table('receivables as r')
            ->leftJoin('customers as c', function ($join): void {
                $join->on('c.id', '=', 'r.customer_id')
                    ->on('c.tenant_id', '=', 'r.tenant_id');
            })
            ->where('r.tenant_id', $tenantId)
            ->where('r.remaining_amount', '>', 0)
            ->orderBy('c.name')
            ->get(['r.id', 'r.customer_id', 'c.name as customer_name', 'r.remaining_amount', 'r.due_date', 'r.status'])
            ->map(fn ($receivable): array => [
                'balance_type' => 'receivable',
                'reference_type' => 'receivable',
                'reference_id' => (string) $receivable->id,
                'label' => (string) ($receivable->customer_name ?? 'Piutang'),
                'amount' => (float) $receivable->remaining_amount,
                'payload' => [
                    'customer_id' => $receivable->customer_id,
                    'due_date' => $receivable->due_date,
                    'status' => $receivable->status,
                ],
            ])
            ->all();
    }

    private function cashAccountIds(string $tenantId): array
    {
        $ids = [];

        if (Schema::hasTable('account_mappings')) {
            $mapping = DB::table('account_mappings')
                ->where('tenant_id', $tenantId)
                ->first(['cash_account_id', 'bank_account_id']);
            $ids = array_filter([(string) ($mapping?->cash_account_id ?? ''), (string) ($mapping?->bank_account_id ?? '')]);
        }

        if ($ids === []) {
            $ids = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->where('type', 'asset')
                ->where(function ($query): void {
                    $query->where('name', 'like', '%Kas%')
                        ->orWhere('name', 'like', '%Bank%')
                        ->orWhere('code', 'like', '11%');
                })
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        }

        return array_values(array_unique($ids));
    }

    private function accountBalance(string $tenantId, string $accountId, string $asOfDate, string $type): float
    {
        if (! Schema::hasTable('journal_items') || ! Schema::hasTable('journal_entries')) {
            return 0.0;
        }

        $row = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->selectRaw('COALESCE(SUM(ji.debit), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(ji.credit), 0) as credit_total')
            ->first();

        $debit = (float) ($row->debit_total ?? 0);
        $credit = (float) ($row->credit_total ?? 0);

        return match ($type) {
            'liability', 'equity', 'revenue' => round($credit - $debit, 2),
            default => round($debit - $credit, 2),
        };
    }

    private function tableTotal(string $table, string $tenantId, string $startDate, string $endDate): float
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'total')) {
            return 0.0;
        }

        return round((float) $this->datedQuery($table, $tenantId, $startDate, $endDate)->sum('total'), 2);
    }

    private function tableCount(string $table, string $tenantId, string $startDate, string $endDate): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) $this->datedQuery($table, $tenantId, $startDate, $endDate)->count();
    }

    private function cashTransactionTotal(string $direction, string $tenantId, string $startDate, string $endDate): float
    {
        if (! Schema::hasTable('cash_transactions')) {
            return 0.0;
        }

        return round((float) DB::table('cash_transactions')
            ->where('tenant_id', $tenantId)
            ->where('direction', $direction)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount'), 2);
    }

    private function datedQuery(string $table, string $tenantId, string $startDate, string $endDate)
    {
        $dateColumn = Schema::hasColumn($table, 'transaction_date') ? 'transaction_date' : 'created_at';

        return DB::table($table)
            ->where('tenant_id', $tenantId)
            ->when($dateColumn === 'created_at',
                fn ($query) => $query->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate),
                fn ($query) => $query->whereBetween($dateColumn, [$startDate, $endDate])
            );
    }

    private function audit(string $tenantId, ?string $actorId, string $eventType, string $periodId, array $payload): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'reference_type' => 'lock_period',
            'reference_id' => $periodId,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
