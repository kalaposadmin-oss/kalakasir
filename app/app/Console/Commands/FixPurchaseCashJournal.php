<?php

namespace App\Console\Commands;

use App\Services\PurchaseCashJournalBackfillService;
use Illuminate\Console\Command;

class FixPurchaseCashJournal extends Command
{
    protected $signature = 'fix:purchase-cash-journal
        {--dry-run : Preview changes without writing to the database}
        {--execute : Apply changes}
        {--tenant_id= : Limit to a single tenant}
        {--from= : Start transaction date}
        {--to= : End transaction date}';

    protected $description = 'Backfill historical cash purchase journals with the canonical correction journal.';

    public function handle(PurchaseCashJournalBackfillService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');

        if ($dryRun && $execute) {
            $this->error('Pilih salah satu: --dry-run atau --execute.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $execute) {
            $dryRun = true;
        }

        $tenantId = trim((string) $this->option('tenant_id'));
        $fromDate = trim((string) $this->option('from'));
        $toDate = trim((string) $this->option('to'));

        $scopeLabel = $tenantId !== '' ? $tenantId : 'ALL';

        $this->info('Scope tenant: '.$scopeLabel);

        if ($fromDate !== '') {
            $this->line('from: '.$fromDate);
        }

        if ($toDate !== '') {
            $this->line('to: '.$toDate);
        }

        $result = $dryRun
            ? $service->preview($tenantId !== '' ? $tenantId : null, $fromDate !== '' ? $fromDate : null, $toDate !== '' ? $toDate : null)
            : $service->execute($tenantId !== '' ? $tenantId : null, $fromDate !== '' ? $fromDate : null, $toDate !== '' ? $toDate : null);

        $summary = $result['summary'];

        $this->line('total_rows: '.$summary['total_rows']);
        $this->line('eligible_rows: '.$summary['eligible_rows']);
        $this->line('eligible_total_value: '.number_format((float) $summary['eligible_total_value'], 2, '.', ''));
        $this->line('already_correct_rows: '.$summary['already_correct_rows']);
        $this->line('already_repaired_rows: '.$summary['already_repaired_rows']);
        $this->line('separate_settlement_rows: '.$summary['separate_settlement_rows']);
        $this->line('missing_cash_account_rows: '.$summary['missing_cash_account_rows']);
        $this->line('ambiguous_purchase_journal_rows: '.$summary['ambiguous_purchase_journal_rows']);
        $this->line('ambiguous_journal_lines_rows: '.$summary['ambiguous_journal_lines_rows']);
        $this->line('amount_mismatch_rows: '.$summary['amount_mismatch_rows']);
        $this->line('not_tunai_rows: '.$summary['not_tunai_rows']);
        $this->line('not_matching_bad_pattern_rows: '.$summary['not_matching_bad_pattern_rows']);

        if ($dryRun) {
            $this->info('Dry run only.');

            return self::SUCCESS;
        }

        $this->line('processed_rows: '.($result['processed'] ?? 0));
        $this->line('failed_rows: '.($result['failed'] ?? 0));

        if ((int) ($result['failed'] ?? 0) > 0) {
            $this->warn('Satu atau lebih transaksi gagal diproses. Semua transaksi yang gagal sudah di-rollback per transaksi.');

            return self::FAILURE;
        }

        $this->info('Backfill selesai.');

        return self::SUCCESS;
    }
}