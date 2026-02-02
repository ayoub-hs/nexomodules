<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_manufacturing_boms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('quantity', 10, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('author');
            $table->timestamps();

            // Foreign keys
            $table->foreign('author')->references('id')->on('nexopos_users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('nexopos_products')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('nexopos_units')->onDelete('set null');

            // Indexes
            $table->index('product_id');
            $table->index('author');
            $table->index('unit_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('ns_manufacturing_boms', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['author']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['unit_id']);

            // Drop indexes
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['is_active']);
        });

        Schema::dropIfExists('ns_manufacturing_boms');
    }
};
