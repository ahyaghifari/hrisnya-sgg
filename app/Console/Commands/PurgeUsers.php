<?php

namespace App\Console\Commands;

use App\Models\Employee\Employee;
use App\Models\Person\Person;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus PERMANEN user tertentu (mis. akun demo) beserta salinan persons/employees dan data
 * approval-nya. TANPA --force hanya melapor (dry-run). Pengaman:
 * - NIP harus ada semua; akun non-karyawan & eksekutif (User::NON_KARYAWAN_IDS) ditolak.
 * - Semua FK ke users yang tidak cascade (RESTRICT/NO ACTION) dideteksi otomatis dari skema.
 *   approval_snapshots.created_by & approval_activities.acted_by ditangani sendiri; penghalang
 *   lain yang berisi baris (payrolls, employee_sps, dst) MEMBATALKAN penghapusan kecuali
 *   diizinkan eksplisit lewat --also=tabel.kolom (barisnya ikut dihapus).
 * - Aktivitas approval user ini pada pengajuan MILIK ORANG LAIN membatalkan penghapusan kecuali
 *   --nullify-approvals (nama pelakunya dikosongkan, riwayatnya tetap).
 * - Semuanya satu transaksi; user dihapus lewat query langsung supaya observer tidak membuat
 *   ulang persons/employees.
 */
class PurgeUsers extends Command
{
    protected $signature = 'identity:purge-users
        {nip* : NIP user yang dihapus permanen}
        {--force : Benar-benar menghapus (tanpa ini hanya laporan)}
        {--nullify-approvals : Aktivitas approval pada pengajuan orang lain: kosongkan pelakunya, jangan batalkan}
        {--also=* : tabel.kolom penghalang lain yang barisnya ikut dihapus (mis. payrolls.user_id)}';

    protected $description = 'Hapus permanen user (mis. akun demo) beserta salinan dan data approval-nya';

    private const POLYMORPHIC = [
        'model_has_roles' => 'model_id',
        'model_has_permissions' => 'model_id',
        'notifications' => 'notifiable_id',
        'personal_access_tokens' => 'tokenable_id',
        'push_subscriptions' => 'subscribable_id',
    ];

    private const APPROVAL_HANDLED = ['approval_snapshots.created_by', 'approval_activities.acted_by'];

    public function handle(): int
    {
        $nips = array_values(array_unique($this->argument('nip')));

        $users = User::withoutGlobalScopes()->withTrashed()->whereIn('nip', $nips)->get();

        if ($missing = array_diff($nips, $users->pluck('nip')->all())) {
            $this->error('NIP tidak ditemukan: ' . implode(', ', $missing));

            return self::FAILURE;
        }

        $ids = $users->pluck('id')->all();

        if ($protected = array_intersect($ids, User::NON_KARYAWAN_IDS)) {
            $this->error('Akun non-karyawan/eksekutif tidak boleh dihapus lewat command ini: '
                . $users->whereIn('id', $protected)->pluck('nip')->implode(', '));

            return self::FAILURE;
        }

        $this->info('User yang akan dihapus (' . count($ids) . '):');
        $this->table(['NIP', 'Nama'], $users->map(fn ($u) => [$u->nip, $u->name])->all());

        $also = (array) $this->option('also');
        $blockers = [];

        foreach ($this->restrictiveForeignKeys() as [$table, $column]) {
            $count = DB::table($table)->whereIn($column, $ids)->count();

            if ($count > 0) {
                $blockers["{$table}.{$column}"] = $count;
            }
        }

        if ($blockers) {
            $this->warn('Data yang menempel ke user ini lewat FK tanpa cascade:');
            $this->table(['Tabel.kolom', 'Baris'], collect($blockers)->map(fn ($n, $k) => [$k, $n])->values()->all());
        }

        $unhandled = array_diff(array_keys($blockers), self::APPROVAL_HANDLED, $also);

        if ($unhandled) {
            $this->error('Dibatalkan: penghalang ini berisi data dan belum diizinkan: ' . implode(', ', $unhandled)
                . '. Kalau memang boleh dihapus, tambahkan --also=' . implode(' --also=', $unhandled));

            return self::FAILURE;
        }

        // Pengajuan yang dibuat user ini tapi sudah diproses orang LAIN: bisa jadi pengajuan
        // karyawan asli yang dibuatkan user ini -> bukan murni demo, jangan dihapus otomatis.
        $created = DB::table('approval_snapshots')->whereIn('created_by', $ids)->selectRaw('module_type, count(*) as n')->groupBy('module_type')->pluck('n', 'module_type');

        if ($created->isNotEmpty()) {
            $this->line('Pengajuan approval yang dibuat user ini: ' . $created->map(fn ($n, $t) => "{$t}={$n}")->implode(', '));
        }

        $shared = DB::table('approval_snapshots as s')
            ->whereIn('s.created_by', $ids)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('approval_activities as a')
                ->whereColumn('a.snapshot_id', 's.id')->whereNotNull('a.acted_by')->whereNotIn('a.acted_by', $ids))
            ->count();

        if ($shared > 0) {
            $this->error("Dibatalkan: {$shared} pengajuan yang dibuat user ini sudah diproses orang lain (kemungkinan pengajuan karyawan asli). "
                . 'Periksa manual sebelum menghapus.');

            return self::FAILURE;
        }

        // Aktivitas yang dikerjakan user ini pada pengajuan yang BUKAN dibuat user ini.
        $foreign = Schema::hasTable('approval_activities')
            ? DB::table('approval_activities as a')
                ->join('approval_snapshots as s', 's.id', '=', 'a.snapshot_id')
                ->whereIn('a.acted_by', $ids)
                ->where(fn ($q) => $q->whereNull('s.created_by')->orWhereNotIn('s.created_by', $ids))
                ->count()
            : 0;

        if ($foreign > 0 && ! $this->option('nullify-approvals')) {
            $this->error("Dibatalkan: user ini pernah memproses {$foreign} aktivitas approval pada pengajuan milik orang lain. "
                . 'Menghapusnya mengubah riwayat pengajuan itu. Tambahkan --nullify-approvals untuk mengosongkan nama pelakunya saja.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->line('Dry-run: tidak ada yang dihapus. Tambahkan --force untuk menghapus.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($ids, $also) {
                // persons dulu (persons.user_id SET NULL akan meninggalkan yatim); cascade ke
                // identitas, alamat, lampiran, employees, employee_careers.
                Person::whereIn('user_id', $ids)->delete();

                foreach (self::POLYMORPHIC as $table => $column) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn($column, $ids)->delete();
                    }
                }

                // Pengajuan yang dibuat user ini (cascade: aktivitas & riwayat approval-nya).
                DB::table('approval_snapshots')->whereIn('created_by', $ids)->delete();

                // Sisa aktivitas pada pengajuan orang lain: kosongkan pelakunya.
                DB::table('approval_activities')->whereIn('acted_by', $ids)->update(['acted_by' => null]);

                foreach ($also as $target) {
                    [$table, $column] = explode('.', $target, 2);
                    DB::table($table)->whereIn($column, $ids)->delete();
                }

                DB::table('users')->whereIn('id', $ids)->delete();
            });
        } catch (QueryException $e) {
            $this->error('Dibatalkan & di-rollback: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Selesai. Sisa -> users: ' . User::withoutGlobalScopes()->withTrashed()->count()
            . ', persons: ' . Person::count() . ', employees: ' . Employee::count());

        return self::SUCCESS;
    }

    /** @return array<int, array{0: string, 1: string}> [tabel, kolom] FK ke users tanpa cascade/set null. */
    private function restrictiveForeignKeys(): array
    {
        $found = [];

        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $fk) {
                if ($fk['foreign_table'] === 'users' && in_array(strtolower((string) $fk['on_delete']), ['restrict', 'no action'], true)) {
                    foreach ($fk['columns'] as $column) {
                        $found[] = [$table['name'], $column];
                    }
                }
            }
        }

        return $found;
    }
}
