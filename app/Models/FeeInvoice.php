<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeInvoice extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'fee_category_id',
        'title',
        'amount',
        'due_date',
    ];
}
