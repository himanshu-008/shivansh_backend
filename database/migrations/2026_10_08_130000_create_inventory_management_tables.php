<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add inventory columns to book_sets if not exists
        Schema::table('book_sets', function (Blueprint $table) {
            if (!Schema::hasColumn('book_sets', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0)->after('amount');
            }
            if (!Schema::hasColumn('book_sets', 'min_stock_alert')) {
                $table->integer('min_stock_alert')->default(10)->after('stock_quantity');
            }
        });

        // 2. Add inventory & detail columns to dress_items if not exists
        Schema::table('dress_items', function (Blueprint $table) {
            if (!Schema::hasColumn('dress_items', 'gender_category')) {
                $table->string('gender_category')->default('All')->after('name');
            }
            if (!Schema::hasColumn('dress_items', 'size')) {
                $table->string('size')->default('Free Size')->after('gender_category');
            }
            if (!Schema::hasColumn('dress_items', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0)->after('amount');
            }
            if (!Schema::hasColumn('dress_items', 'min_stock_alert')) {
                $table->integer('min_stock_alert')->default(10)->after('stock_quantity');
            }
        });

        // 3. Create inventory_transactions audit ledger table
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('item_type'); // 'book' or 'dress'
            $table->unsignedBigInteger('item_id');
            $table->string('transaction_type'); // 'restock', 'distributed', 'adjustment', 'damaged', 'return'
            $table->integer('quantity'); // e.g. +50 for restock, -1 for distributed/damaged
            $table->integer('balance_after'); // resulting stock level
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('reference_no')->nullable(); // PO #, Receipt #, Batch #
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'item_type', 'item_id']);
            $table->index(['school_id', 'transaction_type']);
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');

        Schema::table('dress_items', function (Blueprint $table) {
            if (Schema::hasColumn('dress_items', 'min_stock_alert')) {
                $table->dropColumn('min_stock_alert');
            }
            if (Schema::hasColumn('dress_items', 'stock_quantity')) {
                $table->dropColumn('stock_quantity');
            }
            if (Schema::hasColumn('dress_items', 'size')) {
                $table->dropColumn('size');
            }
            if (Schema::hasColumn('dress_items', 'gender_category')) {
                $table->dropColumn('gender_category');
            }
        });

        Schema::table('book_sets', function (Blueprint $table) {
            if (Schema::hasColumn('book_sets', 'min_stock_alert')) {
                $table->dropColumn('min_stock_alert');
            }
            if (Schema::hasColumn('book_sets', 'stock_quantity')) {
                $table->dropColumn('stock_quantity');
            }
        });
    }
};
