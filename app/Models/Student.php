<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'name',
        'admission_number',
        'roll_number',
        'class_name',
        'section',
        'father_name',
        'mother_name',
        'dob',
        'gender',
        'category',
        'religion',
        'address',
        'aadhar_number',
        'email',
        'contact_number',
        'student_pen',
        'student_nic_id',
        'apaar_id',
        'bpl_beneficiary',
        'is_rte',
        'status',
    ];

    protected $casts = [
        'is_rte' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
