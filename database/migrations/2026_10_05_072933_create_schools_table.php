<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('board_type')->default('STATE_BOARD'); // CBSE, STATE_BOARD, ICSE
            $table->string('udise_code')->unique()->nullable();
            $table->string('registration_no')->nullable();
            
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_url')->nullable();
            
            // Feature Toggles (JSON array of active modules like 'transport', 'books')
            $table->json('enabled_modules')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
