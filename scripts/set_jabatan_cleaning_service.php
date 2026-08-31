<?php
/**
 * Set jabatan = "Cleaning Service" untuk daftar NIP di RSU Syifa Medika Banjarbaru.
 * Mengikuti pola EditKaryawan: update user_profiles.job_position_id + swap baris
 * user_job_positions (hapus jabatan lain, updateOrCreate baris Cleaning Service status active).
 *
 * JALANKAN DI SERVER PRODUCTION.
 *
 *   php scripts/set_jabatan_cleaning_service.php            # DRY RUN (tidak menulis)
 *   php scripts/set_jabatan_cleaning_service.php --apply    # tulis perubahan
 *   ... --company=<ULID>    override company (default: cari "RSU Syifa Medika Banjarbaru")
 *   ... --position=<ULID>   override job position (default: cari nama "Cleaning Service")
 *   env HRIS_ROOT=<path repo>  kalau file tidak di root & tidak di scripts/
 *
 * Aman: dry-run dulu; --apply dibungkus 1 transaksi; NIP di-match exact lalu zero-pad 11;
 * yang tak ketemu / sudah Cleaning Service dilaporkan, tidak disentuh.
 */

use Illuminate\Support\Facades\DB;

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
$apply = false; $companyOpt = null; $positionOpt = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') $apply = true;
    elseif (str_starts_with($a, '--company=')) $companyOpt = substr($a, 10);
    elseif (str_starts_with($a, '--position=')) $positionOpt = substr($a, 11);
}

// ---- daftar NIP (dari user, 2026-08-31) ----
$nips = <<<NIPS
07122016020
01082017067
01122017101
16042018124
06082018142
12012019186
12012019191
24012020271
15022020274
17052022438
19122022470
2062023498
02022023499
02022023497
02022023500
11082023527
11082023528
11082023529
14082023536
21092023544
16102023570
16102023569
22102023572
01122023584
01122023586
22012024594
11032024614
05062024639
05062024638
01072024645
15072024647
17022025710
21042025721
13052025732
20052025738
24072025764
21112025799
14012026824
12022026836
25032026893
NIPS;
$nips = array_values(array_filter(array_map('trim', explode("\n", $nips))));
echo "NIP di daftar: " . count($nips) . "\n";

// ---- company ----
$company = $companyOpt
    ? DB::table('m_companies')->where('id', $companyOpt)->first()
    : DB::table('m_companies')->where('name', 'RSU Syifa Medika Banjarbaru')->first();
if (! $company) { fwrite(STDERR, "Company tidak ketemu.\n"); exit(1); }
echo "Company: {$company->name} ({$company->id})\n";

// ---- job position "Cleaning Service" ----
if ($positionOpt) {
    $pos = DB::table('m_job_positions')->where('id', $positionOpt)->whereNull('deleted_at')->first();
} else {
    // Nama persis "Cleaning Service" dulu (hindari ketimpa "Koordinator Cleaning Service" dll).
    $exact = DB::table('m_job_positions')
        ->where('company_id', $company->id)->whereNull('deleted_at')
        ->whereRaw('LOWER(name) = ?', ['cleaning service'])
        ->get(['id', 'name']);
    $cand = $exact->count() === 1 ? $exact : DB::table('m_job_positions')
        ->where('company_id', $company->id)
        ->whereNull('deleted_at')
        ->where('name', 'like', '%cleaning service%')
        ->get(['id', 'name']);
    if ($cand->count() === 0) { fwrite(STDERR, "Job position 'Cleaning Service' tidak ketemu di company ini. Bikin dulu, atau pakai --position=<ULID>.\n"); exit(1); }
    if ($cand->count() > 1) {
        fwrite(STDERR, "Lebih dari 1 job position cocok — pilih pakai --position=<ULID>:\n");
        foreach ($cand as $c) fwrite(STDERR, "  {$c->id}  {$c->name}\n");
        exit(1);
    }
    $pos = DB::table('m_job_positions')->where('id', $cand->first()->id)->first();
}
echo "Job Position: {$pos->name} ({$pos->id})\n\n";

// ---- rencana ----
$plan = []; $notFound = []; $already = 0; $noProfile = [];
foreach ($nips as $raw) {
    $nip = preg_replace('/[^0-9]/', '', $raw);
    $user = DB::table('users')->where('nip', $nip)->first(['id', 'name'])
        ?? DB::table('users')->where('nip', str_pad($nip, 11, '0', STR_PAD_LEFT))->first(['id', 'name']);
    if (! $user) { $notFound[] = $raw; continue; }

    // jabatan aktif sekarang
    $curName = DB::table('user_job_positions as ujp')
        ->join('m_job_positions as jp', 'ujp.job_position_id', '=', 'jp.id')
        ->where('ujp.user_id', $user->id)
        ->where('ujp.status', 'active')
        ->orderByDesc('ujp.start_date')
        ->value('jp.name');

    $profile = DB::table('user_profiles')->where('user_id', $user->id)->first(['id', 'job_position_id']);
    if (! $profile) $noProfile[] = "{$raw} | {$user->name}";

    if ($profile && $profile->job_position_id === $pos->id && $curName === $pos->name) { $already++; continue; }

    $plan[] = [
        'user_id' => $user->id,
        'nip'     => $nip,
        'nama'    => $user->name,
        'from'    => $curName ?: ($profile && $profile->job_position_id ? '(job_position_id ada, ujp kosong)' : '(kosong)'),
    ];
}

echo "=== AKAN DIUBAH ke '{$pos->name}' (" . count($plan) . ") ===\n";
printf("%-14s | %-32s | %s\n", 'NIP', 'NAMA', 'JABATAN SEKARANG');
foreach ($plan as $x) printf("%-14s | %-32s | %s\n", $x['nip'], mb_substr($x['nama'], 0, 32), $x['from']);

echo "\n=== SUDAH Cleaning Service: {$already}\n";

echo "\n=== NIP TIDAK KETEMU (" . count($notFound) . ") ===\n";
foreach ($notFound as $x) echo "  {$x}\n";

if ($noProfile) {
    echo "\n=== user_profiles BELUM ADA (" . count($noProfile) . ") — akan dibuat saat --apply ===\n";
    foreach ($noProfile as $x) echo "  {$x}\n";
}

if (! $apply) {
    echo "\n>> DRY RUN. Tidak ada yang ditulis. Tambahkan --apply untuk mengubah " . count($plan) . " baris.\n";
    exit(0);
}

echo "\n>> APPLY: mengubah " . count($plan) . " baris...\n";
DB::transaction(function () use ($plan, $pos) {
    $now = now();
    foreach ($plan as $x) {
        // 1) user_profiles.job_position_id
        $has = DB::table('user_profiles')->where('user_id', $x['user_id'])->exists();
        if ($has) {
            DB::table('user_profiles')->where('user_id', $x['user_id'])
                ->update(['job_position_id' => $pos->id, 'updated_at' => $now]);
        } else {
            DB::table('user_profiles')->insert([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'user_id' => $x['user_id'],
                'job_position_id' => $pos->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 2) user_job_positions: hapus jabatan lain, pastikan baris Cleaning Service active
        DB::table('user_job_positions')
            ->where('user_id', $x['user_id'])
            ->where('job_position_id', '!=', $pos->id)
            ->delete();

        $exist = DB::table('user_job_positions')
            ->where('user_id', $x['user_id'])
            ->where('job_position_id', $pos->id)
            ->first(['id']);
        if ($exist) {
            DB::table('user_job_positions')->where('id', $exist->id)
                ->update(['status' => 'active', 'updated_at' => $now]);
        } else {
            DB::table('user_job_positions')->insert([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'user_id' => $x['user_id'],
                'job_position_id' => $pos->id,
                'assigned_by' => null,
                'start_date' => $now,
                'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        echo "  OK  {$x['nip']}  {$x['nama']}  ({$x['from']} -> {$pos->name})\n";
    }
});
echo "SELESAI. " . count($plan) . " baris diperbarui.\n";
