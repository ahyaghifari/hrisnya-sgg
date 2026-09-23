<div>
<x-page-header title="Detail Surat Peringatan" :back="route('sp')" />

<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <button type="button" wire:click="downloadPdf" class="btn-primary">
        <i class="fa-solid fa-file-pdf"></i> Download PDF
    </button>
</div>

@php
    $levelColors = [
        'sp1' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'sp2' => ['bg' => '#ffedd5', 'fg' => '#c2410c'],
        'sp3' => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
    ];
    $levelColor = $levelColors[$record->sp_level->value] ?? ['bg' => '#f1f5f9', 'fg' => '#64748b'];
    $isActive = $record->start_date->lte(today()) && ($record->end_date === null || $record->end_date->gte(today()));
@endphp

<div class="card-surface accent accent-navy" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:8px;">
        <div class="section-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">
            <span class="badge" style="background:{{ $levelColor['bg'] }};color:{{ $levelColor['fg'] }};margin-right:6px;">{{ $record->sp_level->getLabel() }}</span>
            {{ $isActive ? 'Aktif' : 'Expired' }}
            @if ($record->nomor)
                <span style="font-size:11.5px;font-weight:400;color:var(--text-muted);">&middot; No. {{ $record->nomor }}</span>
            @endif
        </p>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Karyawan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Sumber</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">
            {{ $record->source_type->getLabel() }}
            @if ($record->coaching)
                &middot; {{ $record->coaching->title }}
            @endif
        </span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Berlaku</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">
            {{ $record->start_date->translatedFormat('d M Y') }}
            &ndash;
            {{ $record->end_date ? $record->end_date->translatedFormat('d M Y') : 'Tidak pernah berakhir' }}
        </span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Dibuat Oleh</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->createdBy?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
        <span style="font-size:12.5px;color:var(--text-muted);">Mengetahui</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->knowsBy?->name ?? '-' }}</span>
    </div>
</div>

<div class="card-surface accent accent-indigo" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-list-ol"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Poin Pelanggaran</p>
    </div>
    @forelse ($record->items as $item)
        <p style="font-size:13px;color:var(--text-body);margin:0 0 8px;white-space:pre-line;">{{ $item->text }}</p>
    @empty
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada poin.</p>
    @endforelse
</div>

@if ($record->report)
    <div class="card-surface accent accent-green" style="margin-bottom:16px;">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-file-lines"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Berita Acara</p>
        </div>
        @if ($record->report->kejadian)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Kejadian</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->kejadian !!}</div>
        @endif
        @if ($record->report->subject_clarification)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Klarifikasi Karyawan</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->subject_clarification !!}</div>
        @endif
        @if ($record->report->analysis)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Analisis</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->analysis !!}</div>
        @endif
        @if ($record->report->followup_plan)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Rencana Tindak Lanjut</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;">{!! $record->report->followup_plan !!}</div>
        @endif
    </div>
@endif

</div>
