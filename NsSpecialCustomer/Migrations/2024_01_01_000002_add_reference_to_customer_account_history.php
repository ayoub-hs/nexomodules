<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add reference column to customer account history table.
 * 
 * This column is used to track the source of transactions
 * (e.g., 'ns_special_topup', 'ns_special_cashback', etc.)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists first (for fresh installs where NexoPOS might not be fully set up)
        if (!Schema::hasTable('nexopos_customers_account_history')) {
            return;
        }

        // Check if column already exists
        if (Schema::hasColumn('nexopos_customers_account_history', 'reference')) {
            return;
        }

        Schema::table('nexopos_customers_account_history', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('description')->comment('Transaction reference for tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('nexopos_customers_account_history')) {
            return;
        }

        if (!Schema::hasColumn('nexopos_customers_account_history', 'reference')) {
            return;
        }

        Schema::table('nexopos_customers_account_history', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
