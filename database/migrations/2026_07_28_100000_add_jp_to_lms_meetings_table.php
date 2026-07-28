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
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->unsignedInteger('jp')->nullable()->after('end_time')->comment('Dikonversi otomatis dari durasi (start_time-end_time) memakai setting menit_1_jp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->dropColumn('jp');
        });
    }
};
