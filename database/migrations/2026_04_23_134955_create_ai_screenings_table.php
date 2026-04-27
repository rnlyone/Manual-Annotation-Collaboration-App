<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase2_run_id')->constrained('phase2_runs')->cascadeOnDelete();
            $table->uuid('data_id')->index();
            $table->foreign('data_id')->references('id')->on('data')->cascadeOnDelete();
            $table->foreignId('annotation_id')->nullable()->constrained('annotations')->nullOnDelete();
            $table->string('llm_label')->nullable();   // Normal / Depresi / Ansietas / Stres
            $table->float('confidence')->nullable();
            $table->text('reasoning')->nullable();
            $table->boolean('flagged')->default(false);     // LLM thinks it is DAS
            $table->boolean('in_qc_sample')->default(false); // in 10% random QC sample
            $table->string('status')->default('pending'); // pending, done, error
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_screenings');
    }
};
