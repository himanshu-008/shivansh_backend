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
        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('enquiry_number', 100);
            $table->date('enquiry_date');
            $table->string('student_name', 255);
            $table->string('gender', 20)->nullable();
            $table->date('dob')->nullable();
            $table->string('class_name', 50);
            $table->string('father_name', 255)->nullable();
            $table->string('mother_name', 255)->nullable();
            $table->string('contact_number', 50);
            $table->string('alternate_number', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('previous_school', 255)->nullable();
            $table->string('reference_source', 100)->nullable();
            $table->string('counselor_name', 255)->nullable();
            $table->string('status', 50)->default('enquired');
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('advance_fee_paid', 10, 2)->default(0.00);
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->foreignId('converted_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'academic_session_id']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'class_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_enquiries');
    }
};
