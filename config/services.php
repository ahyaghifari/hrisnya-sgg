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
    | Sistem Rekrutmen (eksternal, port 8001)
    |--------------------------------------------------------------------------
    |
    | Push job opening dari HRIS ke sistem Rekrutmen - lihat
    | planning/2026-08-09-integrasi-job-opening-ke-rekrutmen.md. Endpoint di sisi
    | Rekrutmen belum tentu sudah jadi - panggilan sengaja tidak boleh menggagalkan
    | aksi HRIS kalau base_url/token kosong atau request gagal (lihat
    | RekrutmenSyncService::sync()).
    |
    */
    'rekrutmen' => [
        'base_url' => env('REKRUTMEN_API_URL'),
        'token' => env('REKRUTMEN_API_TOKEN'),
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
    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realms' => env('KEYCLOAK_REALM'),
    ],

];
