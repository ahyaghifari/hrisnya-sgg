<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tanggal gajian aktual (beda dari end_date periode) — ditampilkan di slip gaji "Tanggal Bayar". */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->date('payment_date')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('payment_date');
        });
    }
};
