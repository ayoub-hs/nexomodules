<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_container_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('capacity', 10, 3)->default(1.000);
            $table->string('capacity_unit', 20)->default('L');
            $table->decimal('deposit_fee', 18, 5)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('author')->nullable();
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ns_container_types');
    }
};
