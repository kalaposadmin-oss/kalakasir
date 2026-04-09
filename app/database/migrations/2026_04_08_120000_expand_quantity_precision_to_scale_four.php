<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyPrecision(4);
    }

    public function down(): void
    {
        $this->applyPrecision(2);
    }

    private function applyPrecision(int $scale): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            foreach ($this->mysqlStatements($scale) as $statement) {
                DB::statement($statement);
            }

            return;
        }

        if ($driver === 'pgsql') {
            foreach ($this->pgsqlStatements($scale) as $statement) {
                DB::statement($statement);
            }
        }
    }

    private function mysqlStatements(int $scale): array
    {
        return [
            "ALTER TABLE inventory_balances MODIFY qty_on_hand DECIMAL(15,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE stock_ledger MODIFY qty_in DECIMAL(15,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE stock_ledger MODIFY qty_out DECIMAL(15,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE stock_ledger MODIFY balance_after DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE inventory_layers MODIFY qty_remaining DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE inventory_layers MODIFY qty_original DECIMAL(15,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE inventory_issue_allocations MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE inventory_cost_states MODIFY qty_on_hand DECIMAL(15,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE cogs_allocations MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE sale_items MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE purchase_items MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE refund_items MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE stock_transfer_items MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE purchase_return_items MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE stock_adjustments MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
            "ALTER TABLE production_batch_items MODIFY qty DECIMAL(15,{$scale}) NOT NULL",
        ];
    }

    private function pgsqlStatements(int $scale): array
    {
        return [
            "ALTER TABLE inventory_balances ALTER COLUMN qty_on_hand TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE stock_ledger ALTER COLUMN qty_in TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE stock_ledger ALTER COLUMN qty_out TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE stock_ledger ALTER COLUMN balance_after TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE inventory_layers ALTER COLUMN qty_remaining TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE inventory_layers ALTER COLUMN qty_original TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE inventory_issue_allocations ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE inventory_cost_states ALTER COLUMN qty_on_hand TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE cogs_allocations ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE sale_items ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE purchase_items ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE refund_items ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE stock_transfer_items ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE purchase_return_items ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE stock_adjustments ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
            "ALTER TABLE production_batch_items ALTER COLUMN qty TYPE NUMERIC(15,{$scale})",
        ];
    }
};