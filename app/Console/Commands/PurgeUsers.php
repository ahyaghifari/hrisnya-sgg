<?php

namespace App\Console\Commands;

use App\Models\Employee\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus PERMANEN user tertentu (mis. akun demo) beserta salinan employees dan data
 * approval-nya. TANPA --force hanya melapor (dry-run). Pengaman:
 * - NIP harus ada semua; akun non-karyawan & eksekutif (User::NON_KARYAWAN_IDS) ditolak.
 * - User yang punya penghalang DILEWATI (tidak disentuh) dan dilaporkan beserta alasannya;
 *   sisanya tetap dihapus. Karena melewati satu user bisa membuat user lain ikut bermasalah
 *   (mis. B memproses pengajuan A yang dilewati), pengecekan diulang sampai stabil.
 * - Penghalang per user: FK ke users tanpa cascade (RESTRICT/NO ACTION, dideteksi dari skema)
 *   yang berisi baris - kecuali diizinkan lewat --also=tabel.kolom; pengajuan yang dibuat user
 *   ini tapi sudah diproses orang lain (kemungkinan pengajuan karyawan asli); dan aktivitas
 *   approval pada pengajuan milik orang lain - kecuali --nullify-approvals (pelaku dikosongkan).
 * - Satu transaksi; user dihapus lewat query langsung supaya observer tidak membuat ulang
 *   employees.
 */
class PurgeUsers extends Command
{
    protected $signature = 'identity:purge-users
        {nip* : NIP user yang dihapus permanen}
        {--force : Benar-benar menghapus (tanpa ini hanya laporan)}
        {--nullify-approvals : Aktivitas approval pada pengajuan orang lain: kosongkan pelakunya, jangan lewati user}
        {--also=* : tabel.kolom penghalang lain yang barisnya ikut dihapus (mis. payrolls.user_id)}';

    protected $description = 'Hapus permanen user (mis. akun demo) beserta salinan dan data approval-nya; user bermasalah dilewati';

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

        if ($protected = array_intersect($users->pluck('id')->all(), User::NON_KARYAWAN_IDS)) {
            $this->error('Akun non-karyawan/eksekutif tidak boleh dihapus lewat command ini: '
                . $users->whereIn('id', $protected)->pluck('nip')->implode(', '));

            return self::FAILURE;
        }

        $also = (array) $this->option('also');
        $restrictive = $this->restrictiveForeignKeys();

        // Hitung ulang sampai stabil: melewati satu user mengubah penilaian user lain.
        $set = $users->pluck('id')->all();
        $skipped = [];

        do {
            $blocked = [];

            foreach ($set as $id) {
                if ($reasons = $this->blockReasons($id, $set, $restrictive, $also, (bool) $this->option('nullify-approvals'))) {
                    $blocked[$id] = $reasons;
                }
            }

            $skipped += $blocked;
            $set = array_values(array_diff($set, array_keys($blocked)));
        } while ($blocked && $set);

        $byId = $users->keyBy('id');

        if ($skipped) {
            $this->warn('DILEWATI (tidak dihapus):');
            $this->table(['NIP', 'Alasan'], collect($skipped)->map(fn ($r, $id) => [$byId[$id]->nip, implode('; ', $r)])->values()->all());
        }

        if (! $set) {
            $this->error('Tidak ada user yang bisa dihapus.');

            return self::FAILURE;
        }

        $this->info('Akan dihapus (' . count($set) . '):');
        $this->table(['NIP', 'Nama'], collect($set)->map(fn ($id) => [$byId[$id]->nip, $byId[$id]->name])->all());

        if (! $this->option('force')) {
            $this->line('Dry-run: tidak ada yang dihapus. Tambahkan --force untuk menghapus.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($set, $also) {
                // Salinan karyawan dulu; cascade ke identitas, alamat, lampiran, employee_careers.
                Employee::whereIn('user_id', $set)->delete();

                foreach (self::POLYMORPHIC as $table => $column) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn($column, $set)->delete();
                    }
                }

                // Pengajuan yang dibuat user ini (cascade: aktivitas & riwayat approval-nya).
                DB::table('approval_snapshots')->whereIn('created_by', $set)->delete();

                // Sisa aktivitas pada pengajuan orang lain: kosongkan pelakunya.
                DB::table('approval_activities')->whereIn('acted_by', $set)->update(['acted_by' => null]);

                foreach ($also as $target) {
                    [$table, $column] = explode('.', $target, 2);
                    DB::table($table)->whereIn($column, $set)->delete();
                }

                DB::table('users')->whereIn('id', $set)->delete();
            });
        } catch (QueryException $e) {
            $this->error('Dibatalkan & di-rollback: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Selesai. Dihapus: ' . count($set) . ', dilewati: ' . count($skipped)
            . '. Sisa -> users: ' . User::withoutGlobalScopes()->withTrashed()->count()
            . ', employees: ' . Employee::count());

        return self::SUCCESS;
    }

    /**
     * Alasan user ini tidak boleh dihapus, relatif terhadap $set (user yang sedang dipertimbangkan
     * untuk dihapus bersama). Kosong = aman.
     *
     * @param  array<int, string>  $set
     * @param  array<int, array{0: string, 1: string}>  $restrictive
     * @param  array<int, string>  $also
     * @return array<int, string>
     */
    private function blockReasons(string $id, array $set, array $restrictive, array $also, bool $nullify): array
    {
        $reasons = [];

        foreach ($restrictive as [$table, $column]) {
            $key = "{$table}.{$column}";

            if (in_array($key, self::APPROVAL_HANDLED, true) || in_array($key, $also, true)) {
                continue;
            }

            if ($n = DB::table($table)->where($column, $id)->count()) {
                $reasons[] = "{$key} ({$n})";
            }
        }

        if (! Schema::hasTable('approval_snapshots')) {
            return $reasons;
        }

        // Pengajuan yang dibuat user ini tapi sudah diproses orang LAIN: bisa jadi pengajuan
        // karyawan asli yang dibuatkan user ini -> bukan murni demo.
        $shared = DB::table('approval_snapshots as s')
            ->where('s.created_by', $id)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('approval_activities as a')
                ->whereColumn('a.snapshot_id', 's.id')->whereNotNull('a.acted_by')->whereNotIn('a.acted_by', $set))
            ->count();

        if ($shared) {
            $reasons[] = "{$shared} pengajuan yang dibuat sudah diproses orang lain";
        }

        if (! $nullify) {
            $foreign = DB::table('approval_activities as a')
                ->join('approval_snapshots as s', 's.id', '=', 'a.snapshot_id')
                ->where('a.acted_by', $id)
                ->where(fn ($q) => $q->whereNull('s.created_by')->orWhereNotIn('s.created_by', $set))
                ->count();

            if ($foreign) {
                $reasons[] = "{$foreign} aktivitas approval pada pengajuan orang lain (pakai --nullify-approvals)";
            }
        }

        return $reasons;
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
