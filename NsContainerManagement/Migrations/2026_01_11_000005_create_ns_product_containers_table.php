<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_product_containers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_quantity_id')->nullable(); // the specific selling unit
            $table->unsignedBigInteger('unit_id')->nullable();          // raw unit, kept for reference
            $table->unsignedBigInteger('container_type_id');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('product_id');
            $table->index('unit_quantity_id');
            $table->index('unit_id');
            $table->index('container_type_id');

            // FIX: unique per product+unit, NOT per product+container_type.
            // A product can have the same container type on different units,
            // but each unit can only be linked to ONE container type.
            $table->unique(['product_id', 'unit_quantity_id'], 'ns_pc_product_unit_qty_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_product_containers');
    }
};
