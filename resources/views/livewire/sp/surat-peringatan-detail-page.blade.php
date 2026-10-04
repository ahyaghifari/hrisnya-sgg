<div>
<x-page-header title="Detail Surat Peringatan" :back="route('profil-saya')" />

@include('livewire.document.partials.sign-banner')

@if ($record->report && $record->report->status !== null)
    <div class="card-surface" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        @include('livewire.sp.partials.stage-chips', ['report' => $record->report, 'sp' => $record, 'chipsSize' => '12px'])
        <a href="{{ route('sp.saya.ba', $record->report) }}" wire:navigate class="btn-secondary" style="padding:7px 14px;font-size:12px;">
            <i class="fa-solid fa-file-lines"></i> Lihat Berita Acara
        </a>
    </div>
@endif

<div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px;">
    @include('livewire.document.partials.pdf-button', ['document' => $record->document, 'url' => route('sp.saya.preview', $record), 'label' => 'Preview PDF'])
    @if ($record->report)
        @include('livewire.document.partials.pdf-button', ['document' => $record->report->document, 'url' => route('sp.saya.beritaacara_preview', $record), 'label' => 'Preview Berita Acara', 'class' => 'btn-secondary'])
    @endif
</div>

@php
    $levelColors = [
        'sp1' => ['background' => '#fef9c3', 'color' => '#a16207'],
        'sp2' => ['background' => '#ffedd5', 'color' => '#c2410c'],
        'sp3' => ['background' => '#fee2e2', 'color' => '#b91c1c'],
    ];
    $levelColor = $levelColors[$record->sp_level->value] ?? ['background' => '#f1f5f9', 'color' => '#64748b'];
    $isActive = ! $record->isCancelled() && $record->start_date->lte(today()) && ($record->end_date === null || $record->end_date->gte(today()));
@endphp

@if ($record->isCancelled())
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid var(--danger-text);background:#fef2f2;">
        <p style="font-size:13px;font-weight:700;color:var(--danger-text);margin:0;">
            <i class="fa-solid fa-ban"></i> SP Ini Dibatalkan
        </p>
    </div>
@endif

<div class="card-surface accent accent-navy" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:8px;">
        <div class="section-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">
            <span class="badge" style="background:{{ $levelColor['background'] }};color:{{ $levelColor['color'] }};margin-right:6px;">{{ $record->sp_level->getLabel() }}</span>
            {{ $record->isCancelled() ? 'Dibatalkan' : ($isActive ? 'Aktif' : 'Expired') }}
            @if ($record->nomor)
                <span style="font-size:11.5px;font-weight:400;color:var(--text-muted);">&middot; No. {{ $record->nomor }}</span>
            @endif
        </p>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Berlaku</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">
            {{ $record->start_date->translatedFormat('d M Y') }}
            &ndash;
            {{ $record->end_date ? $record->end_date->translatedFormat('d M Y') : 'Tidak pernah berakhir' }}
        </span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
        <span style="font-size:12.5px;color:var(--text-muted);">Diterbitkan Oleh</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->createdBy?->name ?? '-' }}</span>
    </div>
</div>

<div class="card-surface accent accent-indigo" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-list-ol"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Uraian/Kronologi</p>
    </div>
    @forelse ($record->items as $item)
        <div style="display:table;width:100%;margin-bottom:8px;">
            <div style="display:table-cell;width:18px;vertical-align:top;font-size:13px;color:var(--text-body);">{{ $loop->iteration }}.</div>
            <div style="display:table-cell;font-size:13px;color:var(--text-body);text-align:justify;">{!! $item->text !!}</div>
        </div>
    @empty
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada poin.</p>
    @endforelse
</div>

@include('livewire.sp.partials.sp-signature-card')

@if ($record->report)
    <div class="card-surface accent accent-green" style="margin-bottom:16px;">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-file-lines"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Berita Acara</p>
        </div>
        @if ($record->report->dasar_klarifikasi)
            <x-collapse-field label="Dasar Klarifikasi">{!! $record->report->dasarKlarifikasiHtml() !!}</x-collapse-field>
        @endif
        @if ($record->report->kejadian)
            <x-collapse-field label="Uraian Kronologi Permasalahan">{!! $record->report->kejadian !!}</x-collapse-field>
        @endif
        @if ($record->report->subject_clarification)
            <x-collapse-field label="Klarifikasi Karyawan">{!! $record->report->subject_clarification !!}</x-collapse-field>
        @endif
        @if ($record->report->klarifikasi_parafrase)
            <x-collapse-field label="Parafrase Klarifikasi">{!! $record->report->klarifikasi_parafrase !!}</x-collapse-field>
        @endif
        @if ($record->report->analysis)
            <x-collapse-field label="Analisis">{!! $record->report->analysis !!}</x-collapse-field>
        @endif
        @if ($record->report->kesimpulan)
            <x-collapse-field label="Kesimpulan Sementara">{!! $record->report->kesimpulan !!}</x-collapse-field>
        @endif
        @if ($record->report->followup_plan)
            <x-collapse-field label="Rencana Tindak Lanjut">{!! $record->report->followup_plan !!}</x-collapse-field>
        @endif
    </div>
@endif

</div>
