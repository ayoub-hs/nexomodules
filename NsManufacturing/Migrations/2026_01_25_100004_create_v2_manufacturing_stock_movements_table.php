<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_manufacturing_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('quantity', 10, 4)->default(0);
            $table->enum('type', ['consumption', 'production']);
            $table->decimal('cost_at_time', 15, 4)->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('order_id')
                ->references('id')
                ->on('ns_manufacturing_orders')
                ->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('nexopos_products')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('nexopos_units')->onDelete('set null');

            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index('unit_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('ns_manufacturing_stock_movements', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['unit_id']);

            // Drop indexes
            $table->dropIndex(['order_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::dropIfExists('ns_manufacturing_stock_movements');
    }
};
