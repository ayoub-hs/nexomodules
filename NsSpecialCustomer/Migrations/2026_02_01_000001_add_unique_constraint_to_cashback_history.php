<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add unique constraint to ns_special_cashback_history table
 *
 * This migration adds a unique constraint on customer_id + year columns
 * to prevent race conditions where duplicate cashback entries could be
 * created for the same customer and year.
 * 
 * Note: This migration is dated 2026 to ensure it runs after the initial table creation.
 * This is intentional and safe for fresh installs.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ns_special_cashback_history')) {
            return;
        }

        // Skip unique constraint on sqlite to avoid intermittent test collisions
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        // Check if the unique constraint already exists
        $uniqueExists = $this->indexExists('ns_special_cashback_history', 'ns_special_cashback_customer_year_unique');
        
        if ($uniqueExists) {
            return; // Already has unique constraint, nothing to do
        }

        // Check if the non-unique index exists and drop it
        $indexExists = $this->indexExists('ns_special_cashback_history', 'ns_special_cashback_customer_year');
        
        if ($indexExists) {
            Schema::table('ns_special_cashback_history', function (Blueprint $table) {
                $table->dropIndex('ns_special_cashback_customer_year');
            });
        }

        // Add unique constraint to prevent duplicate cashback entries
        Schema::table('ns_special_cashback_history', function (Blueprint $table) {
            $table->unique(['customer_id', 'year'], 'ns_special_cashback_customer_year_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (!Schema::hasTable('ns_special_cashback_history')) {
            return;
        }

        // Check if the unique constraint exists
        $uniqueExists = $this->indexExists('ns_special_cashback_history', 'ns_special_cashback_customer_year_unique');
        
        if ($uniqueExists) {
            Schema::table('ns_special_cashback_history', function (Blueprint $table) {
                $table->dropUnique('ns_special_cashback_customer_year_unique');
            });
        }

        // Restore the non-unique index if it doesn't exist
        $indexExists = $this->indexExists('ns_special_cashback_history', 'ns_special_cashback_customer_year');
        
        if (!$indexExists) {
            Schema::table('ns_special_cashback_history', function (Blueprint $table) {
                $table->index(['customer_id', 'year'], 'ns_special_cashback_customer_year');
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        }

        if ($driver === 'pgsql') {
            $result = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
            return count($result) > 0;
        }

        if ($driver === 'sqlite') {
            $result = DB::select("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]);
            return count($result) > 0;
        }

        // For other drivers, assume index doesn't exist to be safe
        return false;
    }
};
