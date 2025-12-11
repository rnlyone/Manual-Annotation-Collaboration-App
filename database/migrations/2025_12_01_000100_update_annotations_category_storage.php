<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            if (! Schema::hasColumn('annotations', 'category_ids')) {
                $table->json('category_ids')->nullable()->after('data_id');
            }
        });

        DB::table('annotations')->select(['id', 'category_id'])->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('annotations')->where('id', $row->id)->update([
                    'category_ids' => $row->category_id ? json_encode([(int) $row->category_id]) : json_encode([]),
                ]);
            }
        });

        Schema::table('annotations', function (Blueprint $table) {
            if (Schema::hasColumn('annotations', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            if (! Schema::hasColumn('annotations', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('cascade')->after('data_id');
            }
        });

        DB::table('annotations')->select(['id', 'category_ids'])->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $firstId = null;
                if (! empty($row->category_ids)) {
                    $decoded = json_decode($row->category_ids, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded[0])) {
                        $firstId = (int) $decoded[0];
                    }
                }
                DB::table('annotations')->where('id', $row->id)->update([
                    'category_id' => $firstId,
                ]);
            }
        });

        Schema::table('annotations', function (Blueprint $table) {
            if (Schema::hasColumn('annotations', 'category_ids')) {
                $table->dropColumn('category_ids');
            }
        });
    }
};
