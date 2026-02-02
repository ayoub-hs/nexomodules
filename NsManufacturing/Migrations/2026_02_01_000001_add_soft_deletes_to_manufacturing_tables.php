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
                // Only add soft deletes if the column doesn't exist
                if (!Schema::hasColumn('ns_manufacturing_boms', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('ns_manufacturing_orders')) {
            Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
                // Only add soft deletes if the column doesn't exist
                if (!Schema::hasColumn('ns_manufacturing_orders', 'deleted_at')) {
                    $table->softDeletes();
                }
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
                // Only drop soft deletes if the column exists
                if (Schema::hasColumn('ns_manufacturing_boms', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('ns_manufacturing_orders')) {
            Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
                // Only drop soft deletes if the column exists
                if (Schema::hasColumn('ns_manufacturing_orders', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
