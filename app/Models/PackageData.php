<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageData extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\PackageDataFactory> */
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'data_id',
    ];
}
