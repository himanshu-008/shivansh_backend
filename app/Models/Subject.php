<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Subject extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id',
        'class_name',
        'name',
        'code',
    ];
}
