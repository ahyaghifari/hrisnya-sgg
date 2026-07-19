@props(['title' => 'Beranda'])

<header class="pwa-app-bar">
    <div class="pwa-app-bar-inner">
        <div class="pwa-app-bar-brand">
            <img src="{{ asset('assets/app-icon-dark.png') }}" alt="" style="height:28px;width:auto;flex-shrink:0;">
            <span class="pwa-app-bar-title">{{ $title }}</span>
        </div>

        <div class="pwa-app-bar-actions">
            <x-theme-toggle variant="bar" />
            {{-- Tombol notifikasi disembunyikan sementara --}}
            {{-- <div class="pwa-app-bar-bell">
                <i class="fa-regular fa-bell"></i>
                <span class="pwa-app-bar-dot"></span>
            </div> --}}
        </div>
    </div>
</header>