<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_container_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_type_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('direction', ['out', 'in', 'charge', 'adjustment']);
            $table->integer('quantity');
            $table->decimal('unit_deposit_fee', 18, 5)->default(0);
            $table->decimal('total_deposit_value', 18, 5)->default(0);
            $table->string('source_type', 50);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->integer('author');
            $table->timestamps();

            $table->foreign('container_type_id')
                ->references('id')
                ->on('ns_container_types')
                ->onDelete('cascade');

            $table->index(['customer_id', 'container_type_id']);
            $table->index('direction');
            $table->index('created_at');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_container_movements');
    }
};
