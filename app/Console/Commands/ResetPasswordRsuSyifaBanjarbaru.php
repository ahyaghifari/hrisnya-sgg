<?php

namespace App\Console\Commands;

use App\Imports\Kepegawaian\KaryawanAccountImport;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * One-off: reset semua password karyawan RSU Syifa Medika Banjarbaru (kode company '0001') ke
 * format default yang sama dengan password awal hasil import (KaryawanAccountImport::
 * generatePassword() - !{tanggal lahir 2 digit}{NIP}{tahun lahir}#), bukan bikin format baru
 * sendiri. Karyawan tanpa NIP/tanggal lahir lengkap dilewati (tidak bisa dihitung password-nya),
 * bukan bikin password asal. 2 akun eksekutif non-karyawan (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS)
 * otomatis TIDAK ikut - dibuang dari User::query() lewat global scope 'non_karyawan'.
 */
class ResetPasswordRsuSyifaBanjarbaru extends Command
{
    protected $signature = 'password:reset-rsu-banjarbaru {--apply : Actually update the database. Without this flag, only prints the planned changes.}';

    protected $description = 'Reset semua password karyawan RSU Syifa Medika Banjarbaru ke format default (sama seperti password awal import)';

    private const COMPANY_CODE = '0001';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'MODE: APPLY (akan mengubah database)' : 'MODE: DRY-RUN (tidak mengubah apapun, pakai --apply untuk eksekusi)');
        $this->newLine();

        $company = Companies::where('code', self::COMPANY_CODE)->first();

        if (! $company) {
            $this->error('Company dengan kode ' . self::COMPANY_CODE . ' (RSU Syifa Medika Banjarbaru) tidak ditemukan.');

            return self::FAILURE;
        }

        $users = User::with('profile')->where('company_id', $company->id)->get();

        $changed = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $dob = $user->profile?->date_of_birth;

            if (! $user->nip || ! $dob) {
                $this->warn("[SKIP] {$user->name}: NIP atau tanggal lahir belum lengkap.");
                $skipped++;

                continue;
            }

            $password = KaryawanAccountImport::generatePassword($user->nip, $dob);

            $this->line("{$user->name} (NIP {$user->nip}): {$password}");

            if ($apply) {
                $user->update(['password' => Hash::make($password)]);
                Log::info("ResetPasswordRsuSyifaBanjarbaru: password direset untuk user_id={$user->id} ({$user->name})");
            }

            $changed++;
        }

        $this->newLine();
        $this->info(($apply ? 'Selesai - ' : 'Dry run selesai, belum ada yang ditulis - ') . "{$changed} diproses, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
