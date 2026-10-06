<div>
<x-page-header title="Detail Surat Peringatan" :back="route('sp')" />

@include('livewire.document.partials.sign-banner')

{{-- Kasus ini = Berita Acara + Keputusan + Surat Peringatan; BA alur baru bisa dibuka dari sini. --}}
@if ($record->report && $record->report->status !== null)
    <div class="card-surface" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        @include('livewire.sp.partials.stage-chips', ['report' => $record->report, 'sp' => $record, 'chipsSize' => '12px'])
        <a href="{{ route('ba.show', $record->report) }}" wire:navigate class="btn-secondary" style="padding:7px 14px;font-size:12px;">
            <i class="fa-solid fa-file-lines"></i> Lihat Berita Acara
        </a>
    </div>
@endif

<div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px;">
    @include('livewire.document.partials.pdf-button', ['document' => $record->document, 'url' => route('sp.preview', $record), 'label' => 'Preview PDF'])
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

@if ($this->isDraft)
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid #a16207;background:#fefce8;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <p style="font-size:13px;font-weight:700;color:#a16207;margin:0;">
                    <i class="fa-solid fa-pen-to-square"></i> Draft - Belum Terbit
                </p>
                <p style="font-size:12.5px;color:var(--text-body);margin:4px 0 0;">Surat belum ditandatangani &amp; belum diterima karyawan. Masih bisa diedit bebas.</p>
            </div>
            @if ($this->canIssue)
                <button type="button" wire:click="openConfirmIssue" class="btn-primary" style="white-space:nowrap;">
                    <i class="fa-solid fa-signature"></i> Tanda Tangan &amp; Terbitkan
                </button>
            @endif
        </div>
    </div>
@endif

@if ($this->isLocked)
    <div class="card-surface" style="margin-bottom:16px;border:1.5px solid #16a34a;background:#f0fdf4;">
        <p style="font-size:13px;font-weight:700;color:#15803d;margin:0;">
            <i class="fa-solid fa-lock"></i> Dokumen Terkunci
        </p>
        <p style="font-size:12.5px;color:var(--text-body);margin:4px 0 0;">Pembuat sudah menandatangani - nomor, tanggal, Mengetahui, poin pelanggaran, dan Berita Acara tidak bisa diedit lagi.</p>
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
            @unless ($editingSp || $this->isLocked)
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
            @unless ($editingItems || $this->isLocked)
                <button type="button" wire:click="editItems" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
            @endunless
        @endif
    </div>

    @if ($editingItems)
        @include('livewire.sp.partials.reason-items-editor', ['field' => 'items', 'add' => 'addItem', 'remove' => 'removeItem', 'addLabel' => 'Tambah Pelanggaran', 'repeatOptions' => $this->repeatOptions])

        <div style="display:flex;gap:8px;margin-top:8px;">
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

@include('livewire.sp.partials.sp-signature-card')

<div class="card-surface accent accent-green" style="margin-bottom:16px;">
    <div class="section-head-icon" style="margin-bottom:14px;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="section-icon"><i class="fa-solid fa-file-lines"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Berita Acara</p>
        </div>
        <div style="display:flex;align-items-center;gap:3px;">
            @include('livewire.document.partials.pdf-button', ['document' => $record->report?->document, 'url' => route('sp.beritaacara_preview', $record), 'label' => 'Preview PDF', 'style' => 'font-size:12px;padding:4px 10px;'])
            @if ($this->canManage)
                @unless ($editingReport || $this->isLocked)
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

        <div style="margin-bottom:12px;">
            <label class="form-label">Dasar Klarifikasi</label>
            @include('livewire.sp.partials.reason-items-editor', ['field' => 'dasar_items', 'add' => 'addDasarItem', 'remove' => 'removeDasarItem', 'addLabel' => 'Tambah Dasar'])
        </div>

        @foreach (['kejadian' =>'Uraian Kronologi Permasalahan', 'subject_clarification' => 'Klarifikasi Karyawan', 'analysis' => 'Analisis', 'kesimpulan' => 'Kesimpulan Sementara', 'followup_plan' => 'Rencana Tindak Lanjut'] as $field => $label)
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
            <x-collapse-field label="Dasar Klarifikasi">{!! $record->report->dasarKlarifikasiHtml() !!}</x-collapse-field>
        @endif
        @if ($record->report?->kejadian)
            <x-collapse-field label="Uraian Kronologi Permasalahan">{!! $record->report->kejadian !!}</x-collapse-field>
        @endif
        @if ($record->report?->subject_clarification)
            <x-collapse-field label="Klarifikasi Karyawan">{!! $record->report->subject_clarification !!}</x-collapse-field>
        @endif
        @if ($record->report?->analysis)
            <x-collapse-field label="Analisis">{!! $record->report->analysis !!}</x-collapse-field>
        @endif
        @if ($record->report?->kesimpulan)
            <x-collapse-field label="Kesimpulan Sementara">{!! $record->report->kesimpulan !!}</x-collapse-field>
        @endif
        @if ($record->report?->followup_plan)
            <x-collapse-field label="Rencana Tindak Lanjut">{!! $record->report->followup_plan !!}</x-collapse-field>
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

@if ($confirmingIssue)
    <div class="modal-overlay" wire:click="cancelConfirmIssue">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Konfirmasi Tanda Tangan</h3>
                <button type="button" wire:click="cancelConfirmIssue" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="display:flex;align-items:center;gap:12px;background:var(--info-soft-bg);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                <x-avatar :user="auth()->user()" :size="42" />
                <div style="min-width:0;">
                    <p style="font-size:10.5px;color:var(--info-soft-text);text-transform:uppercase;letter-spacing:0.03em;margin:0 0 2px;">Anda menandatangani sebagai</p>
                    <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ auth()->user()->name }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:0;">{{ auth()->user()->activeJobPosition?->jobPosition?->name ?? '-' }}</p>
                </div>
            </div>

            <p style="font-size:12px;color:var(--text-body);margin:0 0 12px;">Surat akan langsung diterima oleh karyawan bersangkutan. Masukkan password Anda untuk menyetujui dan menandatangani penerbitan SP ini.</p>

            <div style="margin-bottom:14px;">
                <label class="form-label">Password Anda</label>
                <input type="password" wire:model="issuePassword" class="form-input" wire:keydown.enter="issue" autofocus>
                @error('issuePassword') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" wire:click="issue" wire:loading.attr="disabled" class="btn-primary" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-check"></i> Setujui &amp; Terbitkan
                </button>
                <button type="button" wire:click="cancelConfirmIssue" class="btn-ghost" style="flex:1;justify-content:center;">Batal</button>
            </div>
        </div>
    </div>
@endif

</div>
