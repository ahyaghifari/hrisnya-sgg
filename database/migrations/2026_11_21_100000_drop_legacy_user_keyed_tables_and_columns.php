<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang sisa penyimpanan lama setelah payroll, koperasi/insentif/tanggungan BPJS/tunjangan
 * eksekutif/jasa unit, dan kontrak/orientasi pindah ke employee_id (migrasi 2026_11_17 - 2026_11_20):
 *
 * - tabel lama yang isinya sudah disalin dengan id sama: user_koperasi, incentives, bpjs_dependents,
 *   executive_allowances, jasa_unit_details, user_contracts, user_orientations;
 * - kolom induk lama di tabel pengajuan (contract_submissions.user_contract_id / new_contract_id,
 *   orientation_submissions.user_orientation_id) — kode sudah memakai employee_contract_id;
 * - kolom user_id (beserta FK dan index/unique-nya) di payrolls, payroll_manual_lines,
 *   payroll_component_overrides, gaji_pokok_kenaikan_overrides — padanannya employee_id.
 *
 * Tiap langkah DITOLAK (tanpa mengubah apa pun) kalau ada baris lama yang belum punya padanan di
 * tabel baru, supaya data tidak hilang diam-diam. Aman diulang: langkah yang sudah jalan dilewati
 * (DDL MySQL tidak transaksional). Tidak bisa dibalik; pulihkan dari backup.
 */
return new class extends Migration
{
    /** tabel lama => tabel baru yang memuat id yang sama */
    private const COPIED = [
        'user_koperasi' => 'employee_koperasi',
        'incentives' => 'employee_incentives',
        'bpjs_dependents' => 'employee_bpjs_dependents',
        'executive_allowances' => 'employee_executive_allowances',
        'jasa_unit_details' => 'payroll_jasa_unit_details',
        'user_contracts' => 'employee_contracts',
        'user_orientations' => 'employee_contracts',
    ];

    /** tabel pengajuan => [kolom lama => kolom pengganti] */
    private const SUBMISSION_COLUMNS = [
        'contract_submissions' => ['user_contract_id' => 'employee_contract_id', 'new_contract_id' => 'new_employee_contract_id'],
        'orientation_submissions' => ['user_orientation_id' => 'employee_contract_id'],
    ];

    private const PAYROLL_TABLES = ['payrolls', 'payroll_manual_lines', 'payroll_component_overrides', 'gaji_pokok_kenaikan_overrides'];

    public function up(): void
    {
        $this->assertNothingWouldBeLost();

        foreach (self::SUBMISSION_COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->dropColumnWithConstraints($table, $column);
            }
        }

        foreach (self::PAYROLL_TABLES as $table) {
            $this->dropColumnWithConstraints($table, 'user_id');
        }

        foreach (array_keys(self::COPIED) as $old) {
            Schema::dropIfExists($old);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi ini membuang data dan tidak bisa dibalik. Pulihkan dari backup database.');
    }

    private function assertNothingWouldBeLost(): void
    {
        foreach (self::COPIED as $old => $new) {
            if (! Schema::hasTable($old)) {
                continue;
            }

            $missing = DB::table($old)->whereNotIn('id', DB::table($new)->select('id'))->count();

            if ($missing > 0) {
                throw new RuntimeException("{$missing} baris {$old} tidak ada di {$new}. Tabel lama TIDAK dibuang; periksa dulu.");
            }
        }

        foreach (self::SUBMISSION_COLUMNS as $table => $columns) {
            foreach ($columns as $old => $new) {
                if (! Schema::hasColumn($table, $old) || ! Schema::hasColumn($table, $new)) {
                    continue;
                }

                $unmapped = DB::table($table)->whereNotNull($old)->whereNull($new)->count();

                if ($unmapped > 0) {
                    throw new RuntimeException("{$unmapped} baris {$table}.{$old} belum terpetakan ke {$new}.");
                }
            }
        }

        foreach (self::PAYROLL_TABLES as $table) {
            if (! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $unlinked = DB::table($table)->whereNull('employee_id')->count();

            if ($unlinked > 0) {
                throw new RuntimeException("{$unlinked} baris {$table} belum punya employee_id. Jalankan `php artisan identity:backfill` dulu.");
            }
        }
    }

    /** FK dan index yang memuat kolom itu harus lepas dulu, baru kolomnya (aturan MySQL dan SQLite). */
    private function dropColumnWithConstraints(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                Schema::table($table, fn ($t) => $t->dropForeign($sqlite ? $foreignKey['columns'] : $foreignKey['name']));
            }
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (! $index['primary'] && in_array($column, $index['columns'], true)) {
                Schema::table($table, fn ($t) => $t->dropIndex($index['name']));
            }
        }

        Schema::table($table, fn ($t) => $t->dropColumn($column));
    }
};
