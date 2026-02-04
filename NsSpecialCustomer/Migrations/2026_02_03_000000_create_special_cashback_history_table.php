<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('special_cashback_history')) {
            Schema::create('special_cashback_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('customer_id');
                $table->integer('year');
                $table->decimal('total_purchases', 18, 5)->default(0);
                $table->decimal('total_refunds', 18, 5)->default(0);
                $table->decimal('cashback_percentage', 5, 2)->default(0);
                $table->decimal('cashback_amount', 18, 5)->default(0);
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_reason')->nullable();
                $table->unsignedBigInteger('reversal_transaction_id')->nullable();
                $table->unsignedBigInteger('reversal_author')->nullable();
                $table->unsignedBigInteger('author')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('special_cashback_history');
    }
};
