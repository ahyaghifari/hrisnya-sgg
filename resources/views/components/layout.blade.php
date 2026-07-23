@props(['title' => 'Syifa Employee Portal'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    <title>{{ $title }} - HRIS Syifa Global Group</title>
    <link rel="manifest" href="/pwa/manifest.json">
    <meta name="theme-color" content="#042c6c">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/pwa/icons/icon-192-v3.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Syifa HRIS">
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @livewireStyles
    @filamentStyles
    @filamentScripts
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
    @stack('head')
</head>
<body class="site">

    <div class="page-deco" aria-hidden="true">
        <div class="deco-a"></div><div class="deco-b"></div><div class="deco-c"></div><div class="deco-d"></div>
    </div>

    <div class="hidden md:block">
        <x-nav />
    </div>
    <div class="md:hidden">
        <x-pwa-app-bar :title="$title" />
    </div>

    <main class="page-content">
        {{ $slot }}
    </main>

    <div class="hidden md:block">
        <x-footer />
    </div>

    <div class="md:hidden">
        <x-pwa-bottom-nav />
    </div>

    @livewire('notifications')

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' });
        }

        // ponytail: submit needs a live request to the server (Livewire AJAX), so just
        // block it while offline instead of silently failing. No offline queue/sync yet.
        function pwaUpdateOfflineState() {
            const offline = !navigator.onLine;
            document.querySelectorAll('form[wire\\:submit]').forEach((form) => {
                const btn = form.querySelector('button[type="submit"]');
                if (!btn) return;
                btn.disabled = offline;
                let notice = form.querySelector('.pwa-offline-notice');
                if (offline && !notice) {
                    notice = document.createElement('span');
                    notice.className = 'pwa-offline-notice';
                    notice.style.cssText = 'color:#dc2626;font-size:12px;font-weight:600;';
                    notice.textContent = 'Tidak bisa mengajukan saat offline';
                    btn.insertAdjacentElement('beforebegin', notice);
                } else if (!offline && notice) {
                    notice.remove();
                }
            });
        }
        window.addEventListener('online', pwaUpdateOfflineState);
        window.addEventListener('offline', pwaUpdateOfflineState);
        document.addEventListener('livewire:navigated', pwaUpdateOfflineState);
        pwaUpdateOfflineState();

        // ponytail: mitigasi "GET method not allowed for livewire/update" yang kadang muncul
        // kalau user reload manual (F5) persis saat sebuah update Livewire (mis. ganti filter)
        // masih menggantung — reload di tengah request itu kadang bikin browser mengarah ke
        // endpoint AJAX-nya sendiri alih-alih halaman aslinya. Bukan perbaikan akar masalah
        // (itu ada di internal Livewire), cuma menahan user reload selagi masih ada request
        // yang jalan lewat dialog konfirmasi browser bawaan. Upgrade livewire/livewire kalau
        // versi lebih baru sudah menutup race condition-nya.
        window.__pendingLivewireRequests = 0;
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ respond, fail }) => {
                window.__pendingLivewireRequests++;
                // respond() tidak terpanggil kalau fetch()-nya sendiri gagal total (network
                // error/offline) — cuma fail() yang jalan di kasus itu. Guard `settled` supaya
                // tidak dobel-kurang kalau dua-duanya sempat terpanggil untuk request yang sama.
                let settled = false;
                let release = () => {
                    if (settled) return;
                    settled = true;
                    window.__pendingLivewireRequests = Math.max(0, window.__pendingLivewireRequests - 1);
                };
                respond(release);
                fail(release);
            });
        });
        window.addEventListener('beforeunload', (e) => {
            if (window.__pendingLivewireRequests > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        function pwaTickClock() {
            const el = document.getElementById('live-clock');
            if (el) el.textContent = new Date().toLocaleTimeString('id-ID');
        }
        clearInterval(window.__liveClockInterval);
        pwaTickClock();
        window.__liveClockInterval = setInterval(pwaTickClock, 1000);
        document.addEventListener('livewire:navigated', pwaTickClock);

        function toggleTheme() {
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        }
        document.addEventListener('livewire:navigated', function () {
            const dark = localStorage.getItem('theme') === 'dark';
            document.documentElement.classList.toggle('dark', dark);
        });
    </script>
    @stack('scripts')
</body>
</html>
