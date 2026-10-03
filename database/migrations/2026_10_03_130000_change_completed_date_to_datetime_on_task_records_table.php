<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dulu `date` (jam hilang -> tersimpan 00:00), jadi tugas selesai lenyap dari board saat ganti hari,
    // bukan 24 jam setelah diselesaikan. Baris lama tetap 00:00 (jamnya memang sudah hilang).
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dateTime('completed_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->date('completed_date')->nullable()->change();
        });
    }
};
