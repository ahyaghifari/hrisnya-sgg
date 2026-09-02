<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel queue terpisah untuk koneksi queue `payroll-slip` (lihat config/queue.php).
 * Skema identik `jobs` bawaan Laravel — cuma tabelnya sendiri supaya batch kirim slip +
 * build ZIP tak menumpuk di `jobs` bersama notif absensi dll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_slip_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slip_jobs');
    }
};
