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
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'photo_url')) {
                $table->longText('photo_url')->nullable()->after('student_nic_id');
            }
            if (!Schema::hasColumn('students', 'blood_group')) {
                $table->string('blood_group', 10)->nullable()->after('photo_url');
            }
        });

        if (!Schema::hasTable('id_card_templates')) {
            Schema::create('id_card_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name')->default('Standard Portrait');
                $table->string('orientation')->default('portrait'); // portrait or landscape
                $table->double('width_mm')->default(54.0);
                $table->double('height_mm')->default(86.0);
                $table->string('primary_color')->default('#1B6CA8');
                $table->string('accent_color')->default('#F39C12');
                $table->boolean('show_blood_group')->default(true);
                $table->boolean('show_dob')->default(true);
                $table->boolean('show_father_name')->default(true);
                $table->boolean('show_contact')->default(true);
                $table->boolean('show_address')->default(true);
                $table->boolean('show_qr_code')->default(true);
                $table->boolean('show_signature')->default(true);
                $table->longText('signature_url')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'blood_group')) {
                $table->dropColumn('blood_group');
            }
            if (Schema::hasColumn('students', 'photo_url')) {
                $table->dropColumn('photo_url');
            }
        });
    }
};
