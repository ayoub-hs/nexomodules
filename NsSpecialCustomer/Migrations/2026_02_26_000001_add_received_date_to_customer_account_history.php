<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nexopos_customers_account_history')) {
            return;
        }

        if (Schema::hasColumn('nexopos_customers_account_history', 'received_date')) {
            return;
        }

        Schema::table('nexopos_customers_account_history', function (Blueprint $table) {
            $table->date('received_date')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('nexopos_customers_account_history')) {
            return;
        }

        if (!Schema::hasColumn('nexopos_customers_account_history', 'received_date')) {
            return;
        }

        Schema::table('nexopos_customers_account_history', function (Blueprint $table) {
            $table->dropColumn('received_date');
        });
    }
};
