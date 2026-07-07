@php
    $photoPath = auth()->user()->profile?->profile_picture[0] ?? null;
    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
@endphp
<nav class="pwa-bottom-nav">
    <a href="{{ route('pwa.home') }}" wire:navigate class="pwa-nav-item {{ request()->routeIs('pwa.home') ? 'active' : '' }}">
        <i class="fa-solid fa-house"></i>
    </a>
    <a href="{{ route('pwa.home') }}#absen-section" class="pwa-nav-item">
        <i class="fa-regular fa-clock"></i>
    </a>
    <a href="{{ route('pwa.pengajuan') }}" wire:navigate class="pwa-nav-item {{ request()->routeIs('pwa.pengajuan') ? 'active' : '' }}">
        <i class="fa-regular fa-file-lines"></i>
    </a>
    <a href="{{ route('pwa.home') }}#kursus-section" class="pwa-nav-item">
        <i class="fa-solid fa-graduation-cap"></i>
    </a>
    <a href="{{ route('pwa.akun') }}" wire:navigate class="pwa-nav-item {{ request()->routeIs('pwa.akun*') || request()->routeIs('pwa.data-diri') ? 'active' : '' }}">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="Akun" class="pwa-nav-avatar">
        @else
            <i class="fa-regular fa-circle-user"></i>
        @endif
    </a>
</nav>
