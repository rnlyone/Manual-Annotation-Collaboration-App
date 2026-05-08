<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_screenings', function (Blueprint $table) {
            // Human Phase 1 annotation label resolved from annotations.category_ids
            // e.g. "Normal", "Depresi", "Ansietas", "Stres", "Depresi/Ansietas"
            $table->string('phase1_label')->nullable()->after('annotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_screenings', function (Blueprint $table) {
            $table->dropColumn('phase1_label');
        });
    }
};
