<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Enkripsi kolom nominal payroll pakai key terpisah dari APP_KEY (`payroll.encrypter`, lihat
 * AppServiceProvider::registerPayrollEncrypter()). Nilai disimpan sebagai ciphertext teks,
 * dibaca balik sebagai float. Kolom DB wajib `text` (ciphertext base64 lebih panjang dari
 * decimal), bukan `decimal`.
 */
class PayrollEncrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) app('payroll.encrypter')->decryptString($value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return app('payroll.encrypter')->encryptString((string) round((float) $value, 2));
    }
}
