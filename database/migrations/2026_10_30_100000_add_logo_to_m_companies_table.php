<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Logo perusahaan (path di disk public), ditampilkan di welcome card beranda. Kosong = tidak ada logo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_companies', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('letter_name');
        });
    }

    public function down(): void
    {
        Schema::table('m_companies', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
