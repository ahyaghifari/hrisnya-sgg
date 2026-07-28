@props(['title' => 'Syifa HRIS'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>{{ $title }} - HRIS Syifa Global Group</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @livewireStyles
</head>
<body class="site min-h-screen relative overflow-hidden">

    <div class="page-deco" aria-hidden="true">
        <div class="deco-a"></div><div class="deco-b"></div><div class="deco-c"></div><div class="deco-d"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-start justify-center px-4 py-8 sm:items-center">
        <div class="w-full max-w-[520px]">
            <div class="flex items-center justify-center gap-3 mb-4">
                <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-11 h-11 rounded-xl object-contain">
                <div class="leading-tight">
                    <span class="block text-[16px] font-bold text-slate-800">Syifa Global Group</span>
                </div>
            </div>

            <div class="bg-white rounded-[14px] px-4 py-3 mb-6 shadow-[0_1px_3px_rgba(0,0,0,0.06)] border border-slate-200/70">
                <img src="{{ asset('assets/companies.png') }}" alt="Perusahaan di bawah Syifa Global Group" class="w-full h-auto object-contain">
            </div>

            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
