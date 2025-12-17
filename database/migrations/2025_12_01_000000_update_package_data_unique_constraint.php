<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_data', function (Blueprint $table) {
            if (Schema::hasTable('package_data')) {
                $table->dropUnique('package_data_data_id_unique');
                $table->unique(['package_id', 'data_id'], 'package_data_package_id_data_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('package_data', function (Blueprint $table) {
            if (Schema::hasTable('package_data')) {
                $table->dropUnique('package_data_package_id_data_id_unique');
                $table->unique('data_id', 'package_data_data_id_unique');
            }
        });
    }
};
