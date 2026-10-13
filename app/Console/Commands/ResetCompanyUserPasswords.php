<?php

namespace App\Console\Commands;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetCompanyUserPasswords extends Command
{
    protected $signature = 'user:reset-company-passwords {company : Nama atau kode perusahaan (m_companies.name / code)} {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Reset password seluruh user pada satu perusahaan ke pola !{nip}#';

    public function handle(): int
    {
        $companyInput = $this->argument('company');

        $company = Companies::where('name', $companyInput)
            ->orWhere('code', $companyInput)
            ->first();

        if (! $company) {
            $this->error("Perusahaan '{$companyInput}' tidak ditemukan.");
            return self::FAILURE;
        }

        $users = User::whereCompany($company->id)
            ->whereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')->whereNotNull('nip'))
            ->get();

        if ($users->isEmpty()) {
            $this->info("Tidak ada user dengan NIP pada perusahaan '{$company->name}'.");
            return self::SUCCESS;
        }

        $this->info("Akan reset password {$users->count()} user pada perusahaan '{$company->name}'.");

        if (! $this->option('force') && ! $this->confirm('Lanjutkan?')) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $user->update(['password' => Hash::make("!{$user->nip}#")]);
        }

        $this->info("Selesai. {$users->count()} password berhasil direset.");

        return self::SUCCESS;
    }
}
