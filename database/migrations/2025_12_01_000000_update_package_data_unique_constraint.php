<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_data', function (Blueprint $table) {
            if (! Schema::hasTable('package_data')) {
                return;
            }

            if ($this->indexExists('package_data', 'package_data_data_id_unique')) {
                $table->dropUnique('package_data_data_id_unique');
            }

            if (! $this->indexExists('package_data', 'package_data_package_id_data_id_unique')) {
                $table->unique(['package_id', 'data_id'], 'package_data_package_id_data_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('package_data', function (Blueprint $table) {
            if (! Schema::hasTable('package_data')) {
                return;
            }

            if ($this->indexExists('package_data', 'package_data_package_id_data_id_unique')) {
                $table->dropUnique('package_data_package_id_data_id_unique');
            }

            if (! $this->indexExists('package_data', 'package_data_data_id_unique')) {
                $table->unique('data_id', 'package_data_data_id_unique');
            }
        });
    }
    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'select count(*) as aggregate from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index]
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
