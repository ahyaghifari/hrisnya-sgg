<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kolom lama users.name / users.nip / users.company_id
    |--------------------------------------------------------------------------
    |
    | null  (default): DETEKSI OTOMATIS dari skema (Schema::hasColumn('users', 'nip')), jadi tidak ada
    |        langkah manual yang bisa terlewat saat migration pencabutan kolom dijalankan.
    | true  (masa transisi): penulisan name/nip/company_id lewat model User disimpan ke employees DAN
    |        ke kolom users (cermin), sehingga rollback tanpa kehilangan data.
    | false (setelah kolom dicabut): hanya ke employees; kolom users tidak lagi ditulis atau dibaca.
    |
    | Isi lewat env IDENTITY_LEGACY_USER_COLUMNS hanya untuk memaksa salah satu mode. Proses yang hidup
    | lama (queue:work) menyimpan hasil deteksi - jalankan `php artisan queue:restart` setelah migration.
    |
    | Lihat App\Models\User (mutator name/nip/company_id) dan docs/roadmap_migrasi_bertahap_identity_hris.md.
    */
    'legacy_user_columns' => env('IDENTITY_LEGACY_USER_COLUMNS') === null
        ? null
        : filter_var(env('IDENTITY_LEGACY_USER_COLUMNS'), FILTER_VALIDATE_BOOLEAN),

];
