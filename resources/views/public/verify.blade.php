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
                : ['label' => 'SAH', 'theme' => 'emerald', 'desc' => 'Dokumen ini asli dan belum diubah sejak ditandatangani.']);

        $themes = [
            'emerald' => ['bg' => 'bg-emerald-600', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-100'],
            'amber' => ['bg' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-100'],
            'red' => ['bg' => 'bg-red-600', 'soft' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-100'],
            'slate' => ['bg' => 'bg-slate-500', 'soft' => 'bg-slate-100', 'text' => 'text-slate-600', 'ring' => 'ring-slate-200'],
        ][$status['theme']];

        $signedRoles = $signatures->pluck('signer_role');
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

                @if (! $isRevoked && $pendingRoles->isNotEmpty())
                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3">
                        <p class="text-[10.5px] text-amber-700 uppercase tracking-wide font-semibold mb-0.5">Belum Lengkap</p>
                        <p class="text-sm text-amber-800">Menunggu tanda tangan: {{ $pendingRoles->values()->implode(', ') }}.</p>
                    </div>
                @endif

                {{-- Penanda tangan: dipetakan ke expectedSignerRoles biar peran yang BELUM
                tanda tangan ikut kelihatan (bukan cuma yang sudah), urutan tetap konsisten
                lintas render (instruksi user 2026-09-28: redesign halaman verifikasi). --}}
                <div>
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-semibold mb-2.5">Penanda Tangan</p>
                    <div class="space-y-2.5">
                        @foreach ($signable?->expectedSignerRoles() ?? [] as $role => $roleLabel)
                            @php $sig = $signatures->firstWhere('signer_role', $role); @endphp
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center mt-0.5 {{ $sig ? 'bg-emerald-100' : 'bg-slate-100' }}">
                                    @if ($sig)
                                        <svg viewBox="0 0 24 24" fill="none" class="w-3.5 h-3.5"><path d="M5 13l4 4L19 7" stroke="#059669" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10.5px] text-slate-400 uppercase tracking-wide font-medium">{{ $roleLabel }}</p>
                                    @if ($sig)
                                        <p class="text-sm font-semibold text-slate-900 leading-tight">{{ $sig->signer_name }}</p>
                                        @if ($sig->signer_position)
                                            <p class="text-xs text-slate-500">{{ $sig->signer_position }}</p>
                                        @endif
                                    @else
                                        <p class="text-sm text-slate-400 italic">Belum menandatangani</p>
                                    @endif
                                </div>
                                @if ($sig)
                                    <p class="text-[11px] text-slate-400 whitespace-nowrap shrink-0">{{ $sig->signed_at->translatedFormat('d M Y') }}<br><span class="text-slate-300">{{ $sig->signed_at->format('H:i') }}</span></p>
                                @endif
                            </div>
                        @endforeach
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
