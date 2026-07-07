@props(['title' => 'Beranda'])

@php
    $initials = collect(explode(' ', auth()->user()->name))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<header class="pwa-app-bar">
    <div class="pwa-app-bar-inner">
        <div class="pwa-app-bar-brand">
            <div class="pwa-app-bar-logo">S</div>
            <span class="pwa-app-bar-title">{{ $title }}</span>
        </div>

        <div class="pwa-app-bar-actions">
            <div class="pwa-app-bar-bell">
                <i class="fa-regular fa-bell"></i>
                <span class="pwa-app-bar-dot"></span>
            </div>
            <a href="{{ route('pwa.akun') }}" wire:navigate class="pwa-app-bar-avatar">{{ $initials }}</a>
        </div>
    </div>
</header>