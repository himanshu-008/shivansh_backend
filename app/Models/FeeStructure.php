<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'fee_category_id',
        'class_name',
        'amount',
        'frequency',
    ];

    public function feeCategory()
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
