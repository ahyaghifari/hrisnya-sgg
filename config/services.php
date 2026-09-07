<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sistem Rekrutmen (shared DB)
    |--------------------------------------------------------------------------
    |
    | Integrasi HRIS <-> Rekrutmen sekarang LANGSUNG ke DB (koneksi `recruitment`,
    | lihat App\Services\Rekrutmen\RecruitmentSync) — tidak ada API HTTP lagi.
    | `url` di sini cuma URL publik app pelamar buat tombol CTA di email notifikasi.
    |
    */
    'rekrutmen' => [
        // URL publik app pelamar (d:\Projects\rekrutmen) — dipakai buat tombol CTA di email
        // notifikasi (kerjakan tes, lihat jadwal, unggah offering). Kosong = tombol disembunyikan.
        'url' => env('REKRUTMEN_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Keycloak SSO (OpenID Connect)
    |--------------------------------------------------------------------------
    |
    | Login SSO opsional, jalan berdampingan dengan login lokal (email/NIP +
    | password). Dipakai App\Http\Controllers\Auth\KeycloakController lewat
    | Socialite provider 'keycloak' (socialiteproviders/keycloak). Client scope
    | 'profile' di Keycloak mengembalikan email, preferred_username, nip.
    |
    */
    'n8n' => [
        'user' => env('N8N_API_USER'),
        'password' => env('N8N_API_PASSWORD'),
    ],

    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realms' => env('KEYCLOAK_REALM'),
    ],

];
