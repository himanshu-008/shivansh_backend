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
        Schema::create('school_holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('title');
            $table->enum('type', ['festival_holiday', 'summer_vacation', 'winter_break', 'gazetted_holiday', 'other'])->default('festival_holiday');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_session_id']);
            $table->index(['school_id', 'start_date', 'end_date']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('employee_code');
            $table->string('name');
            $table->enum('gender', ['male', 'female', 'other'])->default('female');
            $table->date('dob')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('aadhaar_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->text('photo_url')->nullable();
            $table->string('designation')->default('Teacher');
            $table->string('department')->nullable()->default('Teaching');
            $table->string('qualification')->nullable();
            $table->date('joining_date')->nullable();
            $table->enum('status', ['active', 'resigned', 'on_leave'])->default('active');
            
            // Bank details
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('upi_id')->nullable();

            // Salary Structure
            $table->decimal('base_salary', 10, 2)->default(0.00);
            $table->decimal('hra', 10, 2)->default(0.00);
            $table->decimal('da', 10, 2)->default(0.00);
            $table->decimal('allowances', 10, 2)->default(0.00);
            $table->decimal('pf_deduction', 10, 2)->default(0.00);
            $table->decimal('tds_deduction', 10, 2)->default(0.00);
            $table->decimal('other_deductions', 10, 2)->default(0.00);

            // Vacation Pay Policy
            $table->enum('vacation_pay_policy', ['full_pay_12_months', 'unpaid_vacation', 'half_pay'])->default('full_pay_12_months');

            $table->timestamps();

            $table->unique(['school_id', 'employee_code']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'designation']);
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'half_day', 'paid_leave', 'holiday', 'vacation'])->default('present');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'staff_id', 'date']);
            $table->index(['school_id', 'date']);
            $table->index(['staff_id', 'date']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedSmallInteger('month'); // 1 - 12
            $table->unsignedSmallInteger('year');  // e.g. 2026
            
            // Days computation
            $table->unsignedSmallInteger('total_days')->default(30);
            $table->unsignedSmallInteger('active_working_days')->default(30);
            $table->decimal('present_days', 5, 1)->default(0.0);
            $table->decimal('paid_leaves', 5, 1)->default(0.0);
            $table->decimal('holidays', 5, 1)->default(0.0);
            $table->decimal('vacation_days', 5, 1)->default(0.0);
            $table->decimal('unpaid_vacation_days', 5, 1)->default(0.0);
            $table->decimal('unpaid_absent_days', 5, 1)->default(0.0);
            $table->decimal('payable_days', 5, 1)->default(0.0);

            // Salary amounts
            $table->decimal('base_salary', 10, 2)->default(0.00);
            $table->decimal('allowances', 10, 2)->default(0.00);
            $table->decimal('gross_salary', 10, 2)->default(0.00);
            $table->decimal('deductions', 10, 2)->default(0.00);
            $table->decimal('net_salary', 10, 2)->default(0.00);

            // Payment tracking
            $table->enum('status', ['draft', 'processed', 'paid'])->default('processed');
            $table->date('payment_date')->nullable();
            $table->enum('payment_method', ['bank_transfer', 'cash', 'cheque', 'upi'])->default('bank_transfer');
            $table->string('transaction_ref')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'staff_id', 'month', 'year']);
            $table->index(['school_id', 'academic_session_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('school_holidays');
    }
};
