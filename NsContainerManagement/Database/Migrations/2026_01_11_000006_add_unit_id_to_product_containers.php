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
                $blueprint->integer('unit_id')->unsigned()->nullable()->after('product_id');
                $blueprint->foreign('unit_id')->references('id')->on('nexopos_units')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('ns_product_containers') && Schema::hasColumn('ns_product_containers', 'unit_id')) {
            Schema::table('ns_product_containers', function (Blueprint $blueprint) {
                $blueprint->dropForeign(['unit_id']);
                $blueprint->dropColumn('unit_id');
            });
        }
    }
};
