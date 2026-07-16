@props(['title', 'subtitle' => null, 'back' => null, 'size' => 'default'])

<div class="mb-6 flex items-center justify-between flex-wrap gap-2">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }} · <span id="live-clock"></span></p>
        {{-- Judul disembunyikan di mobile: sudah tampil di pwa-app-bar-title, dobel kalau ditulis lagi di sini. --}}
        <h1 class="hidden md:block {{ $size === 'lg' ? 'text-3xl' : 'text-[20px]' }} font-extrabold text-slate-900 tracking-tight">{{ $title }}</h1>
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
