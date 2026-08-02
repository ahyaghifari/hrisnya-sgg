<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;

/**
 * gaji_pokok = pangkat_salaries.base_salary * (1 + gaji_pokok_kenaikan_per_kelas% * steps)
 * steps = kelas.level user - pangkat.startKelas.level, DIBATASI 0..(maxKelas.level -
 * startKelas.level) — kelas di bawah startKelas dianggap steps=0 (tidak dikurangi), kelas di
 * atas maxKelas dianggap sudah mentok di kelas maksimal pangkat itu (tidak terus membesar tanpa
 * batas kalau ada kesalahan assign kelas terlalu tinggi ke seorang karyawan).
 *
 * Persentase kenaikan per kelas itu SATU nilai per company (setting `gaji_pokok_kenaikan_per_kelas`,
 * halaman Komponen Gaji) — sama untuk semua pangkat di company itu, beda company boleh beda
 * persen. start_kelas_id/max_kelas_id (FK ke m_kelas) tetap per-pangkat (halaman Mapping
 * Komponen Gaji, tab Gaji Pokok), karena rentang kelas memang beda tiap pangkat.
 *
 * ADDITIVE, bukan compound — dikonfirmasi user (analisis-payroll-4 §7.3): naik 2x kelas =
 * +16% dari base, BUKAN base*1.08^2.
 *
 * Prorate bulan pertama — berlaku untuk SEMUA kategori karyawan (bukan cuma Orientasi lagi):
 * kalau Tanggal Bergabung jatuh di tengah periode berjalan (join belakangan dari tanggal mulai
 * periode gaji), gaji_pokok dikali (hari kerja efektif sejak Tanggal Bergabung s.d. akhir
 * periode / jumlah hari di BULAN Tanggal Bergabung, mis. 30 buat Juni, 31 buat Juli) — dikoreksi
 * 2026-08-02, sebelumnya pembagi pakai angka tetap KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN,
 * sekarang jumlah hari kalender bulan itu. Pembilang dihitung sederhana: hari Senin-Sabtu
 * (exclude Minggu saja, tanpa lihat jadwal/roster/hari libur per karyawan) dari Tanggal Bergabung
 * s.d. akhir periode.
 */
class GajiPokokCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        // Gaji Pokok butuh Pangkat (jenjang jabatan) + Kelas (level dalam pangkat itu) - dua-duanya
        // FK opsional di UserProfiles, kalau salah satu kosong berarti data karyawan belum lengkap.
        $pangkat = $user->profile?->pangkat;
        $kelas = $user->profile?->kelas;

        // Tidak ada Pangkat/Kelas = tidak bisa hitung Gaji Pokok sama sekali, skip diam-diam
        // (karyawan itu memang belum eligible, bukan error).
        if (! $pangkat || ! $kelas) {
            return;
        }

        // Nominal dasar Gaji Pokok untuk Pangkat ini (1 Pangkat = 1 base_salary, sebelum kenaikan kelas).
        $pangkatSalary = $pangkat->pangkatSalary;
        // Persen kenaikan per kelas, company-scoped lewat Setting (halaman Komponen Gaji) - contoh 8 berarti 8%/kelas.
        $rate = (float) Setting::resolve('gaji_pokok_kenaikan_per_kelas', $period->company_id, default: 0) / 100;
        // Kelas awal (level terendah) yang jadi acuan Pangkat ini - level di titik ini dianggap "langkah 0" (belum ada kenaikan).
        $startLevel = $pangkat->startKelas?->level;

        // Skip aman kalau fondasi datanya belum lengkap — Gaji Pokok Rp0 yang ke-generate
        // diam-diam itu bug serius, jangan asumsikan 0 sebagai default "aman".
        if (! $pangkatSalary || $kelas->level === null || $startLevel === null) {
            return;
        }

        // Jumlah langkah kenaikan = level Kelas karyawan dikurangi level Kelas awal Pangkat, minimal 0
        // (kalau Kelas karyawan lebih rendah dari Kelas awal, dianggap belum ada kenaikan sama sekali).
        $steps = max(0, $kelas->level - $startLevel);
        // Level Kelas maksimal yang masih diakui Pangkat ini (batas atas kenaikan).
        $maxLevel = $pangkat->maxKelas?->level;

        // Kalau Pangkat punya batas maksimal, jumlah langkah dipangkas supaya tidak melebihi
        // selisih maxLevel-startLevel (mencegah kenaikan tak terbatas kalau Kelas karyawan
        // salah di-assign kelewat tinggi).
        if ($maxLevel !== null) {
            $steps = min($steps, max(0, $maxLevel - $startLevel));
        }

        // Rumus akhir: base_salary + (base_salary * rate * jumlah langkah) - additive, bukan compound.
        $amount = round((float) $pangkatSalary->base_salary * (1 + $rate * $steps), 2);
        // Catatan yang tampil di slip gaji, dimulai dari base + jumlah langkah kenaikan kelas.
        $note = "Base Rp" . number_format((float) $pangkatSalary->base_salary, 0, ',', '.') . ", kelas +{$steps} langkah";

        // Tanggal Bergabung karyawan - kolom ini string biasa di DB (bukan di-cast Carbon oleh model),
        // jadi harus di-parse manual di sini sebelum dipakai untuk perbandingan tanggal.
        $joiningDate = $user->profile?->joining_date ? Carbon::parse($user->profile->joining_date) : null;

        // Syarat prorate: (1) Tanggal Bergabung ADA, (2) Tanggal Bergabung jatuh SETELAH awal
        // periode (baru masuk di tengah bulan, bukan sudah dari awal), dan (3) Tanggal Bergabung
        // masih di dalam rentang periode ini (bukan periode yang sudah lewat). Berlaku untuk
        // semua kategori karyawan.
        if (
            $joiningDate
            && $joiningDate->gt($period->start_date)
            && $joiningDate->lte($period->end_date)
        ) {
            // Pembagi = jumlah hari kalender di BULAN Tanggal Bergabung (30/31/28/29, tergantung
            // bulannya) - bukan angka tetap dari setting lagi.
            $daysInJoiningMonth = $joiningDate->daysInMonth;

            // Jumlah hari kerja (Senin-Sabtu) sejak Tanggal Bergabung sampai akhir periode ini.
            $workedDays = $this->countWorkDays($joiningDate, $period->end_date);
            // Rasio dikunci maksimal 1 (100%) - kalau workedDays kebetulan melebihi jumlah hari
            // di bulan itu, tidak boleh dibayar lebih dari 1 bulan penuh.
            $amount = round($amount * min(1, $workedDays / $daysInJoiningMonth), 2);
            // Catatan prorate ditambahkan supaya kelihatan di slip gaji kenapa nominalnya dipotong.
            $note .= ", prorate bulan pertama {$workedDays}/{$daysInJoiningMonth} hari sejak {$joiningDate->format('d-m-Y')}";
        }

        // Kalau hasil akhir tetap 0 (mis. base_salary 0), jangan generate PayrollItem sama sekali -
        // baris Rp0 di slip gaji cuma bikin bingung, bukan informasi yang berguna.
        if ($amount <= 0) {
            return;
        }

        // Simpan sebagai baris PayrollItem (komponen penghasilan) di slip gaji karyawan ini.
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::GAJI_POKOK->label(),
            'type' => PayrollComponentEnum::GAJI_POKOK->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => $note,
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        // Tambahkan ke akumulator gross salary payroll berjalan (dipakai komponen lain, mis. potongan
        // yang basisnya persentase dari gross).
        $context->addEarning($amount);
    }

    /** Hari Senin-Sabtu dari $from s.d. $to (inklusif) - Minggu di-exclude, tanpa lihat hari libur/jadwal. */
    private function countWorkDays(Carbon $from, Carbon $to): int
    {
        // Counter hari kerja yang ditemukan.
        $count = 0;

        // Iterasi tiap tanggal dari $from sampai $to (inklusif), satu hari per langkah.
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            // Semua hari dihitung kerja KECUALI Minggu - tidak melihat hari libur nasional atau
            // jadwal/roster spesifik karyawan (sengaja sederhana, lihat docblock kelas).
            if (! $date->isSunday()) {
                $count++;
            }
        }

        return $count;
    }
}
