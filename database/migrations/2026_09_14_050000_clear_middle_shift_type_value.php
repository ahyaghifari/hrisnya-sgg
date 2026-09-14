<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ShiftType::MIDDLE dihapus (instruksi user 2026-09-14) - Middle sudah tidak beda logic dari
 * shift biasa lain (fixed calculation, sudah dikecualikan dari Extra Shift lewat include-list
 * ExtraShiftRulePage yang cuma izinkan Pagi/Siang/Malam), jadi tidak perlu enum eksplisit lagi -
 * `shift_type = null` sudah cukup buat maksud yang sama. Baris lama (kalau ada) diubah ke null
 * biar tidak nyangkut jadi string 'middle' yatim yang tidak match enum manapun.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('schedule_shift_types')->where('shift_type', 'middle')->update(['shift_type' => null]);
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - baris mana yang dulu 'middle' vs null asli sudah tidak
        // terlacak lagi setelah di-null-kan, pola sama seperti migration cleanup lain.
    }
};
