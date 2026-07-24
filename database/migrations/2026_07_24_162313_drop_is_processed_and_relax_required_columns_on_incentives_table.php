<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifikasi Insentif metode "master" (input manual) — is_processed dicabut total, IncentiveCalculator
 * sekarang baca langsung by tanggal per periode (regenerate-safe, sama pola dengan Koperasi/Bon/Import),
 * bukan "konsumsi sekali lalu terkunci". Nama Program & Tanggal juga dilonggarkan jadi nullable — cuma
 * Penerima & Nominal yang wajib diisi di form portal (lihat IncentivesPage::saveForm(), default tanggal ke
 * hari ini kalau dikosongkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->dropColumn('is_processed');
            $table->string('name')->nullable()->change();
            $table->date('incentive_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->boolean('is_processed')->default(false);
            $table->string('name')->nullable(false)->change();
            $table->date('incentive_date')->nullable(false)->change();
        });
    }
};
