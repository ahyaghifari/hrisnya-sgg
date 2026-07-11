@props(['title' => 'Beranda'])

<header class="pwa-app-bar">
    <div class="pwa-app-bar-inner">
        <div class="pwa-app-bar-brand">
            <button type="button" class="pwa-app-bar-toggle" onclick="togglePwaSidebar()" aria-label="Buka menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="pwa-app-bar-title">{{ $title }}</span>
        </div>

        <div class="pwa-app-bar-actions">
            <x-theme-toggle variant="bar" />
            <div class="pwa-app-bar-bell">
                <i class="fa-regular fa-bell"></i>
                <span class="pwa-app-bar-dot"></span>
            </div>
        </div>
    </div>
</header>