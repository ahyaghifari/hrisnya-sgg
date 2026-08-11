<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report "email mana yang gagal terkirim" (permintaan user 2026-08-12) - SendPayrollSlipJob
 * sekarang catat kegagalan (bukan cuma Log::error) supaya keliatan di UI, bukan cuma di log
 * server. Ditimpa null lagi begitu kirim ulang berhasil (lihat handle()/failed()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->timestamp('slip_send_failed_at')->nullable()->after('slip_sent_at');
            $table->string('slip_send_error', 255)->nullable()->after('slip_send_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['slip_send_failed_at', 'slip_send_error']);
        });
    }
};
