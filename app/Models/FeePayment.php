<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeePayment extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'fee_category_id',
        'amount',
        'payment_date',
        'payment_method',
        'is_discount',
        'transaction_id',
        'remarks',
        'supplier_name',
        'supplier_contact',
    ];

    public function feeCategory()
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }
}
