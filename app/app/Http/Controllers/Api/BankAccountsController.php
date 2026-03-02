<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use Illuminate\Support\Collection;

class BankAccountsController extends Controller
{
    public function index()
    {
        [$parent, $accounts] = $this->resolveBankAccounts();

        return response()->json([
            'parent' => $parent ? $parent->only(['id', 'code', 'name']) : null,
            'accounts' => $accounts->map(fn ($account) => $account->only(['id', 'code', 'name', 'parent_id'])),
        ]);
    }

    public function transactions(ChartOfAccount $account)
    {
        [$parent, $accounts] = $this->resolveBankAccounts();
        $validIds = $accounts->pluck('id')->all();

        if (!$validIds && $parent) {
            $validIds = [$parent->id];
        }

        if (!$validIds || ! in_array($account->id, $validIds, true)) {
            return response()->json([
                'message' => 'Akun bank tidak ditemukan.',
            ], 404);
        }

        $transactions = Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('payments.account_id', $account->id)
            ->orderByDesc('sales.created_at')
            ->limit(20)
            ->get([
                'payments.id',
                'payments.amount',
                'payments.method',
                'payments.sale_id',
                'sales.created_at',
                'sales.total',
            ])
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'sale_id' => $payment->sale_id,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'total' => (float) $payment->total,
                    'created_at' => $payment->created_at,
                ];
            });

        return response()->json([
            'account' => $account->only(['id', 'code', 'name']),
            'transactions' => $transactions,
        ]);
    }

    private function resolveBankAccounts(): array
    {
        $parent = ChartOfAccount::query()
            ->where('code', '1102')
            ->whereNull('parent_id')
            ->first();

        if (! $parent) {
            $parent = ChartOfAccount::query()
                ->whereNull('parent_id')
                ->where('name', 'like', '%bank%')
                ->orderBy('code')
                ->first();
        }

        if (! $parent) {
            $mappedId = AccountMapping::query()->value('bank_account_id');
            if ($mappedId) {
                $mapped = ChartOfAccount::query()->find($mappedId);
                if ($mapped) {
                    $parent = $mapped->parent_id
                        ? ChartOfAccount::query()->find($mapped->parent_id)
                        : $mapped;
                }
            }
        }

        if (! $parent) {
            return [null, collect()];
        }

        $accounts = ChartOfAccount::query()
            ->where('parent_id', $parent->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id'])
            ->filter(fn ($account) => preg_match('/^11\d+$/', (string) ($account->code ?? '')));

        if ($accounts->isEmpty()) {
            $accounts = preg_match('/^11\d+$/', (string) ($parent->code ?? ''))
                ? collect([$parent])
                : collect();
        }

        return [$parent, $accounts];
    }
}
