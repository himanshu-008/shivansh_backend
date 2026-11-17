<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ExamTermSubject extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id',
        'exam_term_id',
        'subject_id',
        'max_marks',
    ];

    public function examTerm()
    {
        return $this->belongsTo(ExamTerm::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
