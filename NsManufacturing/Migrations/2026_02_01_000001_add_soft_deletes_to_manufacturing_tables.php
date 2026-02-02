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
        if (Schema::hasTable('ns_manufacturing_boms')) {
            Schema::table('ns_manufacturing_boms', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('ns_manufacturing_orders')) {
            Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ns_manufacturing_boms')) {
            Schema::table('ns_manufacturing_boms', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('ns_manufacturing_orders')) {
            Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
