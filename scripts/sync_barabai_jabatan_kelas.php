<?php

/**
 * Assign JABATAN (job position) + KELAS untuk karyawan RSU Syifa Medika Barabai.
 *
 * Sumber data: knowledge/sync-untuk-barabai.xlsx (di-embed di bawah supaya script bisa
 * jalan di server produksi tanpa perlu ikut file Excel-nya). ID JABATAN sudah dibuat lebih
 * dulu di produksi & dipakai apa adanya. KELAS di-resolve dari level angka -> m_kelas milik
 * company Barabai (level 1..12), jadi tidak bergantung ID kelas antar-environment.
 *
 * Jalankan (DRY-RUN dulu, tidak menulis apa pun):
 *   php artisan tinker scripts/sync_barabai_jabatan_kelas.php
 *
 * Kalau hasil dry-run sudah benar, set $apply = true lalu jalankan lagi.
 * Idempoten: aman dijalankan berkali-kali.
 */

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Support\Facades\DB;

$apply = false; // <-- ubah ke true untuk benar-benar menyimpan

// NAMA, NIP, LEVEL KELAS, ID JABATAN, (nama jabatan hanya untuk laporan)
$rows = [
    ['dr. Annanda Rimasari', '01042023485', 10, '01m1ded8c93rz1xbwdm4gc433z', 'Direktur RSU Syifa Medika Barabai'],
    ['drg. Bayu Yordha Senggara', '20220226838', 7, '01m1dtb9kxs27gkh5pknhgmwsv', 'Kepala Bidang Pelayanan dan Penunjang Medik'],
    ['Bima Anugerah Putra, A.Md., Fis', '26052025740', 3, '01m1dtcg2z8ec0132sz3g9phra', 'Kepala Bagian Administrasi dan Umum'],
    ['Eka Zatmika Putri', '01022022821', 3, '01m1dtd8wgvagnjgmz8exvbre6', 'Kepala Keuangan'],
    ['Nurizma Nadia, S.Psi', '05052025727', 3, '01m1dtf6dqt0e0c1y7rmm40826', 'Kasubag Kepegawaian'],
    ['Siti Sarah, S.A.B', '04052026027', 3, '01m1dtg57mmk4856ctzmnpbg8d', 'Kasubag Humas Promkes'],
    ['Ismy Auliany, S.T', '05122025813', 3, '01m1dwn8tkzw598g9xtkqgpmz7', 'Kepala IPSRS, Sanitarian & CS'],
    ['Ahmad Ridani', '23022026855', 2, '01m1dwpeb3ecsr09tep0r56g4v', 'Staff IPSRS'],
    ['Riky Eriyanto', '18052026035', 2, '01m1dwpeb3ecsr09tep0r56g4v', 'Staff IPSRS'],
    ['Elysa Aziza Putri, A. Md., Tem.', '20012026846', 2, '01m1dwr13pde28tcjj9vc2mfr1', 'Elektromedis'],
    ['Arianto', '02062026037', 1, '01m1dwpeb3ecsr09tep0r56g4v', 'Staff IPSRS'],
    ['Nur Saidah Afifah, S.Tr.Kes', '23032026891', 2, '01m1dwt8sv7z988sh35rraptec', 'Sanitasi'],
    ['Dendy Novi', '12022026839', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Harry Indra Wahyudi', '12022026840', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Muhammad Ilham Fitriady', '12022026841', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Muhammad Arienanda Riswan', '25022026872', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Ahmad Darhel Al Ramadanoor', '23032026890', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Ridwan Islami', '01042026000', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Muhammad Fikri Haikal', '01042026001', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Muhammad Rijalul Fikri', '01042026002', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Budi Setiawan', '20042026021', 2, '01m1dwscs5t157p2z5755gj6jz', 'Cleaning Service'],
    ['Norpujati Syawaluna Syifa, A.Md.Kes', '13042026012', 3, '01m1dx89cbxrkn5y8z78kvy5yg', 'Kepala CSSD & Laundry'],
    ['Rabiyatul Adawiyah', '25022026871', 2, '01m1dx9g5qejpgj693h4q9mn85', 'Staff laundry'],
    ['Nanda Irma Hulandari, S.Ikom', '04122025817', 3, '01m1dxahs2nj4f1zhgx10b31yz', 'Kepala Resepsionis, Kasir & Rekam Medik'],
    ['Risda Ariyanti, S.A.RS.', '23022026863', 2, '01m1dxgevn8v52pab0mzpte6zc', 'Resepsionis'],
    ['Muhammad Ibnu Mulia, A.Md.RMIK', '23022026864', 2, '01m1dxhhkrjzcax2cfc6ftt0ey', 'Rekam Medis'],
    ['Aredha Azwati, S.M', '13042026014', 2, '01m1dxjgf3wensy1nzzqa93rc6', 'Resepsionis dan Kasir'],
    ['Baidatun Nisa, S.A.P', '04052026029', 2, '01m1dxjgf3wensy1nzzqa93rc6', 'Resepsionis dan Kasir'],
    ['Apt. Akhmad Junaidi S. Farm', '04122025812', 3, '01m1dxqpy3ypk8ek53pw7z2mdh', 'Kepala Instalasi Farmasi'],
    ['Apt. Eka Karlina Putri, S. Farm', '23022026857', 2, '01m1dxrvq5szvzt5b43ha7wf3j', 'Apoteker Pendamping'],
    ['Apt. Mita Kurniati, S. Farm', '23022026858', 2, '01m1dxrvq5szvzt5b43ha7wf3j', 'Apoteker Pendamping'],
    ['Elsa Sapitri, A.Md. Farm', '23022026859', 2, '01m1dy75aa60bj2hq36x9ftykn', 'TTK'],
    ['Erlina Apriliani, A.Md. Farm', '23022026860', 2, '01m1dy75aa60bj2hq36x9ftykn', 'TTK'],
    ['Ahmad Syarif Firgiawan, A.Md.Farm', '23022026860', 2, '01m1dy75aa60bj2hq36x9ftykn', 'TTK'],
    ['Muhammad Rudi, A.Md.Farm', '01042026999', 2, '01m1dy75aa60bj2hq36x9ftykn', 'TTK'],
    ['Iqbal Ilmi, S.Tr.Kes', '04122025818', 3, '01m1dy863q6hqh38rqjzm3reay', 'Kepala Instalasi Laboratorium'],
    ['Hanipa Sapria, A.Md. Ak', '23022026867', 2, '01m1dy90pqx00re1n9exh888gg', 'Analis Kesehatan'],
    ['Amalia Salsabila, A.Md. Kes', '01042026003', 2, '01m1dy90pqx00re1n9exh888gg', 'Analis Kesehatan'],
    ['Annisa Mahmudah, A.Md.Kes', '20042026020', 2, '01m1dy90pqx00re1n9exh888gg', 'Analis Kesehatan'],
    ['Nor Alimah, S.Kep., Ns', '09012026825', 3, '01m1dydzmsx5fjcks7t2peby4z', 'Kepala Ruangan Rawat Inap dan ICU'],
    ['Gita Ria Safitri, A.Md.,Kep', '23022026851', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Luthfia Afifah, A.Md.Kep', '16032026984', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Muhammad Rahman Hidayat, A.Md.Kep', '01042026991', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Alif Arji, S.Kep.,Ns', '13042026016', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Muhammad Rizky, S,Kep.,Ns', '16032026989', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Niswatun Hasanah, A.Md.,Kep', '9032026880', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Nova Aisyah A.Md.Kep', '6032026875', 2, '01m1dyg12hn3wnzv6jhb8xj81y', 'Perawat Ruangan Rawat Inap & ICU'],
    ['Apri Wahdini, A.Md.Kep', '04122025816', 3, '01m1dyh28hxdt59b19x6gwyc5g', 'Kepala IGD'],
    ['Ainul Fitri, S. Kep., Ns', '23022026849', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Annisa Rizky Saputri, S. Kep., Ns', '23022026850', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Hendra Alfianor, S. Kep., Ns', '23022026853', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Muhammad Riswan Rahmani, A.Md.,Kep', '23022026854', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Herly Yanor, A.Md.Kep', '13042026017', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Gleadys Merieta Putri, S.Kep., Ns', '13042026018', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Fatimatuz Zahra, A.Md.Kep', '16032026990', 2, '01m1dyhy1fh982ax37yp1taq8b', 'Perawat IGD'],
    ['Ahmad Zulfakar Rahman, S.Kep., Ns', '04122025814', 3, '01m1dykaeg445h59x1d0edks4w', 'Kepala IBS'],
    ["Abu Ja'far Yazid, A.Md.Kep", '16032026986', 2, '01m1dymdqkg8k7qh95dzpt4r1f', 'Perawat IBS'],
    ['Muhammad Rahim Hidayat, A.Md.Kep', '01042026992', 2, '01m1dymdqkg8k7qh95dzpt4r1f', 'Perawat IBS'],
    ['Hidayah, A.Md.,Kep', '16042026007', 2, '01m1dymdqkg8k7qh95dzpt4r1f', 'Perawat IBS'],
    ['Amanda Cahaya Kencana, S.Kep., Ns', '04122025815', 3, '01m1dypv8fwp5ms7mq2xe761zt', 'Kepala Ruangan Rawat Jalan'],
    ['Hasni Dinniya, S.Kep,. Ns', '23022026847', 2, '01m1dyr7967pam07f9kxr1409c', 'Perawat Poliklinik Rawat Jalan'],
    ['Meldawati, A.Md.,Kep', '23022026848', 2, '01m1dyr7967pam07f9kxr1409c', 'Perawat Poliklinik Rawat Jalan'],
    ['Nur Halimah, S.Kep.,Ns', '16032026987', 2, '01m1dyr7967pam07f9kxr1409c', 'Perawat Poliklinik Rawat Jalan'],
    ['Seri Rahayu, A.Md.Kep', '16032026988', 2, '01m1dyr7967pam07f9kxr1409c', 'Perawat Poliklinik Rawat Jalan'],
    ['Dila Angellina Purilucsa, A.Md.Fis', '23022026866', 2, '01m1dys9ym1zaxheybpnck2kpb', 'Fisioterapis'],
    ['Alyaa Ardhia Garini, A.Md.Kes', '24022026870', 2, '01m1dyt6qrfpeyyb2ek3ntzh02', 'Perawat Gigi'],
    ['Riska Fauzanah, A.Md.Kes', '13042026019', 2, '01m1dyt6qrfpeyyb2ek3ntzh02', 'Perawat Gigi'],
    ['Norlaila Hayati, S.Si.T', '11092021380', 3, '01m1dyv4b34vnjn69kbcaj2rk3', 'Bidan Ponek'],
    ['Mailinda Sari, A.Md.Keb', '05102023553', 3, '01m1dyw9mfpmg9k6dkxvtd85ky', 'Koordinator VK & Kebidanan'],
    ['Dewi Noviawati, A.Md.,Keb', '18022026842', 2, '01m1dyx08c9be7py0xh5n5fej7', 'Bidan'],
    ['Devina Ramadanty, A.Md.,Keb', '18022026843', 2, '01m1dyx08c9be7py0xh5n5fej7', 'Bidan'],
    ['Maya Marcelina Kartini Anin, A.Md.,Keb', '18022026844', 2, '01m1dyx08c9be7py0xh5n5fej7', 'Bidan'],
    ['Maulia Hasanah, A.Md.,Keb', '18022026845', 2, '01m1dyx08c9be7py0xh5n5fej7', 'Bidan'],
    ['Maymunah HS, S.Gz', '04122025819', 3, '01m1dyxzk1sdvyjstg1ydqv1tq', 'Kepala Instalasi Gizi & Pantry'],
    ['Ili Ariyanti', '23022026861', 2, '01m1dyyrda40jrak8rjynv9p0g', 'Staff Pantry'],
    ['Sarmila', '10082026053', 1, '01m1dyyrda40jrak8rjynv9p0g', 'Staff Pantry'],
    ['Annisa Fahrina', '23022026862', 2, '01m1dyyrda40jrak8rjynv9p0g', 'Staff Pantry'],
    ['Nor Ridha Khalifah, A.Md.,Rad', '25022026876', 2, '01m1dz4b3bcjvrywj0q9w31cdm', 'Radiografer'],
    ['Fitri Febrianty, A.Md..,Rad', '02032026877', 2, '01m1dz4b3bcjvrywj0q9w31cdm', 'Radiografer'],
    ['Fahrin Apriansyah', '23022026868', 2, '01m1dz6tvkcrde4ewsdpx0rx0q', 'Supir'],
    ['Ahmad Syaukani', '23032026892', 2, '01m1dz6tvkcrde4ewsdpx0rx0q', 'Supir'],
    ['Fauzi Rahman', '01042020287', 3, '01m1dz7kjh0xd1887ac3m3x58y', 'Kepala Unit Satpam'],
    ['Muhaymin', '23022026869', 2, '01m1dz90qxkfgkkar86qy2vnps', 'Satpam'],
    ['Tamjidillah', '09032026881', 2, '01m1dz90qxkfgkkar86qy2vnps', 'Satpam'],
    ['Muhammad Mukni', '09032026882', 2, '01m1dz90qxkfgkkar86qy2vnps', 'Satpam'],
    ['Muhammad Najhan Nor', '13082026054', 1, '01m1dz90qxkfgkkar86qy2vnps', 'Satpam'],
    ['Sarawani', '18052026036', 2, '01m1dz90qxkfgkkar86qy2vnps', 'Satpam'],
    ['Muhammad Fitriadi, S.Gz', '23022026856', 2, '01m1dz9z4thay9j85mfatnre7c', 'Logistik'],
];

// ── Resolusi master data ──────────────────────────────────────────────────────
$company = Companies::where('name', 'like', '%Barabai%')->first();
if (! $company) {
    echo "GAGAL: company Barabai tidak ditemukan.\n";
    return;
}

$kelasByLevel = Kelas::where('company_id', $company->id)->get()->keyBy('level');

$norm = fn (?string $s) => strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $s));

echo ($apply ? '### MODE: APPLY (menulis ke database)' : '### MODE: DRY-RUN (tidak menulis apa pun)') . "\n";
echo "Company : {$company->name} ({$company->id})\n";
echo str_repeat('-', 100) . "\n";

$plan = [];   // aksi yang akan dijalankan
$skips = [];  // baris yang dilewati + alasan

foreach ($rows as [$nama, $nip, $level, $jabatanId, $jabatanNama]) {
    $nip = trim($nip);

    // 1. User: cocokkan NIP dulu (scoped Barabai), fallback ke nama persis.
    $candidates = User::withTrashed()->where('company_id', $company->id)->where('nip', $nip)->get();
    $matchBy = 'NIP';
    if ($candidates->isEmpty()) {
        $candidates = User::withTrashed()->where('company_id', $company->id)
            ->get()
            ->filter(fn ($u) => $norm($u->name) === $norm($nama))
            ->values();
        $matchBy = 'nama';
    }

    if ($candidates->isEmpty()) {
        $skips[] = "{$nama} (NIP {$nip}) — user tidak ditemukan di company Barabai";
        continue;
    }
    if ($candidates->count() > 1) {
        $skips[] = "{$nama} (NIP {$nip}) — cocok >1 user ({$candidates->count()}), perlu dicek manual";
        continue;
    }
    $user = $candidates->first();
    if ($user->trashed()) {
        $skips[] = "{$nama} (NIP {$nip}) — user berstatus terhapus (soft-deleted), dilewati";
        continue;
    }

    // 2. Jabatan
    $jp = JobPositions::find($jabatanId);
    if (! $jp) {
        $skips[] = "{$nama} — ID jabatan {$jabatanId} tidak ada di database";
        continue;
    }
    if ($jp->company_id !== $company->id) {
        $skips[] = "{$nama} — jabatan {$jp->name} bukan milik company Barabai";
        continue;
    }

    // 3. Kelas dari level
    $kelas = $kelasByLevel->get((int) $level);
    if (! $kelas) {
        $skips[] = "{$nama} — kelas level {$level} tidak ada di m_kelas company Barabai";
        continue;
    }

    $nameFlag = $norm($user->name) === $norm($nama) ? '' : "  [!] nama DB \"{$user->name}\" != xlsx";

    $plan[] = compact('user', 'jp', 'kelas', 'nama', 'nip', 'matchBy', 'nameFlag');
    printf("OK  %-38s NIP %-12s  match:%-4s  -> %-42s  Kelas %s%s\n",
        mb_substr($nama, 0, 38), $nip, $matchBy, mb_substr($jp->name, 0, 42), $kelas->level, $nameFlag);
}

echo str_repeat('-', 100) . "\n";
echo 'Siap di-apply : ' . count($plan) . " baris\n";
echo 'Dilewati      : ' . count($skips) . " baris\n";
foreach ($skips as $s) {
    echo "  - {$s}\n";
}

if (! $apply) {
    echo "\nDRY-RUN selesai. Set \$apply = true di baris atas untuk menyimpan.\n";
    return;
}

// ── APPLY ────────────────────────────────────────────────────────────────────
$done = 0;
DB::transaction(function () use ($plan, &$done) {
    foreach ($plan as $p) {
        /** @var \App\Models\User $user */
        $user = $p['user'];
        $jp = $p['jp'];
        $kelas = $p['kelas'];

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'job_position_id' => $jp->id,
                'kelas_id' => $kelas->id,
                'org_structure_id' => $jp->org_structure_id, // ikut struktur jabatan (sinkron)
            ],
        );

        // Riwayat jabatan: nonaktifkan yang lain, pastikan 1 baris aktif untuk jabatan ini.
        $user->jobPositions()->where('job_position_id', '!=', $jp->id)->delete();

        $existing = UserJobPositions::where('user_id', $user->id)
            ->where('job_position_id', $jp->id)
            ->first();

        UserJobPositions::updateOrCreate(
            ['user_id' => $user->id, 'job_position_id' => $jp->id],
            [
                'assigned_by' => null,
                'start_date' => $existing?->start_date ?? now()->toDateString(),
                'end_date' => null,
                'status' => 'active',
            ],
        );

        $done++;
    }
});

echo "\nAPPLY selesai. {$done} karyawan diperbarui (jabatan + kelas).\n";
