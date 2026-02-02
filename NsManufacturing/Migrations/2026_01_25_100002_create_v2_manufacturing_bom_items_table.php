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

            // Foreign keys
            $table->foreign('bom_id')
                ->references('id')
                ->on('ns_manufacturing_boms')
                ->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('nexopos_products')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('nexopos_units')->onDelete('set null');
            $table->foreign('author')->references('id')->on('nexopos_users')->onDelete('set null');

            // Indexes
            $table->index('bom_id');
            $table->index('product_id');
            $table->index('unit_id');
            $table->index('author');
        });
    }

    public function down(): void
    {
        Schema::table('ns_manufacturing_bom_items', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['bom_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['author']);

            // Drop indexes
            $table->dropIndex(['bom_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['author']);
        });

        Schema::dropIfExists('ns_manufacturing_bom_items');
    }
};
