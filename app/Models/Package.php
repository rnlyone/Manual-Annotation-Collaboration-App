<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\PackageFactory> */
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Data assignments linked to this package.
     */
    public function dataAssignments(): HasMany
    {
        return $this->hasMany(PackageData::class);
    }

    /**
     * User assignments linked to this package.
     */
    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserPackage::class);
    }
}
