<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('id_card_templates', 'show_admission_no')) {
                $table->boolean('show_admission_no')->default(true)->after('accent_color');
            }
            if (!Schema::hasColumn('id_card_templates', 'show_roll_no')) {
                $table->boolean('show_roll_no')->default(true)->after('show_admission_no');
            }
            if (!Schema::hasColumn('id_card_templates', 'show_mother_name')) {
                $table->boolean('show_mother_name')->default(false)->after('show_father_name');
            }
            if (!Schema::hasColumn('id_card_templates', 'show_gender')) {
                $table->boolean('show_gender')->default(false)->after('show_dob');
            }
            if (!Schema::hasColumn('id_card_templates', 'show_student_pen')) {
                $table->boolean('show_student_pen')->default(false)->after('show_address');
            }
            if (!Schema::hasColumn('id_card_templates', 'show_apaar_id')) {
                $table->boolean('show_apaar_id')->default(false)->after('show_student_pen');
            }
            if (!Schema::hasColumn('id_card_templates', 'show_aadhar_no')) {
                $table->boolean('show_aadhar_no')->default(false)->after('show_apaar_id');
            }
            if (!Schema::hasColumn('id_card_templates', 'custom_fields')) {
                $table->longText('custom_fields')->nullable()->after('is_default');
            }
        });
    }

    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $cols = [
                'show_admission_no',
                'show_roll_no',
                'show_mother_name',
                'show_gender',
                'show_student_pen',
                'show_apaar_id',
                'show_aadhar_no',
                'custom_fields',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('id_card_templates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
