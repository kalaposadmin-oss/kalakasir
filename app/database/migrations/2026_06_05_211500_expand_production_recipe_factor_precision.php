<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyPrecision(8);
    }

    public function down(): void
    {
        $this->applyPrecision(4);
    }

    private function applyPrecision(int $scale): void
    {
        if (! Schema::hasColumn('production_batch_items', 'recipe_factor')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        foreach ($this->statements($driver, $scale) as $statement) {
            DB::statement($statement);
        }
    }

    private function statements(string $driver, int $scale): array
    {
        return match ($driver) {
            'mysql' => [
                "ALTER TABLE production_batch_items MODIFY recipe_factor DECIMAL(15,{$scale}) NULL",
            ],
            'pgsql' => [
                "ALTER TABLE production_batch_items ALTER COLUMN recipe_factor TYPE NUMERIC(15,{$scale})",
            ],
            default => [],
        };
    }
};
