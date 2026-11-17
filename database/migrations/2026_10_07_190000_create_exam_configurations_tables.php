<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_term_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('exam_term_id');
            $table->unsignedBigInteger('subject_id');
            $table->decimal('max_marks', 8, 2)->default(100.00);
            $table->timestamps();

            $table->unique(['school_id', 'exam_term_id', 'subject_id'], 'school_term_subject_unique');
        });

        Schema::create('exam_aggregate_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('class_name')->default('All Classes');
            $table->string('name')->default('Final Annual Result');
            $table->json('included_term_ids'); // e.g. [1, 2, 3, 4]
            $table->json('term_weightages')->nullable(); // e.g. {"1": 10, "2": 30, "3": 10, "4": 50}
            $table->decimal('target_total_marks', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'class_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_aggregate_configs');
        Schema::dropIfExists('exam_term_subjects');
    }
};
