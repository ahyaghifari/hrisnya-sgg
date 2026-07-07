@props(['title' => 'Beranda'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    <title>{{ $title }} — HRIS Syifa Global Group</title>
    <link rel="manifest" href="/pwa/manifest.json">
    <meta name="theme-color" content="#042c6c">
    <link rel="apple-touch-icon" href="/pwa/icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Syifa HRIS">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @livewireStyles
    @stack('head')
</head>
<body class="site pwa-body pb-24">

    <div class="page-deco" aria-hidden="true">
        <div class="deco-a"></div><div class="deco-b"></div><div class="deco-c"></div><div class="deco-d"></div>
    </div>

    <x-pwa-app-bar :title="$title" />

    <main class="page-content pwa-content">
        {{ $slot }}
    </main>

    <x-pwa-bottom-nav />

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/pwa/sw.js', { scope: '/pwa/' });
        }
    </script>
    @stack('scripts')
</body>
</html>
