<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\EmployeeTypeCategory;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\GajiPokokKenaikanOverride;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Employee\Employee;
use App\Services\Payroll\Calculators\Concerns\ProratesFirstMonth;
use Carbon\Carbon;

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
 * & Pangkat tetap wajib terisi di Employee (syarat data lengkap, skip diam-diam kalau kosong)
 * walau nilainya sudah tidak dipakai di rumus ini.
 *
 * Rate CUMA berlaku untuk kategori Tetap & Kontrak (instruksi user 2026-08-22) - Orientasi tidak
 * dapat kenaikan sama sekali (rate dipaksa 0%, gaji_pokok = base_salary persis), termasuk yang
 * override lewat GajiPokokKenaikanOverride.
 *
 * Prorate bulan pertama — berlaku untuk SEMUA kategori karyawan (bukan cuma Orientasi lagi),
 * logic-nya di ProratesFirstMonth (dipakai bareng Tunjangan Jabatan/Transportasi/Masa Kerja
 * juga, dikonfirmasi user 2026-08-05 - awalnya cuma Gaji Pokok).
 *
 * Transisi Orientasi->Kontrak (instruksi user 2026-09-15) - setting `kontrak_prorate_metode`
 * company-scoped, CUMA berlaku bulan TTD kontrak (kontrak pertama milik karyawan, employee_contracts, dicari
 * lewat OrientationService::followUpContract):
 * - 'penuh' (default, = perilaku lama tanpa perubahan): kapanpun tanggal TTD-nya, bulan itu
 *   langsung dibayar Kontrak penuh (rate normal, tanpa prorate) - employee_type sudah ke-flip
 *   Kontrak duluan di Employee jadi ini otomatis kejadian tanpa kode tambahan.
 * - 'tiered': tanggal TTD 1-5 = tetap penuh Kontrak (sama seperti 'penuh'). 6-20 = prorate
 *   dicampur: porsi hari SEBELUM TTD dihitung rate Orientasi (0%, base_salary polos), porsi
 *   hari MULAI TTD dihitung rate Kontrak, dibagi divisor yang sama seperti ProratesFirstMonth
 *   (ResolvesProrateDivisor). >20 = bulan ini dibayar Orientasi PENUH (rate 0%, base_salary
 *   utuh tanpa prorate), Kontrak penuh baru mulai bulan berikutnya.
 */
class GajiPokokCalculator implements PayrollComponentCalculator
{
    use ProratesFirstMonth;

    public function calculate(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        [$amount, $note] = $this->resolveAmountAndNote($employee, $period);

        if ($amount === null || $amount <= 0) {
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

    /**
     * Nominal final Gaji Pokok (base x (1+rate), sudah termasuk prorate bulan pertama kalau
     * relevan) — TANPA membuat PayrollItem/menyentuh PayrollContext. Dipakai ULANG oleh
     * CutiCalculator (Teknik "THP" cuti melahirkan/keguguran, butuh baca "Gaji Pokok Akhir")
     * supaya tidak bergantung ke urutan Calculator lain atau keberadaan baris payroll_items
     * Gaji Pokok (bisa saja tidak ada, mis. Gaji Khusus override skip Calculator ini total).
     * Null kalau karyawan ini tidak eligible (Pangkat/Kelas kosong) atau hasilnya 0.
     */
    public function resolveAmount(Employee $employee, PayrollPeriod $period): ?float
    {
        return $this->resolveAmountAndNote($employee, $period)[0];
    }

    /** @return array{0: ?float, 1: ?string} */
    private function resolveAmountAndNote(Employee $employee, PayrollPeriod $period): array
    {
        // Gaji Pokok butuh Pangkat (jenjang jabatan) + Kelas (level dalam pangkat itu) - dua-duanya
        // FK opsional di Employee, kalau salah satu kosong berarti data karyawan belum lengkap.
        $pangkat = $employee->pangkat;
        $kelas = $employee->kelas;

        // Tidak ada Pangkat/Kelas = tidak bisa hitung Gaji Pokok sama sekali, skip diam-diam
        // (karyawan itu memang belum eligible, bukan error).
        if (! $pangkat || ! $kelas) {
            return [null, null];
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
        $category = $employee->employeeType?->category;
        $rate = ($category === null || $category === EmployeeTypeCategory::ORIENTASI)
            ? 0.0
            : (GajiPokokKenaikanOverride::activeRateFor($employee->id)
                ?? (float) Setting::resolve('gaji_pokok_kenaikan_per_kelas', $period->company_id, default: 8)) / 100;

        // Skip aman kalau fondasi datanya belum lengkap — Gaji Pokok Rp0 yang ke-generate
        // diam-diam itu bug serius, jangan asumsikan 0 sebagai default "aman". Kelas.level tetap
        // dicek walau tidak dipakai di rumus - syarat data lengkap karyawan belum berubah.
        if (! $pangkatSalary || $kelas->level === null) {
            return [null, null];
        }

        $baseSalary = (float) $pangkatSalary->base_salary;

        if ($transition = $this->contractTransitionAmount($employee, $period, $baseSalary, $rate)) {
            [$amount, $note] = $transition;
        } else {
            // Rumus akhir: base_salary * (1 + rate) - flat, TIDAK lagi dikali langkah Kelas.
            $amount = round($baseSalary * (1 + $rate), 2);
            $note = "Base Rp" . number_format($baseSalary, 0, ',', '.') . ', kenaikan ' . rtrim(rtrim(number_format($rate * 100, 2), '0'), '.') . '%';

            if ($prorate = $this->firstMonthProrate($employee, $period)) {
                $amount = round($amount * $prorate['ratio'], 2);
                $note .= ', ' . $prorate['note'];
            }
        }

        // Kalau hasil akhir tetap 0 (mis. base_salary 0), jangan generate PayrollItem sama sekali -
        // baris Rp0 di slip gaji cuma bikin bingung, bukan informasi yang berguna.
        if ($amount <= 0) {
            return [null, null];
        }

        return [$amount, $note];
    }

    /**
     * @return array{0: float, 1: string}|null null = tidak ada transisi kontrak bulan ini
     * (atau setting-nya 'penuh') - pakai jalur normal (rate x base_salary, prorate joining_date
     * kalau relevan). $rate di sini rate KONTRAK (sudah termasuk kenaikan), dipakai untuk porsi
     * hari setelah TTD - porsi sebelum TTD selalu rate 0% (Orientasi).
     */
    private function contractTransitionAmount(Employee $employee, PayrollPeriod $period, float $baseSalary, float $rate): ?array
    {
        // Cuma kontrak PERTAMA (Orientasi->Kontrak) yang relevan - perpanjangan kontrak (Kontrak
        // lama -> Kontrak baru) sudah dibayar rate Kontrak dari sebelumnya, tidak ada "porsi
        // Orientasi" yang perlu diprorate. Cek setting/kontrak-pertama/rentang-periode/tgl<=5
        // semua di resolveContractTransitionSignDate() (dipakai bareng Tunjangan
        // Jabatan/Transportasi/Masa Kerja juga - lihat ProratesFirstMonth).
        $signDate = $this->resolveContractTransitionSignDate($employee, $period);

        if (! $signDate) {
            return null;
        }

        $divisor = $this->prorateDivisor($period, $signDate);

        if ($divisor <= 0) {
            return null;
        }

        // >20 = bulan ini masih dianggap Orientasi PENUH (base_salary utuh, tanpa prorate) -
        // Kontrak penuh baru berlaku mulai periode berikutnya.
        if ($signDate->day > 20) {
            $amount = round($baseSalary, 2);
            $note = "TTD kontrak {$signDate->format('d-m-Y')} (>tgl 20): Orientasi penuh bulan ini, Kontrak penuh mulai bulan depan";

            return [$amount, $note];
        }

        // 6-20 = campur: hari sebelum TTD rate Orientasi (0%), hari mulai TTD rate Kontrak.
        $daysBefore = $this->countProrateWorkDays($period->start_date, $signDate->copy()->subDay());
        $daysAfter = $this->countProrateWorkDays($signDate, $period->end_date);

        $amount = round($baseSalary * ($daysBefore / $divisor), 2)
            + round($baseSalary * (1 + $rate) * ($daysAfter / $divisor), 2);
        $note = "TTD kontrak {$signDate->format('d-m-Y')} (tgl 6-20): prorate {$daysBefore} hari Orientasi + {$daysAfter} hari Kontrak dari {$divisor} {$this->prorateDivisorLabel($period)}";

        return [$amount, $note];
    }
}
