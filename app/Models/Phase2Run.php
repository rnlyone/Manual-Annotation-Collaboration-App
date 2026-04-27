<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phase2Run extends Model
{
    protected $table = 'phase2_runs';

    protected $fillable = [
        'source_package_id',
        'phase3_package_id',
        'status',
        'openai_batch_id',
        'total_normal',
        'total_non_normal',
        'processed',
        'flagged_count',
        'qc_sample_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function sourcePackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'source_package_id');
    }

    public function phase3Package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'phase3_package_id');
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(AiScreening::class, 'phase2_run_id');
    }

    /**
     * Percentage of Normal items processed so far.
     */
    public function progressPercent(): float
    {
        if ($this->total_normal <= 0) {
            return 0.0;
        }

        return round(($this->processed / $this->total_normal) * 100, 1);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['running', 'batch_submitted']);
    }

    public function isBatchSubmitted(): bool
    {
        return $this->status === 'batch_submitted';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canCreatePhase3(): bool
    {
        return $this->status === 'completed' && is_null($this->phase3_package_id);
    }
}
