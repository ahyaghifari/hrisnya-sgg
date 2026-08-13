<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payroll Encryption Key
    |--------------------------------------------------------------------------
    |
    | Key terpisah dari APP_KEY, khusus buat enkripsi kolom nominal payroll
    | (PayrollEncrypted cast). Kompromi APP_KEY (dipakai session/cookie/cache
    | juga) gak otomatis bongkar nominal payroll. Generate: base64 32 byte
    | random, sama format kayak APP_KEY (mis. `base64:...`).
    |
    */

    'key' => env('PAYROLL_ENCRYPTION_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Payroll Previous Keys
    |--------------------------------------------------------------------------
    |
    | Key lama, dipisah koma. Dipakai buat fallback decrypt data lama pas
    | 'key' di atas dirotasi ke key baru — data yang dienkripsi key lama
    | tetap kebaca. Encrypt baru selalu pakai 'key' aktif, gak pernah pakai
    | previous keys.
    |
    */

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('PAYROLL_PREVIOUS_KEYS', ''))
        ),
    ],

];
