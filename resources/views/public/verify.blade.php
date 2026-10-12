<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">

    @php
        $status = $isRevoked
            ? ['label' => 'DICABUT', 'theme' => 'slate', 'desc' => 'Dokumen ini sudah dibatalkan dan tidak berlaku lagi.']
            : (! $hashMatches
                ? ['label' => 'DATA BERUBAH', 'theme' => 'red', 'desc' => 'Isi dokumen sudah berbeda dari saat ditandatangani. Jangan dipakai sebagai acuan.']
                : ['label' => 'TERVERIFIKASI', 'theme' => 'emerald', 'desc' => 'Tanda tangan ini asli dan isi dokumen belum diubah sejak ditandatangani.']);

        // Perusahaan penerbit: semua model dokumen (SP, Berita Acara, Surat Tugas) punya relasi `company`.
        $companyName = $signable && method_exists($signable, 'company') ? $signable->company?->letterName() : null;

        $themes = [
            'emerald' => ['hero' => 'from-emerald-600 to-emerald-800', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-100'],
            'red' => ['hero' => 'from-red-600 to-red-800', 'soft' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-100'],
            'slate' => ['hero' => 'from-slate-500 to-slate-700', 'soft' => 'bg-slate-100', 'text' => 'text-slate-600', 'ring' => 'ring-slate-200'],
        ][$status['theme']];

        $roleLabel = $signable?->expectedSignerRoles()[$signature->signer_role] ?? $signature->signer_role;
    @endphp

    <div class="max-w-xl mx-auto px-4 py-8 sm:py-14">

        <div class="flex items-center gap-3 mb-6">
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-12 h-12 p-1.5 rounded-xl object-contain bg-white shadow-sm ring-1 ring-slate-200">
            <div>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-[0.12em] leading-none">Syifa Global Group</p>
                <h1 class="text-lg font-bold text-slate-900 leading-tight mt-1">Verifikasi Dokumen</h1>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-900/5 overflow-hidden">

            {{-- Status hero: lencana besar + label, supaya hasil verifikasi terbaca sekilas. --}}
            <div class="bg-gradient-to-br {{ $themes['hero'] }} px-6 sm:px-8 py-8 text-white relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
                <div class="absolute -right-2 top-16 w-24 h-24 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-5">
                    <div class="w-16 h-16 shrink-0 rounded-full bg-white/20 ring-8 ring-white/10 flex items-center justify-center">
                        @if ($status['theme'] === 'emerald')
                            <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @elseif ($status['theme'] === 'red')
                            <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8"><path d="M6 6l12 12M18 6L6 18" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8"><path d="M12 9v4M12 16.5h.01" stroke="white" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/70">Status Dokumen</p>
                        <p class="text-3xl font-extrabold tracking-wide leading-tight">{{ $status['label'] }}</p>
                        <p class="text-[13px] text-white/90 mt-1 leading-snug">{{ $status['desc'] }}</p>
                    </div>
                </div>
            </div>

            <div class="px-6 sm:px-8 py-7 space-y-7">

                {{-- Dokumen --}}
                <div>
                    <p class="text-[11px] text-slate-400 uppercase tracking-[0.1em] font-semibold mb-1">Jenis Dokumen</p>
                    <p class="text-xl font-bold text-slate-900 leading-snug">{{ $signable?->documentTypeLabel() ?? '-' }}</p>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        @if ($companyName)
                            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3 sm:col-span-2">
                                <dt class="text-[10.5px] text-slate-400 uppercase tracking-[0.1em] font-semibold">Perusahaan</dt>
                                <dd class="text-sm font-semibold text-slate-800 mt-0.5">{{ $companyName }}</dd>
                            </div>
                        @endif
                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3">
                            <dt class="text-[10.5px] text-slate-400 uppercase tracking-[0.1em] font-semibold">Nomor</dt>
                            <dd class="text-sm font-semibold text-slate-800 break-all mt-0.5">{{ $signable?->nomor ?: 'Belum bernomor' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3">
                            <dt class="text-[10.5px] text-slate-400 uppercase tracking-[0.1em] font-semibold">Tanggal Terbit</dt>
                            <dd class="text-sm font-semibold text-slate-800 mt-0.5">{{ $document->issued_at->translatedFormat('d M Y') }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Rincian spesifik dokumen yang ditandatangani (sama dengan ringkasan di modal tanda tangan). --}}
                @php $summary = $signable?->signSummary() ?? []; @endphp
                @if ($summary)
                    <div>
                        <p class="text-[11px] text-slate-400 uppercase tracking-[0.1em] font-semibold mb-3">Rincian Dokumen</p>
                        <dl class="rounded-2xl ring-1 ring-slate-200 divide-y divide-slate-100">
                            @foreach ($summary as $label => $value)
                                <div class="flex gap-4 px-4 py-2.5">
                                    <dt class="w-28 shrink-0 text-xs text-slate-500">{{ $label }}</dt>
                                    <dd class="text-sm font-semibold text-slate-800 min-w-0 break-words">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @if ($isRevoked && $revokedReason)
                    <div class="rounded-xl {{ $themes['soft'] }} ring-1 {{ $themes['ring'] }} px-4 py-3">
                        <p class="text-[10.5px] {{ $themes['text'] }} uppercase tracking-[0.1em] font-semibold mb-0.5">Alasan Dicabut</p>
                        <p class="text-sm text-slate-700">{{ $revokedReason }}</p>
                    </div>
                @endif

                {{-- 1 kode = 1 TTD personal (instruksi user 2026-09-29): penanda tangan yang di-scan tampil utama,
                lalu daftar seluruh penanda tangan dokumen (status saja, tanpa QR/kode milik orang lain). --}}
                <div>
                    <p class="text-[11px] text-slate-400 uppercase tracking-[0.1em] font-semibold mb-3">Penanda Tangan</p>
                    <div class="flex items-center gap-4 rounded-2xl ring-1 ring-slate-200 px-4 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10.5px] text-emerald-700 bg-emerald-50 inline-block rounded-full px-2 py-0.5 uppercase tracking-[0.08em] font-semibold">{{ $roleLabel }}</p>
                            <p class="text-[15px] font-bold text-slate-900 leading-tight mt-1">{{ $signature->signer_name }}</p>
                            @if ($signature->signer_position)
                                <p class="text-xs text-slate-500">{{ $signature->signer_position }}</p>
                            @endif
                        </div>
                        <p class="text-[11.5px] text-slate-500 text-right whitespace-nowrap shrink-0 leading-snug">{{ $signature->signed_at->translatedFormat('d M Y') }}<br><span class="text-slate-400">{{ $signature->signed_at->format('H:i') }} WIB</span></p>
                    </div>
                </div>

                @if ($participants->count() > 1)
                    @php $doneCount = $participants->filter(fn ($p) => $p['signature'])->count(); @endphp
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[11px] text-slate-400 uppercase tracking-[0.1em] font-semibold">Seluruh Penanda Tangan</p>
                            <p class="text-[11.5px] font-semibold {{ $doneCount === $participants->count() ? 'text-emerald-700' : 'text-amber-600' }}">
                                {{ $doneCount }} dari {{ $participants->count() }} sudah menandatangani
                            </p>
                        </div>
                        <ul class="rounded-2xl ring-1 ring-slate-200 divide-y divide-slate-100 overflow-hidden">
                            @foreach ($participants as $p)
                                @php $sig = $p['signature']; @endphp
                                <li class="flex items-center gap-3 px-4 py-3 {{ $p['scanned'] ? 'bg-emerald-50/60' : '' }}">
                                    <span class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center {{ ! $sig ? 'bg-slate-200 text-slate-400' : ($p['valid'] ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white') }}">
                                        @if ($sig && $p['valid'])
                                            <svg viewBox="0 0 24 24" fill="none" class="w-3.5 h-3.5"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @elseif ($sig)
                                            <svg viewBox="0 0 24 24" fill="none" class="w-3.5 h-3.5"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10.5px] text-slate-400 uppercase tracking-[0.08em] font-semibold">{{ $p['label'] }}@if ($p['scanned']) &middot; yang dipindai @endif</p>
                                        @if ($sig)
                                            <p class="text-sm font-semibold text-slate-800 leading-tight">{{ $sig->signer_name }}</p>
                                            @if ($sig->signer_position)<p class="text-xs text-slate-500">{{ $sig->signer_position }}</p>@endif
                                        @else
                                            <p class="text-sm text-slate-400">Belum menandatangani</p>
                                        @endif
                                    </div>
                                    @if ($sig)
                                        <p class="text-[11px] text-right shrink-0 leading-snug {{ $p['valid'] ? 'text-slate-500' : 'text-red-600 font-semibold' }}">
                                            @if ($p['valid']){{ $sig->signed_at->translatedFormat('d M Y') }}<br><span class="text-slate-400">{{ $sig->signed_at->format('H:i') }} WIB</span>@else Isi berubah @endif
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-5 py-4 flex items-center gap-4">
                    <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8 text-slate-400 shrink-0"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="min-w-0">
                        <p class="text-[10.5px] text-slate-400 uppercase tracking-[0.1em] font-semibold">Kode Verifikasi</p>
                        <p class="text-lg font-mono font-bold text-slate-800 tracking-wider break-all">{{ $code }}</p>
                    </div>
                </div>

                {{-- Metode tanda tangan: paling bawah (instruksi user), setelah kode verifikasi. --}}
                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3">
                    <p class="text-[10.5px] text-slate-400 uppercase tracking-[0.1em] font-semibold">Metode Tanda Tangan</p>
                    <p class="text-sm font-semibold text-slate-800 mt-0.5">Tanda tangan elektronik internal <span class="font-normal text-slate-500">(tidak tersertifikasi PSrE)</span></p>
                    <p class="text-xs text-slate-500 mt-0.5">Diverifikasi oleh sistem Syifa HRIS: identitas penanda tangan lewat akun dan password pribadi, keutuhan isi lewat pemeriksaan hash dokumen.</p>
                </div>
            </div>
        </div>

        <p class="text-[11.5px] text-slate-400 text-center mt-6 leading-relaxed px-4">
            Halaman ini cuma menampilkan status keabsahan, bukan isi lengkap dokumen. Tanda tangan
            elektronik pada dokumen ini bersifat internal perusahaan (non-tersertifikasi PSrE).
        </p>
    </div>

</body>
</html>
