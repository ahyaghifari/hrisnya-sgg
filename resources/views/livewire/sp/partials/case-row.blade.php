{{-- 1 baris kasus: Berita Acara -> Keputusan -> Surat Peringatan. Butuh $case (lihat SpCaseList) dan $mine (true = sisi karyawan). --}}
@php
    $report = $case['report'];
    $sp = $case['sp'];

    $levelColors = ['sp1' => ['#fef9c3', '#a16207'], 'sp2' => ['#ffedd5', '#c2410c'], 'sp3' => ['#fee2e2', '#b91c1c']];

    $url = $mine
        ? ($sp ? route('sp.saya.show', $sp) : route('sp.saya.ba', $report))
        : ($sp ? route('sp.show', $sp) : route('ba.show', $report));

    if ($sp) {
        $isActive = ! $sp->isCancelled() && $sp->start_date->lte(today()) && ($sp->end_date === null || $sp->end_date->gte(today()));
        $info = ($sp->nomor ? 'No. ' . $sp->nomor . ' · ' : '') . $sp->start_date->translatedFormat('d M Y') . ' · ' . ($sp->isCancelled() ? 'Dibatalkan' : ($isActive ? 'Aktif' : 'Expired'));
    } else {
        $info = ($report->nomor ? 'No. ' . $report->nomor . ' · ' : '') . $report->created_at->translatedFormat('d M Y') . ' · ' . ($report->keputusan?->getLabel() ?? $report->status->getLabel());
    }
@endphp
<a href="{{ $url }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
    <div style="flex:1;min-width:0;">
        <p class="pwa-row-title">
            @if ($sp)
                @php $lc = $levelColors[$sp->sp_level->value] ?? ['#f1f5f9', '#64748b']; @endphp
                <span class="badge" style="background:{{ $lc[0] }};color:{{ $lc[1] }};margin-right:6px;">{{ $sp->sp_level->getLabel() }}</span>
            @endif
            {{ $mine ? 'Surat Peringatan' : ($case['user']?->name ?? '-') }}
        </p>
        <span style="font-size:11.5px;color:var(--text-muted);">{{ $info }}</span>
        @include('livewire.sp.partials.stage-chips', ['report' => $report, 'sp' => $sp, 'chipsStyle' => 'margin-top:6px;'])
    </div>
    <i class="fa-solid fa-chevron-right"></i>
</a>
