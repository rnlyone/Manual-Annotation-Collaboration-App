<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    protected $appends = [
        'duration_minutes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function getDurationMinutesAttribute(): float
    {
        $window = $this->sessionWindow();

        if (! $window) {
            return 0.0;
        }

        [$start, $end] = $window;
        $seconds = $end->diffInSeconds($start, true);

        return $seconds === 0 ? 0.0 : round($seconds / 60, 1);
    }

    public function sessionWindow(): ?array
    {
        $start = $this->asCarbon($this->created_at);

        if (! $start) {
            return null;
        }

        $end = $this->asCarbon($this->ended_at) ?? $this->asCarbon($this->updated_at);

        if (! $end || $end->lt($start)) {
            $end = $start->copy();
        }

        return [$start->copy(), $end];
    }

    protected function asCarbon($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        return $value ? Carbon::parse($value) : null;
    }
}
