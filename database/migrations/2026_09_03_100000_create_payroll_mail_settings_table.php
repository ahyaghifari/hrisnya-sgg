<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial SMTP per perusahaan KHUSUS pengiriman slip gaji (SendPayrollSlipJob) — biar
 * tiap RS kirim slip dari akun email-nya sendiri, bukan 1 relay global. `password`
 * dienkripsi via cast Laravel (`encrypted`, key APP_KEY). Perusahaan tanpa baris (atau
 * is_active=false) -> pakai mailer default `config('mail')` seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_mail_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('m_companies')->cascadeOnDelete();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username');
            $table->text('password'); // cast 'encrypted'
            $table->string('encryption')->nullable(); // 'tls' | 'ssl' | null
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_mail_settings');
    }
};
