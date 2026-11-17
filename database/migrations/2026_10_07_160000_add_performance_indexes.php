<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index('school_id');
            $table->index('class_name');
            $table->index('admission_number');
        });

        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->index('school_id');
            $table->index('student_id');
            $table->index('fee_category_id');
            $table->index('academic_session_id');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->index('school_id');
            $table->index('student_id');
            $table->index('fee_category_id');
            $table->index('academic_session_id');
            $table->index('is_discount');
        });

        Schema::table('transport_slabs', function (Blueprint $table) {
            $table->index('school_id');
        });

        Schema::table('book_sets', function (Blueprint $table) {
            $table->index('school_id');
            $table->index('class_name');
        });

        Schema::table('dress_items', function (Blueprint $table) {
            $table->index('school_id');
            $table->index('class_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
            $table->dropIndex(['class_name']);
            $table->dropIndex(['admission_number']);
        });

        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
            $table->dropIndex(['student_id']);
            $table->dropIndex(['fee_category_id']);
            $table->dropIndex(['academic_session_id']);
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
            $table->dropIndex(['student_id']);
            $table->dropIndex(['fee_category_id']);
            $table->dropIndex(['academic_session_id']);
            $table->dropIndex(['is_discount']);
        });

        Schema::table('transport_slabs', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
        });

        Schema::table('book_sets', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
            $table->dropIndex(['class_name']);
        });

        Schema::table('dress_items', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
            $table->dropIndex(['class_name']);
        });
    }
};
