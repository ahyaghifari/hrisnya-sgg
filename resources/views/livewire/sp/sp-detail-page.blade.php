<div>
<x-page-header title="Detail Surat Peringatan" :back="route('sp')" />

<div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px;">
    <a href="{{ route('sp.preview', $record) }}" target="_blank" class="btn-primary">
        <i class="fa-solid fa-file-pdf"></i> Preview PDF
    </a>
</div>

@if ($cancellingSp)
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid var(--danger-text);">
        <p style="font-size:13px;font-weight:700;color:var(--danger-text);margin:0 0 8px;">
            <i class="fa-solid fa-triangle-exclamation"></i> Batalkan SP ini?
        </p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 12px;">SP tidak dihapus, cuma ditandai batal - tetap tercatat sebagai riwayat dan tidak lagi dihitung aktif.</p>
        <label class="form-label">Alasan Pembatalan <span style="color:var(--danger-text);">*</span></label>
        <textarea wire:model="cancel_reason" rows="3" class="form-input"></textarea>
        @error('cancel_reason') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button type="button" wire:click="confirmCancelSp" wire:confirm="Yakin batalkan SP ini? Tindakan ini tidak bisa dibatalkan lagi." class="btn-primary" style="background:var(--danger-text);border-color:var(--danger-text);">Ya, Batalkan</button>
            <button type="button" wire:click="closeCancelSp" class="btn-secondary">Batal</button>
        </div>
    </div>
@endif

@php
    $levelColors = [
        'sp1' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'sp2' => ['bg' => '#ffedd5', 'fg' => '#c2410c'],
        'sp3' => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
    ];
    $levelColor = $levelColors[$record->sp_level->value] ?? ['bg' => '#f1f5f9', 'fg' => '#64748b'];
    $isActive = ! $record->isCancelled() && $record->start_date->lte(today()) && ($record->end_date === null || $record->end_date->gte(today()));
@endphp

@if ($this->isFullySigned)
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid #16a34a;background:#f0fdf4;">
        <p style="font-size:13px;font-weight:700;color:#15803d;margin:0;">
            <i class="fa-solid fa-lock"></i> Dokumen Terkunci
        </p>
        <p style="font-size:12.5px;color:var(--text-body);margin:4px 0 0;">Semua pihak sudah menandatangani - nomor, tanggal, Mengetahui, poin pelanggaran, dan Berita Acara tidak bisa diedit lagi.</p>
    </div>
@endif

@if ($record->isCancelled())
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid var(--danger-text);background:#fef2f2;">
        <p style="font-size:13px;font-weight:700;color:var(--danger-text);margin:0 0 6px;">
            <i class="fa-solid fa-ban"></i> SP Ini Dibatalkan
        </p>
        <p style="font-size:12.5px;color:var(--text-body);margin:0 0 4px;">{{ $record->cancelled_reason }}</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0;">
            Oleh {{ $record->cancelledBy?->name ?? '-' }} &middot; {{ $record->cancelled_at->translatedFormat('d M Y, H:i') }}
        </p>
    </div>
@endif

<div class="card-surface accent accent-navy" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:8px;justify-content:space-between;">
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">
            <span class="badge" style="background:{{ $levelColor['bg'] }};color:{{ $levelColor['fg'] }};margin-right:6px;">{{ $record->sp_level->getLabel() }}</span>
            {{ $record->isCancelled() ? 'Dibatalkan' : ($isActive ? 'Aktif' : 'Expired') }}
            @if ($record->nomor)
                <span style="font-size:11.5px;font-weight:400;color:var(--text-muted);">&middot; No. {{ $record->nomor }}</span>
            @endif
        </p>
        @if ($this->canManage)
            @unless ($editingSp || $this->isFullySigned)
                <button type="button" wire:click="editSp" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
            @endunless
        @endif
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

    @if ($editingSp)
        <div style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
            <label class="form-label">Nomor Surat</label>
            <input type="text" wire:model="sp_nomor" class="form-input">
            @error('sp_nomor') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-soft);">
            <div style="flex:1;">
                <label class="form-label">Berlaku Mulai</label>
                <input type="date" wire:model="sp_start_date" class="form-input">
                @error('sp_start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <div style="flex:1;">
                <label class="form-label">Berlaku Sampai (opsional)</label>
                <input type="date" wire:model="sp_end_date" class="form-input">
                @error('sp_end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
        <div style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
            <label class="form-label">Mengetahui</label>
            <select wire:model="sp_knows_by" class="form-input">
                <option value="">Pilih</option>
                @foreach ($this->knowsByOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            @error('sp_knows_by') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
        <div style="display:flex;gap:8px;padding-top:12px;">
            <button type="button" wire:click="saveSp" class="btn-primary" style="font-size:12px;padding:4px 10px;">Simpan</button>
            <button type="button" wire:click="cancelEditSp" class="btn-secondary" style="font-size:12px;padding:4px 10px;">Batal</button>
        </div>
    @else
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
    @endif
</div>

<div class="card-surface accent accent-indigo" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:14px;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="section-icon"><i class="fa-solid fa-list-ol"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Uraian/Kronologi</p>
        </div>
        @if ($this->canManage)
            @unless ($editingItems || $this->isFullySigned)
                <button type="button" wire:click="editItems" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
            @endunless
        @endif
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
        <div style="display:flex;align-items-center;gap:3px;">
            <a href="{{ route('sp.beritaacara_preview', $record) }}" target="_blank"  class="btn-primary" style="font-size:12px;padding:4px 10px;">
                <i class="fa-solid fa-file-pdf"></i> Preview PDF
            </a>
            @if ($this->canManage)
                @unless ($editingReport || $this->isFullySigned)
                    <button type="button" wire:click="editReport" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                @endunless
            @endif
        </div>
    </div>

    @if ($editingReport)

        <div style="margin-bottom: 12px;">
            <label class="form-label">Nomor</label>
            <input type="text" wire:model="nomor" class="form-input">
            @error('nomor') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
        </div>

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

        @if ($record->report?->nomor)
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Nomor</p>
            <div style="font-size:13px;color:var(--text-body);line-height:1.6;margin:0 0 12px;">{{ $record->report->nomor }}</div>
        @endif
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

@if ($this->canManage)
    @unless ($record->isCancelled())
        <div style="display:flex;justify-content:flex-end;margin-top:8px;">
            <button type="button" wire:click="openCancelSp" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                <i class="fa-solid fa-ban"></i> Batalkan SP
            </button>
        </div>
    @endunless
@endif

</div>
