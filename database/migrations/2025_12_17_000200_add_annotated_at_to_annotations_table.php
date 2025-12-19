<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            if (! Schema::hasColumn('annotations', 'annotated_at')) {
                $table->foreignId('annotated_at')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('packages')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            if (Schema::hasColumn('annotations', 'annotated_at')) {
                $table->dropForeign(['annotated_at']);
                $table->dropColumn('annotated_at');
            }
        });
    }
};
