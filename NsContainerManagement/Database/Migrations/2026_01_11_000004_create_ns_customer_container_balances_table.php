<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Classes\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::createIfMissing('ns_customer_container_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('container_type_id');
            $table->float('quantity')->default(0);
            $table->timestamps();

            // Shortened index name to avoid MySQL error
            $table->unique(['customer_id', 'container_type_id'], 'ns_ccb_cust_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ns_customer_container_balances');
    }
};
