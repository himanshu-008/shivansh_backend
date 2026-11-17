<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add supplier / publisher details to book_sets
        Schema::table('book_sets', function (Blueprint $table) {
            if (!Schema::hasColumn('book_sets', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('book_sets', 'supplier_contact')) {
                $table->string('supplier_contact')->nullable()->after('supplier_name');
            }
            if (!Schema::hasColumn('book_sets', 'supplier_address')) {
                $table->string('supplier_address')->nullable()->after('supplier_contact');
            }
            if (!Schema::hasColumn('book_sets', 'supplier_gstin')) {
                $table->string('supplier_gstin')->nullable()->after('supplier_address');
            }
        });

        // 2. Add manufacturer / supplier details to dress_items
        Schema::table('dress_items', function (Blueprint $table) {
            if (!Schema::hasColumn('dress_items', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('dress_items', 'supplier_contact')) {
                $table->string('supplier_contact')->nullable()->after('supplier_name');
            }
            if (!Schema::hasColumn('dress_items', 'supplier_address')) {
                $table->string('supplier_address')->nullable()->after('supplier_contact');
            }
            if (!Schema::hasColumn('dress_items', 'supplier_gstin')) {
                $table->string('supplier_gstin')->nullable()->after('supplier_address');
            }
        });

        // 3. Add supplier details to fee_payments for archival on receipts
        Schema::table('fee_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_payments', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('fee_payments', 'supplier_contact')) {
                $table->string('supplier_contact')->nullable()->after('supplier_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('book_sets', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'supplier_contact', 'supplier_address', 'supplier_gstin']);
        });

        Schema::table('dress_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'supplier_contact', 'supplier_address', 'supplier_gstin']);
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'supplier_contact']);
        });
    }
};
