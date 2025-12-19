<?php

namespace App\Models;

use App\Models\Data;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnAnnotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'data_id',
        'locked_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function data(): BelongsTo
    {
        return $this->belongsTo(Data::class, 'data_id');
    }
}
