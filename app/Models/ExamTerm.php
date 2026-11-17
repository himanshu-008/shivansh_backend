<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ExamTerm extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'name',
    ];
}
