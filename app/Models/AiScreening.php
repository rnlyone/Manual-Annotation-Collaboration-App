<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiScreening extends Model
{
    protected $table = 'ai_screenings';

    protected $fillable = [
        'phase2_run_id',
        'data_id',
        'annotation_id',
        'phase1_label',
        'llm_label',
        'confidence',
        'reasoning',
        'flagged',
        'in_qc_sample',
        'status',
        'error_message',
    ];

    protected $casts = [
        'flagged'     => 'boolean',
        'in_qc_sample' => 'boolean',
        'confidence'  => 'float',
    ];

    public function phase2Run(): BelongsTo
    {
        return $this->belongsTo(Phase2Run::class, 'phase2_run_id');
    }

    public function data(): BelongsTo
    {
        return $this->belongsTo(Data::class, 'data_id');
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class, 'annotation_id');
    }
}
