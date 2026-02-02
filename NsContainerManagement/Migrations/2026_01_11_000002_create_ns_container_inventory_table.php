<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_container_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_type_id')->unique();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->timestamp('last_adjustment_date')->nullable();
            $table->integer('last_adjustment_by')->nullable();
            $table->string('last_adjustment_reason', 255)->nullable();
            $table->timestamps();
            
            // Use index instead of foreign key for flexibility
            $table->index('container_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_container_inventory');
    }
};
