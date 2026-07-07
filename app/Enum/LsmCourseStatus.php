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

}
