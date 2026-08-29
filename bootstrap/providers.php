<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    // Panel /candidate (jalur pendaftaran/lamaran pelamar langsung ke HRIS) dinonaktifkan
    // 2026-08-28 — digantikan modul Rekrutmen (portal Livewire, data dari project Rekrutmen
    // eksternal). File/model/resource-nya SENGAJA tidak dihapus; aktifkan lagi cukup dengan
    // uncomment baris di bawah kalau perlu.
    // App\Providers\Filament\CandidatePanelProvider::class,
];
