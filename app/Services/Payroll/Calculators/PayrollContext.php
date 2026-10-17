<?php

namespace App\Services\Payroll\Calculators;

/**
 * State yang di-share antar-Calculator dalam 1 generate payroll (1 user, 1 periode).
 * Menggantikan pola lama `&$grossSalary`/`&$totalDeductions` (parameter reference dioper ke
 * 6+ method) — rawan lupa nambah di 1 handler, total meleset diam-diam tanpa error. Tiap
 * Calculator manggil addEarning()/addDeduction() eksplisit, orchestrator baca total dari sini.
 */
class PayrollContext
{
    public float $grossSalary = 0.0;
    public float $totalDeductions = 0.0;

    /** source_type komponen yang barisnya sudah di-edit manual (edited_manually=true) di
     * payroll SEBELUMNYA - Calculator komponen ini WAJIB di-skip saat Generate/Regenerate,
     * baris lamanya sudah dibawa pindah apa adanya ke payroll baru (lihat
     * PayrollService::generateForEmployee()). */
    public array $protectedSourceTypes = [];

    public function addEarning(float $amount): void
    {
        $this->grossSalary += $amount;
    }

    public function addDeduction(float $amount): void
    {
        $this->totalDeductions += $amount;
    }

    public function isProtected(?string $sourceType): bool
    {
        return $sourceType !== null && in_array($sourceType, $this->protectedSourceTypes, true);
    }
}
