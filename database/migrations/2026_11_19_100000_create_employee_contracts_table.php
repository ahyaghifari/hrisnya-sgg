<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employee_contracts menyatukan user_contracts + user_orientations (kolom `type`: orientasi|kontrak),
 * dikunci employee_id. Isinya disalin SEKALI saat migration ini jalan, dengan id yang SAMA, jadi
 * tabel lama dibiarkan beku (tidak ditulis lagi oleh kode, dihapus di rilis terpisah).
 *
 * Tabel pengajuan (contract_submissions, orientation_submissions) tetap, tapi induknya diarahkan
 * ke employee_contracts lewat kolom BARU (employee_contract_id; contract_submissions juga
 * new_employee_contract_id) yang diisi dari id lama (id sama). Kolom lama di tabel pengajuan
 * dibiarkan apa adanya (nullable) dan tidak dipakai lagi oleh kode.
 *
 * Pemetaan kolom: contract_start_date/contract_end_date (kontrak) -> start_date/end_date;
 * previous_orientation_id -> previous_contract_id; employee_type_id hanya terisi untuk kontrak;
 * duration_months hanya terisi untuk orientasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['user_contracts', 'user_orientations'] as $old) {
            $orphans = DB::table($old)
                ->leftJoin('employees', 'employees.user_id', '=', "{$old}.user_id")
                ->whereNull('employees.id')
                ->count();

            if ($orphans > 0) {
                throw new RuntimeException("{$orphans} baris {$old} belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
            }
        }

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('type', 20); // orientasi | kontrak
            $table->foreignUlid('employee_type_id')->nullable()->constrained('m_employee_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->string('file_path')->nullable();
            $table->string('source')->nullable();
            $table->foreignUlid('source_transition_id')->nullable()->constrained('user_transitions')->nullOnDelete();
            $table->ulid('previous_contract_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type', 'start_date']);
        });

        DB::statement(
            "INSERT INTO employee_contracts (id, employee_id, company_id, type, employee_type_id, start_date, end_date, file_path, source_transition_id, created_at, updated_at) "
            . "SELECT c.id, e.id, c.company_id, 'kontrak', c.employee_type_id, c.contract_start_date, c.contract_end_date, c.file_path, c.source_transition_id, c.created_at, c.updated_at "
            . 'FROM user_contracts c JOIN employees e ON e.user_id = c.user_id'
        );

        DB::statement(
            "INSERT INTO employee_contracts (id, employee_id, company_id, type, start_date, end_date, duration_months, file_path, source, previous_contract_id, created_at, updated_at) "
            . "SELECT o.id, e.id, o.company_id, 'orientasi', o.start_date, o.end_date, o.duration_months, o.file_path, o.source, o.previous_orientation_id, o.created_at, o.updated_at "
            . 'FROM user_orientations o JOIN employees e ON e.user_id = o.user_id'
        );

        $expected = DB::table('user_contracts')->count() + DB::table('user_orientations')->count();

        if (DB::table('employee_contracts')->count() !== $expected) {
            throw new RuntimeException('Jumlah baris employee_contracts tidak sama dengan user_contracts + user_orientations setelah salin.');
        }

        Schema::table('contract_submissions', function (Blueprint $table) {
            $table->ulid('employee_contract_id')->nullable()->after('user_contract_id');
            $table->foreign('employee_contract_id', 'cs_employee_contract_fk')->references('id')->on('employee_contracts')->nullOnDelete();
            $table->ulid('new_employee_contract_id')->nullable()->after('new_contract_id');
            $table->foreign('new_employee_contract_id', 'cs_new_employee_contract_fk')->references('id')->on('employee_contracts')->nullOnDelete();
        });

        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->ulid('employee_contract_id')->nullable()->after('user_orientation_id');
            $table->foreign('employee_contract_id', 'os_employee_contract_fk')->references('id')->on('employee_contracts')->nullOnDelete();
        });

        DB::statement('UPDATE contract_submissions SET employee_contract_id = user_contract_id WHERE user_contract_id IS NOT NULL');
        DB::statement('UPDATE contract_submissions SET new_employee_contract_id = new_contract_id WHERE new_contract_id IS NOT NULL');
        DB::statement('UPDATE orientation_submissions SET employee_contract_id = user_orientation_id WHERE user_orientation_id IS NOT NULL');
    }

    public function down(): void
    {
        // SQLite tidak bisa drop FK dengan nama; pakai bentuk kolom di sana.
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('orientation_submissions', function (Blueprint $table) use ($sqlite) {
            $table->dropForeign($sqlite ? ['employee_contract_id'] : 'os_employee_contract_fk');
            $table->dropColumn('employee_contract_id');
        });

        Schema::table('contract_submissions', function (Blueprint $table) use ($sqlite) {
            $table->dropForeign($sqlite ? ['employee_contract_id'] : 'cs_employee_contract_fk');
            $table->dropForeign($sqlite ? ['new_employee_contract_id'] : 'cs_new_employee_contract_fk');
            $table->dropColumn(['employee_contract_id', 'new_employee_contract_id']);
        });

        Schema::dropIfExists('employee_contracts');
    }
};
