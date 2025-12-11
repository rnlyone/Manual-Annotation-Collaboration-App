<?php

namespace App\Models;

use App\Models\PackageData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Data extends Model
{
    /** @use HasFactory<\Database\Factories\DataFactory> */
    use HasFactory;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'content',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    /**
     * Relationship to package assignments.
     */
    public function packageAssignments(): HasMany
    {
        return $this->hasMany(PackageData::class, 'data_id');
    }

}
