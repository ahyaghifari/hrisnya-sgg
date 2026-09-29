<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">

    @php
        $status = $isRevoked
            ? ['label' => 'DICABUT', 'theme' => 'slate', 'desc' => 'Dokumen ini sudah dibatalkan dan tidak berlaku lagi.']
            : (! $hashMatches
                ? ['label' => 'DATA BERUBAH', 'theme' => 'red', 'desc' => 'Isi dokumen sudah berbeda dari saat ditandatangani. Jangan dipakai sebagai acuan.']
                : ['label' => 'SAH', 'theme' => 'emerald', 'desc' => 'Tanda tangan ini asli dan isi dokumen belum diubah sejak ditandatangani.']);

        $themes = [
            'emerald' => ['bg' => 'bg-emerald-600', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-100'],
            'amber' => ['bg' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-100'],
            'red' => ['bg' => 'bg-red-600', 'soft' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-100'],
            'slate' => ['bg' => 'bg-slate-500', 'soft' => 'bg-slate-100', 'text' => 'text-slate-600', 'ring' => 'ring-slate-200'],
        ][$status['theme']];

        $roleLabel = $signable?->expectedSignerRoles()[$signature->signer_role] ?? $signature->signer_role;
    @endphp

    <div class="max-w-md mx-auto px-4 py-8 sm:py-12">

        <div class="flex items-center gap-3 mb-5">
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-9 h-9 rounded-lg object-contain bg-white shadow-sm">
            <div>
                <p class="text-[11px] font-medium text-slate-400 uppercase tracking-wide leading-none">Syifa Global Group</p>
                <h1 class="text-base font-bold text-slate-900 leading-tight">Verifikasi Dokumen</h1>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

            {{-- Status hero --}}
            <div class="{{ $themes['bg'] }} px-5 pt-5 pb-6 text-white relative overflow-hidden">
                <div class="relative flex items-start gap-3">
                    <div class="w-9 h-9 shrink-0 rounded-full bg-white/20 flex items-center justify-center">
                        @if ($status['theme'] === 'emerald')
                            <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @elseif ($status['theme'] === 'red')
                            <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5"><path d="M6 6l12 12M18 6L6 18" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5"><path d="M12 9v4M12 16.5h.01" stroke="white" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[15px] font-extrabold tracking-wide">{{ $status['label'] }}</p>
                        <p class="text-[12.5px] text-white/85 mt-0.5 leading-snug">{{ $status['desc'] }}</p>
                    </div>
                </div>
            </div>

            <div class="px-5 py-5 space-y-5">

                {{-- Dokumen --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-0.5">Jenis Dokumen</p>
                        <p class="text-sm font-bold text-slate-900">{{ $signable?->documentTypeLabel() ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-0.5">Nomor</p>
                        <p class="text-sm font-semibold text-slate-800 break-words">{{ $document->document_number }}</p>
                    </div>
                    <div>
                        <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-0.5">Tanggal Terbit</p>
                        <p class="text-sm font-semibold text-slate-800">{{ $document->issued_at->translatedFormat('d M Y') }}</p>
                    </div>
                </div>

                @if ($isRevoked && $revokedReason)
                    <div class="rounded-xl {{ $themes['soft'] }} ring-1 {{ $themes['ring'] }} px-4 py-3">
                        <p class="text-[10.5px] {{ $themes['text'] }} uppercase tracking-wide font-semibold mb-0.5">Alasan Dicabut</p>
                        <p class="text-sm text-slate-700">{{ $revokedReason }}</p>
                    </div>
                @endif

                {{-- 1 kode = 1 TTD personal (instruksi user 2026-09-29) - halaman ini cuma
                bicara soal TTD yang di-scan, bukan status TTD peran lain di dokumen yang sama. --}}
                <div>
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-2.5">Penanda Tangan</p>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 shrink-0 rounded-full bg-emerald-100 flex items-center justify-center mt-0.5">
                            <svg viewBox="0 0 24 24" fill="none" class="w-3.5 h-3.5"><path d="M5 13l4 4L19 7" stroke="#059669" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-medium">{{ $roleLabel }}</p>
                            <p class="text-sm font-semibold text-slate-900 leading-tight">{{ $signature->signer_name }}</p>
                            @if ($signature->signer_position)
                                <p class="text-xs text-slate-500">{{ $signature->signer_position }}</p>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-400 whitespace-nowrap shrink-0">{{ $signature->signed_at->translatedFormat('d M Y') }}<br><span class="text-slate-300">{{ $signature->signed_at->format('H:i') }}</span></p>
                    </div>
                </div>

                <div class="pt-3 border-t border-dashed border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold">Kode Verifikasi</p>
                        <p class="text-sm font-mono font-bold text-slate-700 tracking-wide">{{ $code }}</p>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-[11px] text-slate-400 text-center mt-5 leading-relaxed px-2">
            Halaman ini cuma menampilkan status keabsahan, bukan isi lengkap dokumen. Tanda tangan
            elektronik pada dokumen ini bersifat internal perusahaan (non-tersertifikasi PSrE).
        </p>
    </div>

</body>
</html>
