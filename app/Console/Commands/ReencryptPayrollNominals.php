<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-off: konversi nilai plaintext existing di kolom nominal payroll (§4 planning
 * 2026-08-13-payroll-enkripsi-nominal-key-terpisah.md) jadi ciphertext, SEBELUM cast
 * PayrollEncrypted/encrypted ditempel ke model - kalau kebalik (cast aktif duluan), Eloquent
 * bakal nyoba decrypt string plaintext dan throw DecryptException pas dibaca.
 *
 * Idempotent: kolom nominal (numerik) dicek is_numeric() - kalau sudah ciphertext (bukan
 * angka), dilewati. Kolom audit_logs (bebas teks) dicek coba-decrypt - kalau berhasil didecrypt,
 * berarti sudah ciphertext, dilewati.
 */
class ReencryptPayrollNominals extends Command
{
    protected $signature = 'payroll:reencrypt-nominals {--dry-run : Cuma hitung baris yang akan diubah, gak nulis apa-apa}';

    protected $description = 'Reencrypt kolom nominal payroll (dan audit_logs) yang masih plaintext sebelum cast enkripsi ditempel ke model';

    /** @var array<int, array{table: string, columns: string[]}> */
    private array $numericTables = [
        ['table' => 'payrolls', 'columns' => ['gross_salary', 'net_salary', 'total_deductions']],
        ['table' => 'payroll_items', 'columns' => ['amount', 'unit_amount']],
        ['table' => 'incentives', 'columns' => ['amount']],
        ['table' => 'user_koperasi', 'columns' => ['amount']],
        ['table' => 'omset_details', 'columns' => ['amount']],
        ['table' => 'executive_allowances', 'columns' => ['amount']],
        ['table' => 'position_allowances', 'columns' => ['amount']],
        ['table' => 'legacy_position_allowances', 'columns' => ['amount']],
        ['table' => 'transport_allowances', 'columns' => ['amount']],
        ['table' => 'jasa_unit_details', 'columns' => ['amount']],
        ['table' => 'payroll_manual_lines', 'columns' => ['amount']],
        ['table' => 'tenure_allowances', 'columns' => ['amount']],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach ($this->numericTables as $spec) {
            $this->reencryptNumericTable($spec['table'], $spec['columns'], $dryRun);
        }

        $this->reencryptSettingsBpjsDeclaredValue($dryRun);
        $this->reencryptAuditLogs($dryRun);

        $this->info($dryRun ? 'Dry run selesai - belum ada yang ditulis.' : 'Reencrypt selesai.');

        return self::SUCCESS;
    }

    private function reencryptNumericTable(string $table, array $columns, bool $dryRun): void
    {
        $rows = DB::table($table)->select(['id', ...$columns])->get();
        $changed = 0;

        foreach ($rows as $row) {
            $update = [];

            foreach ($columns as $column) {
                $value = $row->{$column};

                if ($value === null || ! is_numeric(trim((string) $value))) {
                    continue;
                }

                $update[$column] = app('payroll.encrypter')->encryptString((string) round((float) $value, 2));
            }

            if ($update === []) {
                continue;
            }

            $changed++;

            if (! $dryRun) {
                DB::table($table)->where('id', $row->id)->update($update);
            }
        }

        $this->line("{$table}: {$changed}/{$rows->count()} baris di-reencrypt");
    }

    private function reencryptSettingsBpjsDeclaredValue(bool $dryRun): void
    {
        $rows = DB::table('settings')->where('key', 'bpjs_declared_value')->select(['id', 'value'])->get();
        $changed = 0;

        foreach ($rows as $row) {
            if ($row->value === null || ! is_numeric(trim((string) $row->value))) {
                continue;
            }

            $changed++;

            if (! $dryRun) {
                DB::table('settings')->where('id', $row->id)->update([
                    'value' => app('payroll.encrypter')->encryptString((string) round((float) $row->value, 2)),
                ]);
            }
        }

        $this->line("settings.bpjs_declared_value: {$changed}/{$rows->count()} baris di-reencrypt");
    }

    private function reencryptAuditLogs(bool $dryRun): void
    {
        $rows = DB::table('audit_logs')->select(['id', 'old_value', 'new_value'])->get();
        $changed = 0;

        foreach ($rows as $row) {
            $update = [];

            foreach (['old_value', 'new_value'] as $column) {
                $value = $row->{$column};

                if ($value === null || $this->isAlreadyEncrypted($value)) {
                    continue;
                }

                $update[$column] = Crypt::encryptString($value);
            }

            if ($update === []) {
                continue;
            }

            $changed++;

            if (! $dryRun) {
                DB::table('audit_logs')->where('id', $row->id)->update($update);
            }
        }

        $this->line("audit_logs: {$changed}/{$rows->count()} baris di-reencrypt");
    }

    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
