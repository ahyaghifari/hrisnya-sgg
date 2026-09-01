<?php
/**
 * Set jabatan (m_job_positions) untuk daftar NIP. Generic — nama jabatan & NIP dari argumen.
 * Mengikuti pola EditKaryawan: update user_profiles.job_position_id + swap baris
 * user_job_positions (hapus jabatan lain, updateOrCreate baris tujuan status 'active').
 *
 * JALANKAN DI SERVER PRODUCTION.
 *
 *   php scripts/set_jabatan.php "Petugas Pantry" 13052025731                 # DRY RUN
 *   php scripts/set_jabatan.php "Cleaning Service" 111 222 333 --apply       # tulis
 *   php scripts/set_jabatan.php "Petugas Pantry" --file=nips.txt --apply     # NIP dari file (1 per baris)
 *
 *   --apply              tulis perubahan (default: dry run, tidak menulis)
 *   --file=<path>        baca NIP tambahan dari file, 1 per baris
 *   --company=<ULID>     override company (default: "RSU Syifa Medika Banjarbaru")
 *   --position=<ULID>    override job position (kalau nama tidak unik / belum ada)
 *   env HRIS_ROOT=<path> kalau file ini tidak di root repo & tidak di scripts/
 *
 * Aman: dry-run dulu; --apply dibungkus 1 transaksi; NIP match exact lalu zero-pad 11;
 * yang tak ketemu / sudah sesuai dilaporkan, tidak disentuh.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ---- cari root repo HRIS ----
$ROOT = getenv('HRIS_ROOT') ?: null;
if (! $ROOT) {
    foreach ([__DIR__, dirname(__DIR__)] as $c) {
        if (is_file($c . '/vendor/autoload.php')) { $ROOT = $c; break; }
    }
}
if (! $ROOT || ! is_file($ROOT . '/vendor/autoload.php')) {
    fwrite(STDERR, "Tidak ketemu vendor/autoload.php. Set env HRIS_ROOT ke root repo HRIS.\n");
    exit(1);
}
require $ROOT . '/vendor/autoload.php';
$app = require $ROOT . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ---- args ----
$apply = false; $companyOpt = null; $positionOpt = null; $file = null;
$positional = [];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') $apply = true;
    elseif (str_starts_with($a, '--company=')) $companyOpt = substr($a, 10);
    elseif (str_starts_with($a, '--position=')) $positionOpt = substr($a, 11);
    elseif (str_starts_with($a, '--file=')) $file = substr($a, 7);
    else $positional[] = $a;
}
$positionName = array_shift($positional);
if (! $positionName) {
    fwrite(STDERR, "Usage: php scripts/set_jabatan.php \"<Nama Jabatan>\" <nip> [nip...] [--file=x] [--apply]\n");
    exit(1);
}

$nips = $positional;
if ($file) {
    if (! is_file($file)) { fwrite(STDERR, "File NIP tidak ada: {$file}\n"); exit(1); }
    $nips = array_merge($nips, array_map('trim', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
}
$nips = array_values(array_unique(array_filter($nips)));
if (! $nips) { fwrite(STDERR, "Tidak ada NIP.\n"); exit(1); }
echo "Jabatan target: \"{$positionName}\"\n";
echo "NIP di daftar: " . count($nips) . "\n";

// ---- company ----
$company = $companyOpt
    ? DB::table('m_companies')->where('id', $companyOpt)->first()
    : DB::table('m_companies')->where('name', 'RSU Syifa Medika Banjarbaru')->first();
if (! $company) { fwrite(STDERR, "Company tidak ketemu.\n"); exit(1); }
echo "Company: {$company->name} ({$company->id})\n";

// ---- job position ----
if ($positionOpt) {
    $pos = DB::table('m_job_positions')->where('id', $positionOpt)->whereNull('deleted_at')->first();
    if (! $pos) { fwrite(STDERR, "Job position --position={$positionOpt} tidak ketemu.\n"); exit(1); }
} else {
    $exact = DB::table('m_job_positions')
        ->where('company_id', $company->id)->whereNull('deleted_at')
        ->whereRaw('LOWER(name) = ?', [mb_strtolower($positionName)])
        ->get(['id', 'name']);
    $cand = $exact->count() === 1 ? $exact : DB::table('m_job_positions')
        ->where('company_id', $company->id)->whereNull('deleted_at')
        ->where('name', 'like', '%' . $positionName . '%')
        ->get(['id', 'name']);
    if ($cand->count() === 0) { fwrite(STDERR, "Job position \"{$positionName}\" tidak ketemu di company ini. Bikin dulu, atau pakai --position=<ULID>.\n"); exit(1); }
    if ($cand->count() > 1) {
        fwrite(STDERR, "Lebih dari 1 job position cocok — pilih pakai --position=<ULID>:\n");
        foreach ($cand as $c) fwrite(STDERR, "  {$c->id}  {$c->name}\n");
        exit(1);
    }
    $pos = DB::table('m_job_positions')->where('id', $cand->first()->id)->first();
}
echo "Job Position: {$pos->name} ({$pos->id})\n\n";

// ---- rencana ----
$plan = []; $notFound = []; $already = 0;
foreach ($nips as $raw) {
    $nip = preg_replace('/[^0-9]/', '', $raw);
    $user = DB::table('users')->where('nip', $nip)->first(['id', 'name'])
        ?? DB::table('users')->where('nip', str_pad($nip, 11, '0', STR_PAD_LEFT))->first(['id', 'name']);
    if (! $user) { $notFound[] = $raw; continue; }

    $curName = DB::table('user_job_positions as ujp')
        ->join('m_job_positions as jp', 'ujp.job_position_id', '=', 'jp.id')
        ->where('ujp.user_id', $user->id)->where('ujp.status', 'active')
        ->orderByDesc('ujp.start_date')->value('jp.name');

    $profile = DB::table('user_profiles')->where('user_id', $user->id)->first(['job_position_id']);

    if ($profile && $profile->job_position_id === $pos->id && $curName === $pos->name) { $already++; continue; }

    $plan[] = [
        'user_id' => $user->id, 'nip' => $nip, 'nama' => $user->name,
        'from' => $curName ?: (($profile && $profile->job_position_id) ? '(job_position_id ada, ujp kosong)' : '(kosong)'),
    ];
}

echo "=== AKAN DIUBAH ke '{$pos->name}' (" . count($plan) . ") ===\n";
printf("%-14s | %-32s | %s\n", 'NIP', 'NAMA', 'JABATAN SEKARANG');
foreach ($plan as $x) printf("%-14s | %-32s | %s\n", $x['nip'], mb_substr($x['nama'], 0, 32), $x['from']);
echo "\n=== SUDAH sesuai: {$already}\n";
echo "\n=== NIP TIDAK KETEMU (" . count($notFound) . ") ===\n";
foreach ($notFound as $x) echo "  {$x}\n";

if (! $apply) {
    echo "\n>> DRY RUN. Tidak ada yang ditulis. Tambahkan --apply untuk mengubah " . count($plan) . " baris.\n";
    exit(0);
}

echo "\n>> APPLY: mengubah " . count($plan) . " baris...\n";
DB::transaction(function () use ($plan, $pos) {
    $now = now();
    foreach ($plan as $x) {
        if (DB::table('user_profiles')->where('user_id', $x['user_id'])->exists()) {
            DB::table('user_profiles')->where('user_id', $x['user_id'])
                ->update(['job_position_id' => $pos->id, 'updated_at' => $now]);
        } else {
            DB::table('user_profiles')->insert([
                'id' => (string) Str::ulid(), 'user_id' => $x['user_id'],
                'job_position_id' => $pos->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('user_job_positions')->where('user_id', $x['user_id'])
            ->where('job_position_id', '!=', $pos->id)->delete();

        $exist = DB::table('user_job_positions')
            ->where('user_id', $x['user_id'])->where('job_position_id', $pos->id)->first(['id']);
        if ($exist) {
            DB::table('user_job_positions')->where('id', $exist->id)
                ->update(['status' => 'active', 'updated_at' => $now]);
        } else {
            DB::table('user_job_positions')->insert([
                'id' => (string) Str::ulid(), 'user_id' => $x['user_id'], 'job_position_id' => $pos->id,
                'assigned_by' => null, 'start_date' => $now, 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        echo "  OK  {$x['nip']}  {$x['nama']}  ({$x['from']} -> {$pos->name})\n";
    }
});
echo "SELESAI. " . count($plan) . " baris diperbarui.\n";
