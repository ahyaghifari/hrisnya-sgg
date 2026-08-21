<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\ExecutiveAllowance;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Baris pendapatan/potongan manual khusus User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS (Presiden
 * Direktur & Sekretaris Direktur) - lihat ExecutiveAllowance. Always-on sama pola
 * FeeClaimCalculator (tidak digate lewat company_payroll_components) karena roster-nya sendiri
 * yang menentukan ada-tidaknya baris, bukan toggle per company - aman dipanggil untuk SEMUA
 * user tiap generate, no-op kalau rosternya kosong (karyawan biasa).
 *
 * source_type ikut `ExecutiveAllowance.component` - null buat baris "Manual" (label bebas,
 * ditaruh di akhir urutan slip sama seperti adjustment lain di PayrollService::componentRows()),
 * TAPI diisi PayrollComponentEnum-nya kalau baris ini dipilih dari "Dari Komponen Gaji" (mis.
 * Gaji Pokok) - biar urutannya di slip/export ikut posisi baku komponen itu, bukan selalu
 * kebuang ke bawah (bug ditemukan 2026-08-21: Gaji Pokok eksekutif selalu di bawah Tunjangan
 * Masa Kerja walau harusnya duluan).
 */
class ExecutiveAllowanceCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $rows = ExecutiveAllowance::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        foreach ($rows as $row) {
            $amount = (float) $row->amount;

            if ($amount <= 0) {
                continue;
            }

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $row->label,
                'type' => $row->type,
                'quantity' => 1,
                'amount' => $amount,
                'note' => $row->note,
                'source_type' => $row->component,
            ]);

            if ($row->type === PayrollComponentTypeEnum::EARNING->value) {
                $context->addEarning($amount);
            } else {
                $context->addDeduction($amount);
            }
        }
    }
}
