<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nama resmi perusahaan khusus untuk surat (SP, Berita Acara, dst); kosong = pakai `name`. Diedit di admin panel. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_companies', fn (Blueprint $table) => $table->string('letter_name')->nullable()->after('name'));
    }

    public function down(): void
    {
        Schema::table('m_companies', fn (Blueprint $table) => $table->dropColumn('letter_name'));
    }
};
