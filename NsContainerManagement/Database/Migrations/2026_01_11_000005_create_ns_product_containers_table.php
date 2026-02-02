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
            $table->unsignedBigInteger('container_type_id');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('container_type_id')
                ->references('id')
                ->on('ns_container_types')
                ->onDelete('cascade');

            $table->unique(['product_id', 'container_type_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_product_containers');
    }
};
