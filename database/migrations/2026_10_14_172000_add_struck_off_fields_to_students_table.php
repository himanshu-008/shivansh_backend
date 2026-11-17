<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'struck_off_reason')) {
                $table->string('struck_off_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('students', 'struck_off_date')) {
                $table->date('struck_off_date')->nullable()->after('struck_off_reason');
            }
            if (!Schema::hasColumn('students', 'remarks')) {
                $table->text('remarks')->nullable()->after('struck_off_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['struck_off_reason', 'struck_off_date', 'remarks']);
        });
    }
};
