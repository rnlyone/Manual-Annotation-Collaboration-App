<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annotation extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\AnnotationFactory> */
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'data_id',
        'category_ids',
        'user_id',
        'select_start',
        'select_end',
        'annotated_at',
    ];

    protected $casts = [
        'category_ids' => 'array',
        'annotated_at' => 'integer',
    ];

    protected $attributes = [
        'select_start' => 0,
        'select_end' => 0,
        'category_ids' => '[]',
    ];

    public function data(): BelongsTo
    {
        return $this->belongsTo(Data::class, 'data_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function annotatedPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'annotated_at');
    }
}
