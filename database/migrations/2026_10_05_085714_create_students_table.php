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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('name');
            $table->string('admission_number')->nullable(); // Unique per school ideally
            $table->string('class_name');
            $table->string('section')->nullable();
            $table->string('father_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('status')->default('active'); // active, alumni, dropped
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
