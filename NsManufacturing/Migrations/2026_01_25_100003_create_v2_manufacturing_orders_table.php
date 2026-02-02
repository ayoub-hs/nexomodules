<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing('ns_manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('quantity', 10, 4)->default(0);
            $table->enum('status', ['draft', 'planned', 'in_progress', 'completed', 'cancelled', 'on_hold'])->default('draft');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('author');
            $table->timestamps();

            // Foreign keys
            $table->foreign('bom_id')
                ->references('id')
                ->on('ns_manufacturing_boms')
                ->onDelete('set null');
            $table->foreign('author')->references('id')->on('nexopos_users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('nexopos_products')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('nexopos_units')->onDelete('set null');

            // Indexes
            $table->index('product_id');
            $table->index('status');
            $table->index('unit_id');
            $table->index('author');
            $table->index('started_at');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['bom_id']);
            $table->dropForeign(['author']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['unit_id']);

            // Drop indexes
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['author']);
            $table->dropIndex(['started_at']);
            $table->dropIndex(['completed_at']);
        });

        Schema::dropIfExists('ns_manufacturing_orders');
    }
};
