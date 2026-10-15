@props(['title' => 'Beranda'])

<header class="pwa-app-bar">
    <div class="pwa-app-bar-inner">
        <div class="pwa-app-bar-brand">
            <span class="pwa-app-bar-title">{{ $title }}</span>
        </div>

        <div class="pwa-app-bar-actions">
            <x-theme-toggle variant="bar" />
            <livewire:notification-bell variant="mobile" :key="'notif-bell-mobile'" />
            <x-mascot-guide variant="bar" />
        </div>
    </div>
</header>