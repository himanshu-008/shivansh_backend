<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'udise_code',
        'registration_no',
        'contact_email',
        'contact_phone',
        'address',
        'district',
        'state',
        'pincode',
        'board_type',
        'status',
        'logo_url',
        'enabled_modules',
        'is_active',
    ];
}
