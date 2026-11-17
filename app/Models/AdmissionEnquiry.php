<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'enquiry_number',
        'enquiry_date',
        'student_name',
        'gender',
        'dob',
        'class_name',
        'father_name',
        'mother_name',
        'contact_number',
        'alternate_number',
        'email',
        'address',
        'previous_school',
        'reference_source',
        'counselor_name',
        'status',
        'follow_up_date',
        'notes',
        'advance_fee_paid',
        'payment_method',
        'transaction_id',
        'converted_student_id',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function convertedStudent()
    {
        return $this->belongsTo(Student::class, 'converted_student_id');
    }
}
