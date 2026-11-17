<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ExamMark extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id',
        'exam_term_id',
        'student_id',
        'subject_id',
        'marks_obtained',
        'max_marks',
    ];
}
