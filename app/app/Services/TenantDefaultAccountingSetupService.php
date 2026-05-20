<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDefaultAccountingSetupService
{
    public function ensure(string $tenantId, ?string $actorId = null): void
    {
        $definitions = [
            '1101' => ['name' => 'Kas', 'type' => 'asset'],
            '1102' => ['name' => 'Bank', 'type' => 'asset'],
            '1201' => ['name' => 'Piutang Usaha', 'type' => 'asset'],
            '1301' => ['name' => 'Persediaan Barang', 'type' => 'asset'],
            '1501' => ['name' => 'Aset Tetap - Peralatan', 'type' => 'asset'],
            '1502' => ['name' => 'Akumulasi Depresiasi Peralatan', 'type' => 'asset'],
            '2101' => ['name' => 'Hutang Usaha', 'type' => 'liability'],
            '3101' => ['name' => 'Modal Pemilik', 'type' => 'equity'],
            '4101' => ['name' => 'Penjualan', 'type' => 'revenue'],
            '5101' => ['name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
            '6101' => ['name' => 'Penyesuaian Beban', 'type' => 'expense'],
            '6201' => ['name' => 'Beban Depresiasi Peralatan', 'type' => 'expense'],
        ];

        foreach ($definitions as $code => $meta) {
            $exists = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'code' => $code,
                    'name' => $meta['name'],
                    'type' => $meta['type'],
                    'parent_id' => null,
                ]);
            }
        }

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenantId)->first();

        $resolvedIds = [
            'sale_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '4101')->value('id'),
            'cash_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1101')->value('id'),
            'bank_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1102')->value('id'),
            'inventory_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1301')->value('id'),
            'cogs_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '5101')->value('id'),
            'payable_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '2101')->value('id'),
            'receivable_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1201')->value('id'),
            'expense_adjustment_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '6101')->value('id'),
        ];

        if (! $mapping) {
            DB::table('account_mappings')->insert(array_merge([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ], $resolvedIds));

            return;
        }

        $patch = ['updated_by' => $actorId ?: $mapping->updated_by];
        foreach ($resolvedIds as $column => $value) {
            if (! $mapping->{$column}) {
                $patch[$column] = $value;
            }
        }

        if (count($patch) > 1) {
            DB::table('account_mappings')
                ->where('tenant_id', $tenantId)
                ->update($patch);
        }
    }
}
