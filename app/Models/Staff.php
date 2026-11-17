<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'school_id',
        'employee_code',
        'name',
        'gender',
        'dob',
        'phone',
        'email',
        'address',
        'aadhaar_number',
        'pan_number',
        'photo_url',
        'designation',
        'department',
        'qualification',
        'joining_date',
        'status',
        'bank_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'base_salary',
        'hra',
        'da',
        'allowances',
        'pf_deduction',
        'tds_deduction',
        'other_deductions',
        'vacation_pay_policy',
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
        'base_salary' => 'decimal:2',
        'hra' => 'decimal:2',
        'da' => 'decimal:2',
        'allowances' => 'decimal:2',
        'pf_deduction' => 'decimal:2',
        'tds_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    /**
     * Compute total gross salary (Base + HRA + DA + Allowances)
     */
    public function getGrossSalaryAttribute()
    {
        return (float)($this->base_salary + $this->hra + $this->da + $this->allowances);
    }

    /**
     * Compute total standard monthly deductions (PF + TDS + Other)
     */
    public function getTotalDeductionsAttribute()
    {
        return (float)($this->pf_deduction + $this->tds_deduction + $this->other_deductions);
    }
}
