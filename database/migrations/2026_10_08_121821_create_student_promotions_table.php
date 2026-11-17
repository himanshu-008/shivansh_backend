<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('from_session_id')->constrained('academic_sessions')->onDelete('cascade');
            $table->foreignId('to_session_id')->constrained('academic_sessions')->onDelete('cascade');
            $table->string('from_class');
            $table->string('from_section')->nullable();
            $table->string('to_class');
            $table->string('to_section')->nullable();
            $table->string('action'); // 'promoted', 'retained', 'passed_out', 'tc_issued'
            $table->date('promotion_date');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
