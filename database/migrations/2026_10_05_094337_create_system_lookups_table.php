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
        Schema::create('system_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g., 'gender', 'caste', 'religion'
            $table->string('value'); // e.g., 'Male', 'OBC', 'Hindu'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_lookups');
    }
};
