<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\EmployeeTypeCategory;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\GajiPokokKenaikanOverride;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\Calculators\Concerns\ProratesFirstMonth;

/**
 * gaji_pokok = pangkat_salaries.base_salary * (1 + rate)
 * rate = GajiPokokKenaikanOverride (master per-user, halaman "Kenaikan Gaji Pokok") ??
 * Setting `gaji_pokok_kenaikan_per_kelas` (per company, halaman Komponen Gaji, default 8%).
 * Urutan lengkap kalau termasuk Gaji Khusus (PayrollComponentOverride, skip Calculator ini
 * total kalau ada baris aktif): Gaji Khusus -> GajiPokokKenaikanOverride -> Setting company.
 * Tier "skema per kelas" diabaikan dulu (instruksi user 2026-08-22, lihat catatan di bawah) -
 * kalau nanti diaktifkan lagi, taruh di antara GajiPokokKenaikanOverride & Setting company.
 *
 * Kenaikan berdasarkan LANGKAH KELAS di-terminate (instruksi user 2026-08-22) - rate sekarang
 * flat, TIDAK dikali lagi dengan selisih level Kelas karyawan terhadap Kelas awal Pangkat. Kelas
 * & Pangkat tetap wajib terisi di UserProfiles (syarat data lengkap, skip diam-diam kalau kosong)
 * walau nilainya sudah tidak dipakai di rumus ini.
 *
 * Rate CUMA berlaku untuk kategori Tetap & Kontrak (instruksi user 2026-08-22) - Orientasi tidak
 * dapat kenaikan sama sekali (rate dipaksa 0%, gaji_pokok = base_salary persis), termasuk yang
 * override lewat GajiPokokKenaikanOverride.
 *
 * Prorate bulan pertama — berlaku untuk SEMUA kategori karyawan (bukan cuma Orientasi lagi),
 * logic-nya di ProratesFirstMonth (dipakai bareng Tunjangan Jabatan/Transportasi/Masa Kerja
 * juga, dikonfirmasi user 2026-08-05 - awalnya cuma Gaji Pokok).
 */
class GajiPokokCalculator implements PayrollComponentCalculator
{
    use ProratesFirstMonth;

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

        // Nominal dasar Gaji Pokok untuk Pangkat ini (1 Pangkat = 1 base_salary).
        $pangkatSalary = $pangkat->pangkatSalary;
        // Persen kenaikan flat, company-scoped lewat Setting (halaman Komponen Gaji) - contoh 8
        // berarti +8%. Default 8% kalau company belum pernah setting. GajiPokokKenaikanOverride
        // (master, halaman "Kenaikan Gaji Pokok") bisa override rate ini per-user (mis. rate
        // lebih tinggi untuk 1 orang tertentu terlepas dari setting company-nya). Orientasi
        // tidak dapat kenaikan sama sekali. Fail-safe kalau tipe karyawan belum di-assign
        // (employeeType null, data belum lengkap) - rate WAJIB 0%, bukan diam-diam dapat
        // kenaikan penuh cuma karena bukan Orientasi secara eksplisit. Docblock di atas bilang
        // "rate cuma berlaku Tetap & Kontrak" - null bukan salah satu dari itu, harus ikut
        // diblok sama seperti Orientasi.
        $category = $user->profile?->employeeType?->category;
        $rate = ($category === null || $category === EmployeeTypeCategory::ORIENTASI)
            ? 0.0
            : (GajiPokokKenaikanOverride::activeRateFor($user->id)
                ?? (float) Setting::resolve('gaji_pokok_kenaikan_per_kelas', $period->company_id, default: 8)) / 100;

        // Skip aman kalau fondasi datanya belum lengkap — Gaji Pokok Rp0 yang ke-generate
        // diam-diam itu bug serius, jangan asumsikan 0 sebagai default "aman". Kelas.level tetap
        // dicek walau tidak dipakai di rumus - syarat data lengkap karyawan belum berubah.
        if (! $pangkatSalary || $kelas->level === null) {
            return;
        }

        // Rumus akhir: base_salary * (1 + rate) - flat, TIDAK lagi dikali langkah Kelas.
        $amount = round((float) $pangkatSalary->base_salary * (1 + $rate), 2);
        $note = "Base Rp" . number_format((float) $pangkatSalary->base_salary, 0, ',', '.') . ', kenaikan ' . rtrim(rtrim(number_format($rate * 100, 2), '0'), '.') . '%';

        if ($prorate = $this->firstMonthProrate($user, $period)) {
            $amount = round($amount * $prorate['ratio'], 2);
            $note .= ', ' . $prorate['note'];
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
}
