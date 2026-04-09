<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        $driver = DB::getDriverName();
        $isPg = $driver === 'pgsql';

        $statements = [
            "CREATE INDEX %s ix_sales_tenant_branch_created_at ON sales (tenant_id, branch_id, created_at)",
            "CREATE INDEX %s ix_sale_items_sale_variant ON sale_items (sale_id, product_variant_id)",
            "CREATE INDEX %s ix_payments_tenant_sale ON payments (tenant_id, sale_id)",
            "CREATE INDEX %s ix_stock_ledger_tenant_location_variant_created_at ON stock_ledger (tenant_id, location_id, product_variant_id, created_at)",
            "CREATE INDEX %s ix_inventory_balances_tenant_location_variant ON inventory_balances (tenant_id, location_id, product_variant_id)",
        ];

        foreach ($statements as $statement) {
            $sql = $isPg ? sprintf($statement, 'IF NOT EXISTS') : sprintf($statement, '');
            try {
                DB::statement($sql);
            } catch (Throwable $exception) {
                // Ignore if index already exists or unsupported statement.
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'ix_sales_tenant_branch_created_at',
            'ix_sale_items_sale_variant',
            'ix_payments_tenant_sale',
            'ix_stock_ledger_tenant_location_variant_created_at',
            'ix_inventory_balances_tenant_location_variant',
        ];

        foreach ($indexes as $index) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$index}");
            } catch (Throwable $exception) {
                // Ignore drop errors.
            }
        }
    }
};
