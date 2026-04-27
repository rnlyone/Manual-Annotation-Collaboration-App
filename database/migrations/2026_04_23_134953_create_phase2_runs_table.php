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
        Schema::create('phase2_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('phase3_package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, running, completed, failed, cancelled
            $table->unsignedInteger('total_normal')->default(0);
            $table->unsignedInteger('total_non_normal')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('flagged_count')->default(0);
            $table->unsignedInteger('qc_sample_count')->default(0);
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phase2_runs');
    }
};
