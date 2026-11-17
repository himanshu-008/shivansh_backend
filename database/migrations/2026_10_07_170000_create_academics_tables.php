<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('class_name');
            $table->string('section')->nullable();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'leave'])->default('present');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'class_name', 'date']);
            $table->index(['student_id', 'date']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('class_name');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'class_name']);
        });

        Schema::create('exam_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->index('school_id');
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('exam_term_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->decimal('marks_obtained', 8, 2);
            $table->decimal('max_marks', 8, 2)->default(100.00);
            $table->timestamps();

            $table->index(['school_id', 'exam_term_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_terms');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('attendances');
    }
};
