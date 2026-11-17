<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('class_name');
            $table->string('name'); // e.g. "Class 1 Complete Book Set"
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('dress_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('class_name')->nullable(); // nullable for all-school uniform
            $table->string('name'); // e.g. "Regular Uniform Set", "Sports Kit", "Winter Blazer"
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('book_set_id')->nullable()->constrained('book_sets')->nullOnDelete();
            $table->foreignId('dress_item_id')->nullable()->constrained('dress_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['book_set_id']);
            $table->dropColumn('book_set_id');
            $table->dropForeign(['dress_item_id']);
            $table->dropColumn('dress_item_id');
        });

        Schema::dropIfExists('dress_items');
        Schema::dropIfExists('book_sets');
    }
};
