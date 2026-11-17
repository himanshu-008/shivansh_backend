<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['school_id', 'student_id', 'date'], 'att_school_student_date_unique');
        });

        Schema::table('exam_marks', function (Blueprint $table) {
            $table->unique(['school_id', 'exam_term_id', 'student_id', 'subject_id'], 'exam_marks_composite_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('att_school_student_date_unique');
        });

        Schema::table('exam_marks', function (Blueprint $table) {
            $table->dropUnique('exam_marks_composite_unique');
        });
    }
};
