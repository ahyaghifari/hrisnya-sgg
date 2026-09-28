<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit tambahan (instruksi user 2026-09-28, menutup salah satu gap opsional dari
 * docs/HRIS_TTE_NonTersertifikasi_QR_Verification.md §15/§24: Authentication + Audit Log) -
 * IP address & user agent PELAKU saat menandatangani, disimpan di baris signature itu sendiri
 * (bukan tabel audit_trail generik terpisah - satu-satunya event yang perlu dijejak sekarang
 * cuma "menandatangani", YAGNI bikin tabel event umum untuk 1 jenis event). TIDAK ditampilkan
 * di halaman verifikasi publik - internal-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('signed_at');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
