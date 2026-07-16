<?php

namespace App\Enum;

/**
 * Cara cast `settings.value` (selalu disimpan sebagai string) saat dibaca lewat
 * Setting::resolve(). Lihat knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md.
 */
enum SettingTypeEnum: string
{
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case STRING = 'string';

    public function label(): string
    {
        return match ($this) {
            self::INTEGER => 'Angka Bulat',
            self::DECIMAL => 'Angka Desimal',
            self::BOOLEAN => 'Ya/Tidak',
            self::STRING => 'Teks',
        };
    }
}
