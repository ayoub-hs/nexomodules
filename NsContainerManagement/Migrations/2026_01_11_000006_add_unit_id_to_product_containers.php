<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ns_product_containers') && !Schema::hasColumn('ns_product_containers', 'unit_id')) {
            Schema::table('ns_product_containers', function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('unit_id')->nullable()->after('product_id');
                // Use index instead of foreign key for flexibility
                $blueprint->index('unit_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('ns_product_containers') && Schema::hasColumn('ns_product_containers', 'unit_id')) {
            Schema::table('ns_product_containers', function (Blueprint $blueprint) {
                $blueprint->dropColumn('unit_id');
            });
        }
    }
};
