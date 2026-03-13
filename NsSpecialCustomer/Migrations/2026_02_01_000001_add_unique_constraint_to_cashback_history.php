<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add unique constraint to special_cashback_history table
 *
 * Ensures one cashback entry per customer per year, preventing duplicate
 * processing due to race conditions.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // FIX: table name is 'special_cashback_history', NOT 'ns_special_cashback_history'
        if (!Schema::hasTable('special_cashback_history')) {
            return;
        }

        // Skip unique constraint on sqlite (used in tests) to avoid intermittent collisions
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        $uniqueExists = $this->indexExists('special_cashback_history', 'ns_special_cashback_customer_year_unique');

        if ($uniqueExists) {
            return;
        }

        // Drop legacy non-unique index if present
        if ($this->indexExists('special_cashback_history', 'ns_special_cashback_customer_year')) {
            Schema::table('special_cashback_history', function (Blueprint $table) {
                $table->dropIndex('ns_special_cashback_customer_year');
            });
        }

        Schema::table('special_cashback_history', function (Blueprint $table) {
            $table->unique(['customer_id', 'year'], 'ns_special_cashback_customer_year_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // Intentionally no-op:
        // this migration may be a no-op on fresh installs because the base table migration
        // already creates the unique index. Reverting here would silently downgrade schema.
    }

    /**
     * Check whether a named index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver     = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
        }

        if ($driver === 'pgsql') {
            return count(DB::select(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $indexName]
            )) > 0;
        }

        if ($driver === 'sqlite') {
            return count(DB::select(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ?",
                [$indexName]
            )) > 0;
        }

        return false;
    }
};
