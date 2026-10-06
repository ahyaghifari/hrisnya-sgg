<?php

namespace App\Enum\Doctor;

use Filament\Support\Contracts\HasLabel;

/** Jenis lisensi dokter (docs/doctor_management_domain_spec.md, Fase 3). Nilai juga dipakai sebagai document_type file scan-nya. */
enum DoctorLicenseType: string implements HasLabel
{
    case SIP = 'SIP';
    case STR = 'STR';
    case STR_KT = 'STR_KT';

    public function getLabel(): string
    {
        return match ($this) {
            self::SIP => 'SIP',
            self::STR => 'STR',
            self::STR_KT => 'STR-KT',
        };
    }
}
