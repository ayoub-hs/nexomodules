<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only proceed if the table exists (for existing installations)
        if (Schema::hasTable('ns_customer_container_balances')) {
            Schema::table('ns_customer_container_balances', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('ns_customer_container_balances', 'balance')) {
                    $table->integer('balance')->default(0);
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'total_out')) {
                    $table->integer('total_out')->default(0);
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'total_in')) {
                    $table->integer('total_in')->default(0);
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'total_charged')) {
                    $table->integer('total_charged')->default(0);
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'last_movement_at')) {
                    $table->timestamp('last_movement_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op:
        // On fresh installs these columns are created by the base table migration.
        // Dropping them here would corrupt rollback state for installations where this
        // migration did not actually add anything.
    }
};
