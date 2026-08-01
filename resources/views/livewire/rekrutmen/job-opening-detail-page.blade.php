<div>
<x-page-header title="Detail Lowongan Kerja" :subtitle="$record->display_name" :back="route('kepegawaian.lowongan')" />

@php $openingStatus = $record->openingStatus(); @endphp

@if ($this->canEdit)
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('kepegawaian.lowongan.edit', $record) }}" wire:navigate class="btn-primary">
            @if ($openingStatus === \App\Enum\Recruitment\JobOpeningStatus::WAITING)
                <i class="fa-solid fa-bullhorn"></i> Buka Lowongan
            @else
                <i class="fa-solid fa-pen"></i> Kelola Lowongan
            @endif
        </a>
    </div>
@endif

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Posisi</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->jobPosition?->name ?? '-' }}</span>
    </div>
    @if ($record->alias)
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Nama Tampilan</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->alias }}</span>
        </div>
    @endif
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Unit</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->jobPosition?->orgStructure?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Perusahaan</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->company?->name ?? '-' }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Kuota</span>
        <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->quota }}</span>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
        <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
        <span class="badge {{ $openingStatus->getBadgeClass() }}">{{ $openingStatus->getLabel() }}</span>
    </div>
    @if ($record->description)
        <div style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Deskripsi</span>
            <div style="font-size:13px;color:var(--text-body);">{!! $record->description !!}</div>
        </div>
    @endif
    @if ($record->requirements)
        <div style="padding:10px 0;">
            <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Persyaratan</span>
            <div style="font-size:13px;color:var(--text-body);">{!! $record->requirements !!}</div>
        </div>
    @endif
</div>

<h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Pelamar ({{ $record->applicants->count() }})</h3>

@if ($record->applicants->isEmpty())
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pelamar untuk lowongan ini.</p>
    </div>
@else
    <div class="pwa-list">
        @foreach ($record->applicants as $applicant)
            @php
                $status = \App\Enum\Recruitment\ProcessStatus::tryFrom($applicant->status);
            @endphp
            <a href="{{ route('kepegawaian.lowongan.kandidat', $applicant) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div class="pwa-tile-icon neutral"><i class="fa-solid fa-user"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $applicant->candidate?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ ucfirst(str_replace('_', ' ', $applicant->current_stage)) }} &middot; {{ $applicant->applied_at?->translatedFormat('d M Y') }}</p>
                </div>
                <span class="badge" style="background:var(--surface-alt);color:var(--text-body);">{{ $status?->getLabel() ?? $applicant->status }}</span>
            </a>
        @endforeach
    </div>
@endif
</div>
