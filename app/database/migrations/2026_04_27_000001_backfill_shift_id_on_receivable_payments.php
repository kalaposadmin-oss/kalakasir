<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill shift_id for existing receivable_payments that have no shift_id.
        // We match each payment to a shift by finding the open/closed shift that was active
        // at the time the payment was created, for the same tenant.
            DB::table('receivable_payments')
                ->whereNull('shift_id')
                ->orderBy('created_at')
                ->get(['id', 'tenant_id', 'created_at'])
                ->each(function (object $payment): void {
                    $shiftId = DB::table('shifts')
                        ->where('tenant_id', $payment->tenant_id)
                        ->where('opened_at', '<=', $payment->created_at)
                        ->where(function ($query) use ($payment): void {
                            $query->whereNull('closed_at')
                                ->orWhere('closed_at', '>=', $payment->created_at);
                        })
                        ->orderByDesc('opened_at')
                        ->value('id');

                    if (! $shiftId) {
                        return;
                    }

                    DB::table('receivable_payments')
                        ->where('id', $payment->id)
                        ->update(['shift_id' => $shiftId]);
                });
    }

    public function down(): void
    {
        // No rollback needed — the column itself is handled by the previous migration.
    }
};
