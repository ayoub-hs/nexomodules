<?php

/**
 * Table Migration
 **/

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('nexopos_products')) {
            Schema::table('nexopos_products', function (Blueprint $table) {
                $table->boolean('is_manufactured')->default(false)->after('searchable');
                $table->boolean('is_raw_material')->default(false)->after('is_manufactured');
                
                // Add index for better query performance
                $table->index('is_manufactured');
                $table->index('is_raw_material');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('nexopos_products')) {
            Schema::table('nexopos_products', function (Blueprint $table) {
                $table->dropIndex(['is_manufactured']);
                $table->dropIndex(['is_raw_material']);
                $table->dropColumn(['is_manufactured', 'is_raw_material']);
            });
        }
    }
};