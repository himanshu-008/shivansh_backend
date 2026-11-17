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
        Schema::create('transfer_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('tc_number')->nullable(); // RajPSP TC No. or School TC No.
            $table->date('application_date')->nullable();
            $table->date('issue_date');
            $table->string('reason')->nullable();
            $table->string('conduct')->default('Good');
            $table->string('last_class_studied')->nullable();
            $table->string('qualified_for_promotion')->nullable();
            $table->integer('total_working_days')->default(0);
            $table->integer('attended_days')->default(0);
            $table->boolean('dues_cleared')->default(true);
            $table->decimal('pending_dues_amount', 10, 2)->default(0.00);
            $table->string('rajpsp_file_path')->nullable(); // Stored PDF/Image from RajPSP
            $table->string('rajpsp_file_name')->nullable();
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
        Schema::dropIfExists('transfer_certificates');
    }
};
