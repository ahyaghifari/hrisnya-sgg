<?php

namespace App\Enum\Payroll;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Jenis penjamin omset. Menentukan persentase omset yang masuk ke pool Jasa Pelayanan
 * (JasaPelayananCalculator): BPJS 2,5%, Asuransi/Umum 7,5%. Persentase di-hardcode di sini
 * (keputusan user 2026-10-04) - bukan config per company.
 */
enum OmsetPayerType: string implements HasLabel
{
    case BPJS = 'bpjs';
    case UMUM = 'umum';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::BPJS => 'BPJS',
            self::UMUM => 'Asuransi/Umum',
        };
    }

    /** Pecahan omset yang jadi dasar Jasa Pelayanan. */
    public function rate(): float
    {
        return match ($this) {
            self::BPJS => 0.025,
            self::UMUM => 0.075,
        };
    }
}
