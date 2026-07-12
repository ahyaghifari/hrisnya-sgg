<?php

namespace App\Enum\Salary;

/**
 * Penjelas asal 1 baris payroll_items — dari salary component (assignment jabatan/pangkat),
 * kehadiran, insentif, dst. Baris lama sebelum kolom ini ada akan bernilai NULL.
 */
enum PayrollItemSourceEnum: string
{
    case BASE_SALARY = 'base_salary';
    case JOB_POSITION_ASSIGNMENT = 'job_position_assignment';
    case ATTENDANCE = 'attendance';
    case SERVICE_FEE = 'service_fee';
    case INCENTIVE = 'incentive';
    case FEE_CLAIM = 'fee_claim';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::BASE_SALARY => 'Gaji Pokok',
            self::JOB_POSITION_ASSIGNMENT => 'Komponen Jabatan',
            self::ATTENDANCE => 'Kehadiran',
            self::SERVICE_FEE => 'Jasa Pelayanan',
            self::INCENTIVE => 'Incentive',
            self::FEE_CLAIM => 'Fee Klaim',
            self::MANUAL => 'Manual',
        };
    }
}
