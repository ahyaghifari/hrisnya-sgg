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
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-11 h-11 p-1.5 rounded-xl object-contain bg-white shadow-sm ring-1 ring-slate-200">
            <div>
                <p class="text-[11px] font-medium text-slate-400 uppercase tracking-wide leading-none">Syifa Global Group</p>
                <h1 class="text-base font-bold text-slate-900 leading-tight">Verifikasi Dokumen</h1>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-amber-600 px-5 pt-5 pb-6 text-white">
                <p class="text-[15px] font-extrabold tracking-wide">TANDA TANGAN DIBATALKAN</p>
                <p class="text-[12.5px] text-white/90 mt-0.5 leading-snug">Dokumen ini direvisi setelah ditandatangani, sehingga tanda tangan ini tidak berlaku lagi. Mintalah versi terbaru kepada penerbit dokumen.</p>
            </div>

            <div class="px-5 py-5 space-y-3">
                <div>
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold">Dokumen</p>
                    <p class="text-sm font-bold text-slate-800">{{ $signable?->documentTypeLabel() ?? '-' }}</p>
                    @if ($signable?->nomor)<p class="text-xs text-slate-500 break-all">{{ $signable->nomor }}</p>@endif
                </div>
                <div>
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold">Penanda tangan yang dibatalkan</p>
                    <p class="text-sm font-bold text-slate-800">{{ $voided->signer_name }}</p>
                    @if ($voided->signer_position)<p class="text-xs text-slate-500">{{ $voided->signer_position }}</p>@endif
                    <p class="text-xs text-slate-500">Ditandatangani {{ $voided->signed_at->translatedFormat('d M Y H:i') }}, dibatalkan {{ $voided->voided_at->translatedFormat('d M Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold">Alasan revisi</p>
                    <p class="text-sm text-slate-700">{{ $voided->reason }}</p>
                </div>
                <div>
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-1">Kode yang dipindai</p>
                    <p class="text-sm font-mono font-bold text-slate-700 break-all">{{ $code }}</p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
