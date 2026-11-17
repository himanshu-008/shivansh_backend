<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Attendance extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'class_name',
        'section',
        'date',
        'status',
        'remarks',
    ];
}
