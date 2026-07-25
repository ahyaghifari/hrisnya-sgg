<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watermark singleton (selalu 1 baris) buat sinkronisasi access_logs -> attendance_records
 * incremental (App\Console\Commands\SyncAccessLogsAttendance) - biar tiap run cuma baca access_logs yang
 * access_datetime-nya lebih baru dari last_synced_at, bukan scan ulang semua baris hari itu tiap
 * menit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_access_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_access_logs');
    }
};
