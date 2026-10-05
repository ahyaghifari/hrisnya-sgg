<?php

namespace App\Console\Commands;

use App\Models\Master\Companies;
use App\Models\Role;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class GrantHoldingAdminAccess extends Command
{
    protected $signature = 'access:grant-holding-admin
        {--role=Admin : Nama role yang diberi akses}
        {--with-other-companies : Sertakan lihat_perusahaan_lain (lihat/pilih perusahaan lain)}
        {--apply : Benar-benar menyimpan. Tanpa flag ini hanya menampilkan rencana.}';

    protected $description = 'Beri role Admin di perusahaan holding akses halaman Hak Akses dan Manajemen Pengguna (Role & Permission)';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $this->info($apply ? 'MODE: APPLY (mengubah database)' : 'MODE: DRY-RUN (pakai --apply untuk eksekusi)');

        $holding = Companies::where('code', Companies::HOLDING_CODE)->first();
        if (! $holding) {
            $this->error('Perusahaan holding (kode ' . Companies::HOLDING_CODE . ') tidak ditemukan.');

            return self::FAILURE;
        }

        $role = Role::where('name', $this->option('role'))->where('company_id', $holding->id)->first();
        if (! $role) {
            $this->error("Role '{$this->option('role')}' di {$holding->name} tidak ditemukan.");
            $this->line('Role bernama mirip: ' . (Role::with('company')->where('name', 'like', '%' . $this->option('role') . '%')->get()
                ->map(fn ($r) => "{$r->name} [" . ($r->company?->code ?? 'global') . ']')->implode(', ') ?: '-'));

            return self::FAILURE;
        }

        $names = ['kelola_hak_akses'];
        foreach (['Role', 'Permission'] as $subject) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $verb) {
                $names[] = "{$verb}:{$subject}";
            }
        }
        if ($this->option('with-other-companies')) {
            $names[] = 'lihat_perusahaan_lain';
        }

        $missing = collect($names)->diff($role->permissions()->pluck('name'));
        $this->line("Role: {$role->name} ({$holding->name})");
        $this->line($missing->isEmpty() ? 'Semua permission sudah dimiliki.' : 'Akan ditambahkan: ' . $missing->implode(', '));

        if ($apply && $missing->isNotEmpty()) {
            $role->givePermissionTo(
                $missing->map(fn ($n) => Permission::firstOrCreate(['name' => $n, 'guard_name' => 'web']))
            );
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Selesai.');
        }

        return self::SUCCESS;
    }
}
