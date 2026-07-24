<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai kapan slip gaji terakhir dikirim ke email karyawan (App\Jobs\SendPayrollSlipJob) —
 * dipakai buat tampilkan status "Terkirim" di halaman detail/list Payroll, bukan buat mencegah
 * kirim ulang (finance boleh kirim ulang kapan saja, cuma menimpa timestamp ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->timestamp('slip_sent_at')->nullable()->after('is_paid');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('slip_sent_at');
        });
    }
};
