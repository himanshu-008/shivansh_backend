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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('category')->default('Miscellaneous'); // Salaries, Utilities, Maintenance, Uniform Procurement, etc.
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('payment_method')->default('cash'); // cash, online, upi, cheque, bank_transfer, card
            $table->string('vendor_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->longText('receipt_url')->nullable(); // URL or Base64/Data URI of receipt
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index(['school_id', 'expense_date']);
            $table->index(['school_id', 'category']);
            $table->index(['school_id', 'academic_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
