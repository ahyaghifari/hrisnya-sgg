<?php

namespace App\Support;

/**
 * Normalisasi input jam bebas-format ke "H:i" (24 jam, leading zero) supaya lolos
 * rule `date_format:H:i`. Terima "5:30 PM", "05:30pm", "8:00", "17:5" dst.
 * Kalau tidak bisa di-parse, kembalikan apa adanya biar validasi yang menolak.
 */
class TimeInput
{
    private const FORMATS = ['G:i', 'g:i A', 'g:iA', 'g:i a', 'g:ia', 'h:i A', 'h:iA'];

    public static function normalize(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            return $value;
        }

        foreach (self::FORMATS as $format) {
            $dt = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();

            if ($dt !== false && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $dt->format('H:i');
            }
        }

        return $value;
    }
}
