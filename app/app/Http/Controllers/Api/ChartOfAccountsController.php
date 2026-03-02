<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountsController extends Controller
{
    public function __invoke(Request $request)
    {
        $type = $request->query('type');

        $query = ChartOfAccount::query()->select([
            'id',
            'code',
            'name',
            'type',
            'parent_id',
        ]);

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $accounts = $query->orderBy('code')->get();

        $mapping = AccountMapping::query()->first([
            'sale_account_id',
            'cash_account_id',
            'bank_account_id',
            'inventory_account_id',
            'cogs_account_id',
            'payable_account_id',
            'receivable_account_id',
            'expense_adjustment_account_id',
        ]);

        $mappingAccountIds = $mapping
            ? array_values(array_filter((array) $mapping->toArray()))
            : [];

        $mappingAccounts = $mappingAccountIds
            ? ChartOfAccount::query()->whereIn('id', $mappingAccountIds)->get(['id', 'code', 'name'])
            : collect();

        return response()->json([
            'accounts' => $accounts,
            'mapping' => $mapping,
            'mapping_accounts' => $mappingAccounts,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('chart_of_accounts')->where(function ($query) {
                $tenantId = TenantContext::id();
                return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
            })],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'parent_id' => ['nullable', 'string'],
        ]);

        if (!empty($payload['parent_id'])) {
            ChartOfAccount::query()->where('id', $payload['parent_id'])->firstOrFail();
        }

        $account = ChartOfAccount::query()->create([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'type' => $payload['type'],
            'parent_id' => $payload['parent_id'] ?: null,
        ]);

        return response()->json([
            'account' => $account,
        ], 201);
    }

    public function show(ChartOfAccount $account)
    {
        return response()->json([
            'account' => $account->only(['id', 'code', 'name', 'type', 'parent_id']),
        ]);
    }

    public function update(Request $request, ChartOfAccount $account)
    {
        if ($request->has('code') && $request->input('code') !== $account->code) {
            return response()->json([
                'message' => 'Kode akun tidak bisa diubah.',
            ], 422);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string'],
        ]);

        if (!empty($payload['parent_id'])) {
            if ($payload['parent_id'] === (string) $account->id) {
                return response()->json([
                    'message' => 'Kelompok tidak boleh sama dengan akun itu sendiri.',
                ], 422);
            }
            ChartOfAccount::query()->where('id', $payload['parent_id'])->firstOrFail();
        }

        $account->update([
            'name' => $payload['name'],
            'parent_id' => $payload['parent_id'] ?: null,
        ]);

        return response()->json([
            'account' => $account->only(['id', 'code', 'name', 'type', 'parent_id']),
        ]);
    }
}
