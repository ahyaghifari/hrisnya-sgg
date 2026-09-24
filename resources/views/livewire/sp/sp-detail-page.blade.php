<div>
<x-page-header title="Detail Surat Peringatan" :back="route('sp')" />

<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="{{ route('sp.preview', $record) }}" target="_blank" class="btn-primary">
        <i class="fa-solid fa-file-pdf"></i> Preview PDF
    </a>
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
    <div class="section-head-icon" style="margin-bottom:14px;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="section-icon"><i class="fa-solid fa-list-ol"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Uraian/Kronologi</p>
        </div>
        @unless ($editingItems)
            <button type="button" wire:click="editItems" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                <i class="fa-solid fa-pen"></i> Edit
            </button>
        @endunless
    </div>

    @if ($editingItems)
        @foreach ($items as $index => $text)
            <div wire:key="'edit-item-'.{{ $index }}" style="display:flex;gap:8px;margin-bottom:8px;align-items:flex-start;">
                <div wire:ignore style="flex:1;">
                    <input type="hidden" id="trix-edit-item-{{ $index }}" wire:model="items.{{ $index }}" value="{{ $text }}">
                    <trix-editor input="trix-edit-item-{{ $index }}" style="min-height:70px;"></trix-editor>
                </div>
                <button type="button" wire:click="removeItem({{ $index }})" class="btn-secondary" style="font-size:12px;padding:6px 10px;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        @endforeach
        @error('items') <p style="font-size:12px;color:#b91c1c;margin:0 0 8px;">{{ $message }}</p> @enderror

        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="button" wire:click="addItem" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                <i class="fa-solid fa-plus"></i> Tambah Uraian
            </button>
            <button type="button" wire:click="saveItems" class="btn-primary" style="font-size:12px;padding:4px 10px;">Simpan</button>
            <button type="button" wire:click="cancelEditItems" class="btn-secondary" style="font-size:12px;padding:4px 10px;">Batal</button>
        </div>
    @else
        @forelse ($record->items as $item)
            <div style="display:table;width:100%;margin-bottom:8px;">
                <div style="display:table-cell;width:18px;vertical-align:top;font-size:13px;color:var(--text-body);">{{ $loop->iteration }}.</div>
                <div style="display:table-cell;font-size:13px;color:var(--text-body);text-align:justify;">{!! $item->text !!}</div>
            </div>
        @empty
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada poin.</p>
        @endforelse
    @endif
</div>

<div class="card-surface accent accent-green" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:14px;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="section-icon"><i class="fa-solid fa-file-lines"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Berita Acara</p>
        </div>
        @unless ($editingReport)
            <button type="button" wire:click="editReport" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                <i class="fa-solid fa-pen"></i> Edit
            </button>
        @endunless
    </div>

    @if ($editingReport)
        @foreach (['dasar_klarifikasi' => 'Dasar Klarifikasi', 'kejadian' => 'Uraian Kronologi Permasalahan', 'subject_clarification' => 'Klarifikasi Karyawan', 'analysis' => 'Analisis', 'kesimpulan' => 'Kesimpulan Sementara', 'followup_plan' => 'Rencana Tindak Lanjut'] as $field => $label)
            <div style="margin-bottom:12px;">
                <label class="form-label">{{ $label }}</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-report-{{ $field }}" wire:model="{{ $field }}" value="{{ $$field }}">
                    <trix-editor input="trix-report-{{ $field }}" style="min-height:80px;"></trix-editor>
                </div>
            </div>
        @endforeach

        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="button" wire:click="saveReport" class="btn-primary" style="font-size:12px;padding:4px 10px;">Simpan</button>
            <button type="button" wire:click="cancelEditReport" class="btn-secondary" style="font-size:12px;padding:4px 10px;">Batal</button>
        </div>
    @else
        @if ($record->report?->dasar_klarifikasi)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Dasar Klarifikasi</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->dasar_klarifikasi !!}</div>
        @endif
        @if ($record->report?->kejadian)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Uraian Kronologi Permasalahan</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->kejadian !!}</div>
        @endif
        @if ($record->report?->subject_clarification)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Klarifikasi Karyawan</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->subject_clarification !!}</div>
        @endif
        @if ($record->report?->analysis)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Analisis</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->analysis !!}</div>
        @endif
        @if ($record->report?->kesimpulan)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Kesimpulan Sementara</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{!! $record->report->kesimpulan !!}</div>
        @endif
        @if ($record->report?->followup_plan)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Rencana Tindak Lanjut</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;">{!! $record->report->followup_plan !!}</div>
        @endif
        @unless ($record->report?->kejadian || $record->report?->dasar_klarifikasi || $record->report?->subject_clarification || $record->report?->analysis || $record->report?->kesimpulan || $record->report?->followup_plan)
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada Berita Acara.</p>
        @endunless
    @endif
</div>

</div>
