<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('class_name');
            $table->string('section')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->integer('total_working_days')->default(0);
            $table->integer('attended_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('half_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'year', 'month'], 'uniq_school_std_yr_mo');
            $table->index(['school_id', 'class_name', 'year', 'month'], 'idx_school_cls_yr_mo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_attendances');
    }
};
