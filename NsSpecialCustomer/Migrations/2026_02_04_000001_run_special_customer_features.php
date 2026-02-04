<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $migrationPath = __DIR__ . '/../Database/Migrations/2024_01_01_000001_create_special_customer_features.php';
        if (file_exists($migrationPath)) {
            $migration = require $migrationPath;
            if ($migration instanceof Migration) {
                $migration->up();
            }
        }
    }

    public function down(): void
    {
        $migrationPath = __DIR__ . '/../Database/Migrations/2024_01_01_000001_create_special_customer_features.php';
        if (file_exists($migrationPath)) {
            $migration = require $migrationPath;
            if ($migration instanceof Migration) {
                $migration->down();
            }
        }
    }
};
