<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_manufacturing_bom_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('quantity', 10, 4)->default(0);
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->decimal('cost_allocation', 5, 2)->default(100);
            $table->unsignedBigInteger('author')->nullable();
            $table->timestamps();

            // Indexes for performance (no foreign key constraints)
            $table->index('bom_id');
            $table->index('product_id');
            $table->index('unit_id');
            $table->index('author');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_manufacturing_bom_items');
    }
};
