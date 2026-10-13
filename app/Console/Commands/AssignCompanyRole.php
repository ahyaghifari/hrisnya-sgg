<?php

namespace App\Console\Commands;

use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class AssignCompanyRole extends Command
{
    protected $signature = 'user:assign-company-role {company : Nama atau kode perusahaan} {role : Nama role milik perusahaan tsb} {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Assign satu role (milik perusahaan) ke semua user pada perusahaan itu, tanpa menghapus role lain';

    public function handle(): int
    {
        $companyInput = $this->argument('company');

        $company = Companies::where('name', $companyInput)->orWhere('code', $companyInput)->first();
        if (! $company) {
            $this->error("Perusahaan '{$companyInput}' tidak ditemukan.");
            return self::FAILURE;
        }

        $role = Role::where('name', $this->argument('role'))->where('company_id', $company->id)->first();
        if (! $role) {
            $this->error("Role '{$this->argument('role')}' tidak ditemukan di '{$company->name}'.");
            return self::FAILURE;
        }

        $users = User::whereCompany($company->id)->get()->reject(fn ($u) => $u->hasRole($role));

        if ($users->isEmpty()) {
            $this->info('Semua user sudah punya role ini.');
            return self::SUCCESS;
        }

        $this->info("Akan assign role '{$role->name}' ke {$users->count()} user pada '{$company->name}'.");

        if (! $this->option('force') && ! $this->confirm('Lanjutkan?')) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        $users->each->assignRole($role);

        $this->info("Selesai. {$users->count()} user diberi role '{$role->name}'.");

        return self::SUCCESS;
    }
}
