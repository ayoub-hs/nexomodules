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

            // Indexes for performance (no foreign key constraints)
            $table->index('order_id');
            $table->index('product_id');
            $table->index('unit_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_manufacturing_stock_movements');
    }
};
