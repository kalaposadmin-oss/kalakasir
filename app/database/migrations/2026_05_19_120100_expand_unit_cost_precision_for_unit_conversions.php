<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyPrecision(6);
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

        $statements = $driver === 'mysql'
            ? $this->mysqlStatements($scale)
            : ($driver === 'pgsql' ? $this->pgsqlStatements($scale) : []);

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    private function mysqlStatements(int $scale): array
    {
        return [
            "ALTER TABLE purchase_items MODIFY price DECIMAL(18,{$scale}) NOT NULL",
            "ALTER TABLE inventory_layers MODIFY cost_per_unit DECIMAL(18,{$scale}) NOT NULL",
            "ALTER TABLE inventory_cost_states MODIFY average_cost DECIMAL(18,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE inventory_cost_states MODIFY last_cost DECIMAL(18,{$scale}) NOT NULL DEFAULT 0",
            "ALTER TABLE stock_ledger MODIFY unit_cost DECIMAL(18,{$scale}) NOT NULL DEFAULT 0",
        ];
    }

    private function pgsqlStatements(int $scale): array
    {
        return [
            "ALTER TABLE purchase_items ALTER COLUMN price TYPE NUMERIC(18,{$scale})",
            "ALTER TABLE inventory_layers ALTER COLUMN cost_per_unit TYPE NUMERIC(18,{$scale})",
            "ALTER TABLE inventory_cost_states ALTER COLUMN average_cost TYPE NUMERIC(18,{$scale})",
            "ALTER TABLE inventory_cost_states ALTER COLUMN last_cost TYPE NUMERIC(18,{$scale})",
            "ALTER TABLE stock_ledger ALTER COLUMN unit_cost TYPE NUMERIC(18,{$scale})",
        ];
    }
};
