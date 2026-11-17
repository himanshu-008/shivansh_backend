<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'staff_id',
        'month',
        'year',
        'total_days',
        'active_working_days',
        'present_days',
        'paid_leaves',
        'holidays',
        'vacation_days',
        'unpaid_vacation_days',
        'unpaid_absent_days',
        'payable_days',
        'base_salary',
        'allowances',
        'gross_salary',
        'deductions',
        'net_salary',
        'status',
        'payment_date',
        'payment_method',
        'transaction_ref',
        'expense_id',
        'remarks',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'present_days' => 'float',
        'paid_leaves' => 'float',
        'holidays' => 'float',
        'vacation_days' => 'float',
        'unpaid_vacation_days' => 'float',
        'unpaid_absent_days' => 'float',
        'payable_days' => 'float',
        'base_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
