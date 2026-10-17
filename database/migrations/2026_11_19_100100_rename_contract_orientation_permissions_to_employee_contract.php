<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission Shield dinamai dari nama class model: model UserContract diganti EmployeeContract, jadi `Verb:UserContract` menjadi `Verb:EmployeeContract`; izin orientasi (`Verb:UserOrientation`) menjadi `Verb:EmployeeOrientation` (tetap terpisah dari kontrak). Baris permissions di-rename di tempat (bukan
 * dibuat ulang), sehingga role_has_permissions / model_has_permissions (berkunci id) otomatis tetap
 * mengikat — tidak ada role yang kehilangan akses. Kalau nama baru sudah ada (mis. dibuat Shield
 * lebih dulu), penerima permission lama dipindahkan ke yang baru lalu yang lama dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('UserContract', 'EmployeeContract');
        $this->rename('UserOrientation', 'EmployeeOrientation');
    }

    public function down(): void
    {
        $this->rename('EmployeeContract', 'UserContract');
        $this->rename('EmployeeOrientation', 'UserOrientation');
    }

    private function rename(string $from, string $to): void
    {
        $olds = DB::table('permissions')->where('name', 'like', "%:{$from}")->get();

        foreach ($olds as $old) {
            $newName = substr($old->name, 0, -strlen($from)) . $to;
            $existing = DB::table('permissions')->where('name', $newName)->where('guard_name', $old->guard_name)->first();

            if (! $existing) {
                DB::table('permissions')->where('id', $old->id)->update(['name' => $newName]);

                continue;
            }

            foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
                $rows = DB::table($table)->where('permission_id', $old->id)->get();

                foreach ($rows as $row) {
                    $data = (array) $row;
                    $data['permission_id'] = $existing->id;
                    DB::table($table)->insertOrIgnore($data);
                }

                DB::table($table)->where('permission_id', $old->id)->delete();
            }

            DB::table('permissions')->where('id', $old->id)->delete();
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
