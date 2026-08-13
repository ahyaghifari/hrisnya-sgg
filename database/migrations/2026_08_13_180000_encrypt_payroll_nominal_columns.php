<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubah tipe kolom nominal payroll dari decimal ke text - ciphertext PayrollEncrypted (JSON
 * base64: iv+value+mac) jauh lebih panjang dari angka desimal, gak muat di decimal(15,2).
 * `settings.value` juga ikut (varchar(255) terlalu mepet buat ciphertext) - dipakai buat 1 key
 * spesifik (`bpjs_declared_value`), 64 key lain tetap plaintext (text menampung string biasa
 * sama baiknya dengan varchar).
 *
 * Migration ini CUMA ubah tipe kolom, data existing (plaintext) tetap utuh sebagai teks -
 * reencrypt jalan terpisah lewat `php artisan payroll:reencrypt` SEBELUM cast ditempel ke model
 * (lihat app/Console/Commands/ReencryptPayrollNominals.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->text('gross_salary')->nullable()->change();
            $table->text('net_salary')->nullable()->change();
            $table->text('total_deductions')->nullable()->change();
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->text('unit_amount')->nullable()->change();
            $table->text('amount')->change();
        });

        Schema::table('incentives', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('user_koperasi', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('fee_items', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('omset_details', function (Blueprint $table) {
            $table->text('amount')->nullable()->change();
        });

        Schema::table('executive_allowances', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('position_allowances', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('legacy_position_allowances', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('transport_allowances', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('jasa_unit_details', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->text('amount')->change();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('gross_salary', 15, 2)->default(0)->change();
            $table->decimal('net_salary', 15, 2)->default(0)->change();
            $table->decimal('total_deductions', 15, 2)->default(0)->change();
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('unit_amount', 15, 2)->nullable()->change();
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('incentives', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('user_koperasi', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('fee_items', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('omset_details', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0)->change();
        });

        Schema::table('executive_allowances', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('position_allowances', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('legacy_position_allowances', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('transport_allowances', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('jasa_unit_details', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('payroll_manual_lines', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('value')->change();
        });
    }
};
