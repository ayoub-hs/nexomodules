<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ns_product_containers', function (Blueprint $table) {
            // Drop the old unique index that was too restrictive for unit-specific containers
            $table->dropUnique('ns_product_containers_product_id_container_type_id_unique');
            
            // Add a new unique index per product + unit
            // This allows same container type for different units of the same product
            $table->unique(['product_id', 'unit_id']);
        });
    }

    public function down()
    {
        Schema::table('ns_product_containers', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'unit_id']);
            $table->unique(['product_id', 'container_type_id']);
        });
    }
};
