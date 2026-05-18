<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountsController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenantId = TenantContext::id();
        $type = $request->query('type');

        $query = ChartOfAccount::query()
            ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->select([
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

        $mapping = AccountMapping::query()
            ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->first([
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
            ? ChartOfAccount::query()
                ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId))
                ->whereIn('id', $mappingAccountIds)
                ->get(['id', 'code', 'name'])
            : collect();

        return response()->json([
            'accounts' => $accounts,
            'mapping' => $mapping,
            'mapping_accounts' => $mappingAccounts,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('chart_of_accounts')->where(function ($query) {
                $tenantId = TenantContext::id();
                return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
            })],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'parent_id' => [
                'nullable',
                'uuid',
                $tenantId
                    ? Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)
                    : 'exists:chart_of_accounts,id',
            ],
        ]);

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
        $tenantId = TenantContext::id();

        if ($request->has('code') && $request->input('code') !== $account->code) {
            return response()->json([
                'message' => 'Kode akun tidak bisa diubah.',
            ], 422);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'uuid',
                $tenantId
                    ? Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)
                    : 'exists:chart_of_accounts,id',
            ],
        ]);

        if (!empty($payload['parent_id'])) {
            if ($payload['parent_id'] === (string) $account->id) {
                return response()->json([
                    'message' => 'Kelompok tidak boleh sama dengan akun itu sendiri.',
                ], 422);
            }
        }

        $account->update([
            'name' => $payload['name'],
            'parent_id' => $payload['parent_id'] ?: null,
        ]);

        return response()->json([
            'account' => $account->only(['id', 'code', 'name', 'type', 'parent_id']),
        ]);
    }

    public function updateMapping(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $payload = $request->validate([
            'sale_account_id' => ['required', 'uuid'],
            'cash_account_id' => ['required', 'uuid'],
            'bank_account_id' => ['required', 'uuid'],
            'inventory_account_id' => ['required', 'uuid'],
            'cogs_account_id' => ['required', 'uuid'],
            'payable_account_id' => ['required', 'uuid'],
            'receivable_account_id' => ['required', 'uuid'],
            'expense_adjustment_account_id' => ['required', 'uuid'],
        ]);

        $resolved = [
            'sale_account_id' => $this->resolveMappedAccount($tenantId, $payload['sale_account_id'], 'revenue', 'sale_account_id'),
            'cash_account_id' => $this->resolveMappedAccount($tenantId, $payload['cash_account_id'], 'asset', 'cash_account_id'),
            'bank_account_id' => $this->resolveMappedAccount($tenantId, $payload['bank_account_id'], 'asset', 'bank_account_id'),
            'inventory_account_id' => $this->resolveMappedAccount($tenantId, $payload['inventory_account_id'], 'asset', 'inventory_account_id'),
            'cogs_account_id' => $this->resolveMappedAccount($tenantId, $payload['cogs_account_id'], 'expense', 'cogs_account_id'),
            'payable_account_id' => $this->resolveMappedAccount($tenantId, $payload['payable_account_id'], 'liability', 'payable_account_id'),
            'receivable_account_id' => $this->resolveMappedAccount($tenantId, $payload['receivable_account_id'], 'asset', 'receivable_account_id'),
            'expense_adjustment_account_id' => $this->resolveMappedAccount($tenantId, $payload['expense_adjustment_account_id'], 'expense', 'expense_adjustment_account_id'),
        ];

        $distinct = collect($resolved)->values()->unique();
        if ($distinct->count() !== count($resolved)) {
            return response()->json([
                'message' => 'Setiap mapping akun wajib menunjuk akun yang berbeda.',
                'errors' => [
                    'mapping' => ['Mapping akun duplikat tidak diizinkan.'],
                ],
            ], 422);
        }

        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->first();
        $actorId = (string) optional($request->user())->id;

        if (! $mapping) {
            $mapping = AccountMapping::query()->create(array_merge([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'created_by' => $actorId !== '' ? $actorId : null,
                'updated_by' => $actorId !== '' ? $actorId : null,
            ], $resolved));
        } else {
            $mapping->forceFill(array_merge($resolved, [
                'updated_by' => $actorId !== '' ? $actorId : $mapping->updated_by,
            ]))->save();
            $mapping->refresh();
        }

        return response()->json([
            'mapping' => $mapping,
        ]);
    }

    private function resolveMappedAccount(string $tenantId, string $accountId, string $expectedType, string $field): string
    {
        $account = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                $field => ['Akun tidak ditemukan untuk tenant aktif.'],
            ]);
        }

        if ((string) $account->type !== $expectedType) {
            throw ValidationException::withMessages([
                $field => ['Akun wajib bertipe '.$expectedType.'.'],
            ]);
        }

        return (string) $account->id;
    }
}
