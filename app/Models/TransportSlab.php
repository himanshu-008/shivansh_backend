<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportSlab extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'min_km',
        'max_km',
        'monthly_fee',
        'description',
    ];
}
