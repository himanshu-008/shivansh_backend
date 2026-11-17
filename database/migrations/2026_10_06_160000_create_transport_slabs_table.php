<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('name'); // e.g. "0 - 5 km", "5 - 10 km"
            $table->decimal('min_km', 8, 2)->default(0);
            $table->decimal('max_km', 8, 2)->default(0);
            $table->decimal('monthly_fee', 10, 2); // Monthly fee amount in INR
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('transport_slab_id')->nullable()->constrained('transport_slabs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['transport_slab_id']);
            $table->dropColumn('transport_slab_id');
        });
        Schema::dropIfExists('transport_slabs');
    }
};
