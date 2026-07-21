<?php

namespace App\Enum;

enum LsmCourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    /** Warna badge — Draft netral, Published hijau (menandakan sudah tayang ke peserta). */
    public function badgeColor(): array
    {
        return match ($this) {
            self::Draft => ['bg' => 'var(--surface-muted)', 'text' => 'var(--text-muted)'],
            self::Published => ['bg' => '#dcfce7', 'text' => '#15803d'],
        };
    }
}
