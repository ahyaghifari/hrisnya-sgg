<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * schedule_shift_types.shift_type (Pagi/Siang/Malam, dipakai ShiftSwapRequestService buat cek
 * kompatibilitas tukar shift) ternyata SEMUA baris NULL - form admin (CreateSchedulePage) tidak
 * pernah punya field buat isi kolom ini sejak awal dibuat (2026-07-18), ketahuan pas fitur Tukar
 * Shift dites 2026-08-16 dan semua percobaan gagal "shift tidak diketahui". Field-nya sudah
 * ditambah ke form (lihat schedule-shift-form.blade.php) - migration ini backfill data yang
 * SUDAH ADA, cuma yang namanya jelas mengandung kata pagi/siang/malam (case-insensitive). Nama
 * ambigu (mis. "Shift Sore" - tidak ada padanan langsung di 3 kategori) SENGAJA dibiarkan NULL,
 * admin isi manual lewat form yang baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('schedule_shift_types')->whereNull('shift_type')->whereRaw('LOWER(name) LIKE ?', ['%pagi%'])->update(['shift_type' => 'pagi']);
        DB::table('schedule_shift_types')->whereNull('shift_type')->whereRaw('LOWER(name) LIKE ?', ['%siang%'])->update(['shift_type' => 'siang']);
        DB::table('schedule_shift_types')->whereNull('shift_type')->whereRaw('LOWER(name) LIKE ?', ['%malam%'])->update(['shift_type' => 'malam']);
    }

    public function down(): void
    {
        // Sengaja tidak di-null-kan lagi - backfill ini cuma isi data yang tadinya kosong,
        // rollback yang mengosongkan lagi cuma bikin bug yang sama muncul lagi.
    }
};
