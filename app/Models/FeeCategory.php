<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'type',
        'description',
    ];
}
