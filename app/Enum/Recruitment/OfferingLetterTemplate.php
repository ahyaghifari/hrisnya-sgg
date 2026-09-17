<?php

namespace App\Enum\Recruitment;

/**
 * Gaya visual offering letter yang dipakai perusahaan — mirror App\Enums\OfferingLetterTemplate
 * project `rekrutmen` (nilai & nama view HARUS sama, cuma view blade-nya diport terpisah ke
 * HRIS di resources/views/pdf/rekrutmen/).
 */
enum OfferingLetterTemplate: string
{
    case Sgg = 'sgg';
    case Rs = 'rs';

    public function label(): string
    {
        return match ($this) {
            self::Sgg => 'Syifa Global Group (tabel kompensasi)',
            self::Rs => 'RSU Syifa Medika (list kompensasi)',
        };
    }

    public function view(): string
    {
        return match ($this) {
            self::Sgg => 'pdf.rekrutmen.offering-letter-sgg',
            self::Rs => 'pdf.rekrutmen.offering-letter-rs',
        };
    }
}
