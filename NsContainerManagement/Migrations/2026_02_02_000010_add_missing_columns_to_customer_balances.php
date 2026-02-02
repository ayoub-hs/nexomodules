<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
                    $table->integer('balance')->default(0)->after('container_type_id');
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'total_out')) {
                    $table->integer('total_out')->default(0)->after('balance');
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'total_in')) {
                    $table->integer('total_in')->default(0)->after('total_out');
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'total_charged')) {
                    $table->integer('total_charged')->default(0)->after('total_in');
                }
                if (!Schema::hasColumn('ns_customer_container_balances', 'last_movement_at')) {
                    $table->timestamp('last_movement_at')->nullable()->after('total_charged');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ns_customer_container_balances')) {
            Schema::table('ns_customer_container_balances', function (Blueprint $table) {
                // Only drop columns if they exist
                $columns = [];
                if (Schema::hasColumn('ns_customer_container_balances', 'balance')) {
                    $columns[] = 'balance';
                }
                if (Schema::hasColumn('ns_customer_container_balances', 'total_out')) {
                    $columns[] = 'total_out';
                }
                if (Schema::hasColumn('ns_customer_container_balances', 'total_in')) {
                    $columns[] = 'total_in';
                }
                if (Schema::hasColumn('ns_customer_container_balances', 'total_charged')) {
                    $columns[] = 'total_charged';
                }
                if (Schema::hasColumn('ns_customer_container_balances', 'last_movement_at')) {
                    $columns[] = 'last_movement_at';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
