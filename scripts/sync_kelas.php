<?php
/**
 * Samakan `user_profiles.kelas_id` di HRIS dengan kolom "Kelas" (P) di file gaji Excel.
 * Excel = sumber kebenaran.
 *
 * JALANKAN DI SERVER PRODUCTION (DB dev cuma data demo).
 *
 *   php scripts/sync_kelas.php "knowledge/gaji-agustus-banjarbaru.xlsx"            # DRY RUN (tidak menulis)
 *   php scripts/sync_kelas.php "knowledge/gaji-agustus-banjarbaru.xlsx" --apply   # tulis perubahan
 *   ... --company=<ULID>   # override company (default: cari "RSU Syifa Medika Banjarbaru")
 *
 * Aman:
 *  - Dry run dulu, tidak menulis apa pun sampai --apply.
 *  - --apply dibungkus 1 transaksi; update lewat query builder (bypass hook model Kelas).
 *  - Hanya menyentuh baris yang benar-benar berubah level-nya.
 *  - NIP di-match exact lalu zero-pad 11 digit. Yang tak ketemu / tak ada m_kelas level-nya
 *    dilaporkan, TIDAK ditebak.
 */

use Illuminate\Support\Facades\DB;

// Cari root repo HRIS (folder yang punya vendor/autoload.php). Override: env HRIS_ROOT.
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

use PhpOffice\PhpSpreadsheet\IOFactory;

$argsPos = [];
$apply = false;
$companyOpt = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') $apply = true;
    elseif (str_starts_with($a, '--company=')) $companyOpt = substr($a, 10);
    else $argsPos[] = $a;
}
$path = $argsPos[0] ?? null;
if (! $path || ! is_file($path)) {
    fwrite(STDERR, "File Excel tidak ditemukan. Usage: php sync_kelas.php <xlsx> [--apply] [--company=ULID]\n");
    exit(1);
}

// ---- company ----
$company = $companyOpt
    ? DB::table('m_companies')->where('id', $companyOpt)->first()
    : DB::table('m_companies')->where('name', 'RSU Syifa Medika Banjarbaru')->first();
if (! $company) { fwrite(STDERR, "Company tidak ketemu.\n"); exit(1); }
echo "Company: {$company->name} ({$company->id})\n";

// ---- m_kelas level -> id (company ini) ----
$kelasByLevel = [];
foreach (DB::table('m_kelas')->where('company_id', $company->id)->get(['id','name','level']) as $k) {
    if ($k->level !== null) $kelasByLevel[(int) $k->level] = $k;
}
ksort($kelasByLevel);
echo "m_kelas tersedia (level): " . implode(', ', array_keys($kelasByLevel)) . "\n\n";

// ---- baca Excel ----
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
    public function readCell($col, $row, $ws = ''): bool {
        return $row <= 540 && in_array($col, ['B','E','K','P'], true);
    }
});
$sheet = $reader->load($path)->getActiveSheet();

$norm = fn (string $s) => preg_replace('/[^0-9]/', '', preg_replace('/^D[A-Z]-?/i', '', trim($s)));

$plan = [];     // [nip, nama, from, to, kelasId]
$skipMitra = 0;
$noP = [];
$noUser = [];
$noKelasRow = [];
$already = 0;

for ($r = 4; $r <= 540; $r++) {
    $b = trim((string) $sheet->getCell('B'.$r)->getValue());
    $e = trim((string) $sheet->getCell('E'.$r)->getValue());
    $k = trim((string) $sheet->getCell('K'.$r)->getValue());
    $p = trim((string) $sheet->getCell('P'.$r)->getValue());
    if ($b === '' && $e === '') continue;
    if ($e === '') continue;                              // section header / trailer
    if (stripos($k, 'mitra') !== false) { $skipMitra++; continue; }

    if (! preg_match('/\d+/', $p, $m)) { $noP[] = "$b (row $r)"; continue; }
    $targetLevel = (int) $m[0];

    $nip = $norm($e);
    $user = DB::table('users')->where('nip', $nip)->first(['id','name'])
        ?? DB::table('users')->where('nip', str_pad($nip, 11, '0', STR_PAD_LEFT))->first(['id','name']);
    if (! $user) { $noUser[] = "$b | E=$e"; continue; }

    if (! isset($kelasByLevel[$targetLevel])) {
        $noKelasRow[] = "$b | butuh level $targetLevel (tidak ada m_kelas-nya di company ini)";
        continue;
    }
    $targetKelas = $kelasByLevel[$targetLevel];

    $profile = DB::table('user_profiles')->where('user_id', $user->id)->first(['id','kelas_id']);
    $curLevel = null;
    if ($profile && $profile->kelas_id) {
        $cur = DB::table('m_kelas')->where('id', $profile->kelas_id)->first(['level']);
        $curLevel = $cur?->level;
    }

    if ((int) $curLevel === $targetLevel) { $already++; continue; }

    $plan[] = [
        'user_id'  => $user->id,
        'nip'      => $nip,
        'nama'     => $b,
        'from'     => $curLevel === null ? '(kosong)' : $curLevel,
        'to'       => $targetLevel,
        'kelas_id' => $targetKelas->id,
        'has_profile' => (bool) $profile,
    ];
}

// ---- laporan ----
echo "=== AKAN DIUBAH (" . count($plan) . ") ===\n";
printf("%-14s | %-32s | %-8s | %-6s\n", 'NIP', 'NAMA', 'DARI', 'KE');
foreach ($plan as $x) printf("%-14s | %-32s | %-8s | %-6s%s\n", $x['nip'], mb_substr($x['nama'],0,32), $x['from'], $x['to'], $x['has_profile'] ? '' : '  [profile BELUM ADA - dilewati]');

echo "\n=== SUDAH SAMA: $already\n";
echo "=== Mitra dilewati: $skipMitra\n";

echo "\n=== KOLOM P KOSONG di Excel (" . count($noP) . ") ===\n";
foreach ($noP as $x) echo "  $x\n";

echo "\n=== USER TIDAK KETEMU by NIP (" . count($noUser) . ") ===\n";
foreach ($noUser as $x) echo "  $x\n";

echo "\n=== LEVEL TAK ADA m_kelas-nya (" . count($noKelasRow) . ") ===\n";
foreach ($noKelasRow as $x) echo "  $x\n";

// ---- apply ----
$writable = array_filter($plan, fn ($x) => $x['has_profile']);
if (! $apply) {
    echo "\n>> DRY RUN. Tidak ada yang ditulis. Tambahkan --apply untuk mengubah " . count($writable) . " baris.\n";
    exit(0);
}

echo "\n>> APPLY: mengubah " . count($writable) . " baris...\n";
DB::transaction(function () use ($writable) {
    foreach ($writable as $x) {
        DB::table('user_profiles')->where('user_id', $x['user_id'])->update([
            'kelas_id'   => $x['kelas_id'],
            'updated_at' => now(),
        ]);
        echo "  OK  {$x['nip']}  {$x['nama']}  {$x['from']} -> {$x['to']}\n";
    }
});
echo "SELESAI. " . count($writable) . " baris diperbarui.\n";
