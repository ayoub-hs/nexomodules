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
                // Only add columns if they don't exist
                if (!Schema::hasColumn('nexopos_products', 'is_manufactured')) {
                    $table->boolean('is_manufactured')->default(false)->after('searchable');
                }
                if (!Schema::hasColumn('nexopos_products', 'is_raw_material')) {
                    $table->boolean('is_raw_material')->default(false)->after('is_manufactured');
                }
                
                // Note: Indexes are optional and only needed for performance on large datasets
                // They're not critical for functionality, so we skip creating them if they exist
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
                // Only drop columns if they exist
                $columns = [];
                if (Schema::hasColumn('nexopos_products', 'is_manufactured')) {
                    $columns[] = 'is_manufactured';
                }
                if (Schema::hasColumn('nexopos_products', 'is_raw_material')) {
                    $columns[] = 'is_raw_material';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
