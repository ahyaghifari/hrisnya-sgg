<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Fee Claim & Pajak Jasa Dokter dihapus total dari HRIS (instruksi user 2026-09-05) — dokter/
 * Mitra sudah punya sistem perhitungan sendiri di luar Payroll HRIS ini, jadi FeeClaimCalculator,
 * halaman admin "Pengajuan Fee" (FeeSubmissionResource), dan komponen enum-nya sudah dicabut
 * dari kode duluan.
 *
 * PENTING beda dari drop_doctor_payroll_tables (yang datanya dikonfirmasi dummy) — tabel ini
 * BUKAN dummy, isinya klaim fee/pajak jasa dokter yang beneran pernah diinput finance. Baris
 * `payroll_items` (source_type fee_claim/pajak_jasa_dokter) yang SUDAH masuk slip gaji periode
 * lalu TIDAK disentuh migrasi ini (tabel terpisah, cuma tag source_type string) - histori slip
 * tetap utuh. Yang hilang cuma tabel "antrian klaim" ini sendiri - kalau saat migrasi ini
 * dijalankan masih ada fee_items yang is_paid=false (belum sempat masuk payroll manapun),
 * datanya ikut hilang permanen. Cek dulu sebelum deploy ke production kalau perlu diarsipkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fee_items');
        Schema::dropIfExists('fee_submissions');
    }

    public function down(): void
    {
        // Sengaja tidak direverse - lihat docblock, fee/pajak jasa dokter sudah dipindah total
        // ke sistem dokter terpisah, tidak ada rencana kembali ke skema ini.
    }
};
