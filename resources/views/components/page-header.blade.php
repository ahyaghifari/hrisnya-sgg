@props(['title', 'subtitle' => null, 'back' => null, 'size' => 'default'])

{{-- Di mobile judulnya disembunyikan (ada di app bar) dan baris jam sudah dimatikan, jadi tanpa subtitle/back
header kosong tapi mb-6 tetap makan jarak: sembunyikan seluruhnya di mobile. --}}
<div @class(['mb-6 items-center justify-between flex-wrap gap-2', 'hidden md:flex' => ! $subtitle && ! $back, 'flex' => $subtitle || $back])>
    <div>
        {{-- Tanggal + jam live disembunyikan sementara (instruksi user 2026-10-05); tick di layout.blade.php
        aman karena cek elemen #live-clock null. Kembalikan baris ini untuk mengaktifkan lagi:
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }} · <span id="live-clock"></span></p> --}}
        {{-- Judul disembunyikan di mobile: sudah tampil di pwa-app-bar-title, dobel kalau ditulis lagi di sini. --}}
        <h1 class="hidden md:block lg:text-2xl xl:3-xl font-extrabold text-slate-900 tracking-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-xs text-slate-600 mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($back)
        <a href="{{ $back }}" wire:navigate style="font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;white-space:nowrap;">
            <i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali
        </a>
    @endif
</div>
