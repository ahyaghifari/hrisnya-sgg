<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">

    <div class="max-w-md mx-auto px-4 py-8 sm:py-12">
        <div class="flex items-center gap-3 mb-5">
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-9 h-9 rounded-lg object-contain bg-white shadow-sm">
            <div>
                <p class="text-[11px] font-medium text-slate-400 uppercase tracking-wide leading-none">Syifa Global Group</p>
                <h1 class="text-base font-bold text-slate-900 leading-tight">Verifikasi Dokumen</h1>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-slate-500 px-5 pt-5 pb-6 text-white">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 shrink-0 rounded-full bg-white/20 flex items-center justify-center">
                        <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5"><path d="M12 9v4M12 16.5h.01" stroke="white" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/></svg>
                    </div>
                    <div>
                        <p class="text-[15px] font-extrabold tracking-wide">Kode verifikasi tidak ditemukan</p>
                        <p class="text-[12.5px] text-white/85 mt-0.5 leading-snug">Kode ini tidak cocok dengan dokumen mana pun.</p>
                    </div>
                </div>
            </div>

            <div class="px-5 py-5">
                <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-1">Kode yang dipindai</p>
                <p class="text-sm font-mono font-bold text-slate-700 break-all">{{ $code }}</p>
                <p class="text-xs text-slate-500 mt-3 leading-relaxed">Periksa kembali QR atau tautan yang dipakai - bisa jadi salah ketik, atau dokumennya belum pernah diterbitkan dari sistem ini.</p>
            </div>
        </div>
    </div>

</body>
</html>
