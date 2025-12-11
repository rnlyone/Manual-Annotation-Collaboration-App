<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionLog extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'annotation_datas',
        'ended_at',
    ];

    protected $casts = [
        'annotation_datas' => 'array',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
