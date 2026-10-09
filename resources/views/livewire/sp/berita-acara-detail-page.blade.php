@use('App\Enum\Sp\SpReportStatus', 'S')
<div>
<x-page-header title="Detail Surat Peringatan" :back="route('sp')" />

@php
    $status = $record->status;
    $editable = $this->canManage;
    $lanjutSp = $record->keputusan === \App\Enum\Sp\SpReportDecision::LANJUT_SP;
@endphp

<div class="card-surface" style="margin-bottom:16px;">
    {{-- Berita Acara, Keputusan, dan Surat Peringatan = satu kasus; sub-status BA ditulis di bawahnya. --}}
    @include('livewire.sp.partials.stage-chips', ['report' => $record, 'sp' => $record->sp, 'chipsStyle' => 'margin-bottom:10px;', 'chipsSize' => '12px'])
    @if ($status !== S::SELESAI)
        <p style="font-size:12.5px;margin:0 0 8px;color:var(--text-muted);">Tahap Berita Acara: <strong style="color:var(--text-heading);">{{ $status->getLabel() }}</strong></p>
    @endif
    @php
        $parties = [
            ['fa-user', 'Yang bersangkutan', $record->user, 'var(--brand)'],
            ['fa-user-tie', 'Atasan langsung', $this->supervisor, '#7c3aed'],
            ['fa-user-shield', 'HC', $record->createdBy, '#0891b2'],
            ['fa-user-check', 'Mengetahui', $record->knowsBy, '#ea580c'],
        ];
    @endphp
    <div style="display:flex;flex-direction:column;gap:12px;">
        @foreach ($parties as [$icon, $label, $person, $color])
            <div style="display:flex;align-items:flex-start;gap:10px;">
                <div style="flex:none;width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb, {{ $color }} 13%, transparent);color:{{ $color }};">
                    <i class="fa-solid {{ $icon }}" style="font-size:13px;"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:10.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--text-muted);">{{ $label }}</div>
                    <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);word-break:break-word;">{{ $person?->name ?? '-' }}</div>
                </div>
            </div>
        @endforeach
    </div>
    @if ($record->keputusan)
        <p style="font-size:13px;margin:6px 0 0;font-weight:700;">Keputusan: {{ $record->keputusan->getLabel() }}</p>
    @endif

</div>

{{-- Setelah SELESAI: seluruh bagian BA dijadikan 1 section (lihat bawah), 4 section proses disembunyikan. --}}
@if ($status !== S::SELESAI)
@if ($status === S::DRAFT && $editable)
{{-- Draft: semua isian HC diisi sekaligus, disimpan sebagai draft sampai di-FIX --}}
<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Isi Berita Acara</span>
        <a href="{{ route('ba.preview', $record) }}" target="_blank" class="btn-secondary" style="margin-left:auto;font-size:12px;padding:5px 12px;" title="Hanya pratinjau, bukan untuk ditandatangani"><i class="fa-solid fa-file-pdf"></i> Preview PDF</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        @if (\App\Services\Sp\SpReportService::coachingSourceEnabled())
        <div>
            <label class="form-label">Sumber dari Coaching (opsional)</label>
            <select wire:model="coaching_id" class="form-input">
                <option value="">Tanpa Coaching</option>
                @foreach ($this->coachingOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('coaching_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
        @endif
        <div>
            <label class="form-label">Nomor</label>
            <input type="text" wire:model="nomor" class="form-input">
        </div>
        <div>
            <label class="form-label">Dasar Klarifikasi <span style="color:var(--danger-text);">*</span></label>
            @include('livewire.sp.partials.reason-items-editor', ['options' => $this->dasarReasonOptions, 'noun' => 'dasar klarifikasi', 'withNote' => false, 'field' => 'dasar_items', 'add' => 'addDasarItem', 'remove' => 'removeDasarItem', 'addLabel' => 'Tambah Dasar'])
        </div>
        @foreach ([['kejadian', 'Kejadian'], ['subject_clarification', 'Klarifikasi Karyawan'], ['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
            <div>
                <label class="form-label">{{ $label }} <span style="color:var(--danger-text);">*</span></label>
                @if ($field === 'subject_clarification')
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 6px;">Tuliskan klarifikasi yang bersangkutan tentang klarifikasinya terhadap uraian atau kronologi yang ditanyakan.</p>
                @endif
                <div wire:ignore>
                    <input type="hidden" id="trix-ba-d-{{ $field }}" wire:model="{{ $field }}" value="{{ $$field }}">
                    <trix-editor input="trix-ba-d-{{ $field }}" style="min-height:90px;"></trix-editor>
                </div>
            </div>
        @endforeach
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin:12px 0 0;">Disimpan sebagai draft dan belum terlihat karyawan. Wajib lengkap saat FIX.</p>
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
        <button type="button" wire:click="saveDraft" class="btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Simpan Draft</button>
        <button type="button" wire:click="sendForSigning" wire:confirm="FIX Berita Acara sekarang? Isi akan dikunci dan tidak bisa diubah lagi. Tombol tanda tangan muncul setelahnya." class="btn-primary">
            <i class="fa-solid fa-lock"></i> FIX Berita Acara
        </button>
    </div>
</div>
@else
<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Isi Berita Acara</span>
        <a href="{{ route('ba.preview', $record) }}" target="_blank" class="btn-secondary" style="margin-left:auto;font-size:12px;padding:5px 12px;" title="Hanya pratinjau, bukan untuk ditandatangani"><i class="fa-solid fa-file-pdf"></i> Preview PDF</a>
    </div>
    <p class="form-label">Nomor</p><p style="margin:0 0 12px;">{{ $record->nomor ?? '-' }}</p>
    @if ($record->coaching && \App\Services\Sp\SpReportService::coachingSourceEnabled())
        <p class="form-label">Sumber dari Coaching</p><p style="margin:0 0 12px;">{{ $record->coaching->title }} ({{ $record->coaching->created_at->translatedFormat('d M Y') }})</p>
    @endif
    <p class="form-label">Dasar Klarifikasi</p><div class="prose" style="margin-bottom:12px;">{!! $record->dasarKlarifikasiHtml() !!}</div>
    <p class="form-label">Kejadian</p><div class="prose" style="margin-bottom:12px;">{!! $record->kejadian !!}</div>
    @if (filled($record->subject_clarification))
        <p class="form-label">Klarifikasi Karyawan</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 4px;">Ditampilkan apa adanya</p>
        <div class="prose" style="margin-bottom:12px;">{!! $record->subjectClarificationHtml() !!}</div>
    @endif
    @foreach ([['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
        @if (filled($record->{$field}))
            <p class="form-label">{{ $label }}</p><div class="prose" style="margin-bottom:12px;">{!! $record->{$field} !!}</div>
        @endif
    @endforeach

    @foreach ($record->riwayat_klarifikasi ?? [] as $i => $old)
        <details style="margin-top:12px;">
            <summary style="font-size:12.5px;cursor:pointer;">Riwayat pengajuan ulang #{{ $i + 1 }} &middot; {{ $old['at'] ?? '' }}</summary>
            <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Alasan mengajukan ulang</p><p style="font-size:12.5px;margin:0;">{{ $old['alasan_ajukan_ulang'] ?? '' }}</p>
            <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Klarifikasi saat itu</p><p style="font-size:12.5px;margin:0;">{!! $record->subjectClarificationHtml($old['klarifikasi'] ?? '', $old['diisi_hc'] ?? false) !!}</p>
        </details>
    @endforeach
</div>
@endif

{{-- Tanda tangan: TTD digital (config sp.digital_signing) atau unggah berkas bertanda tangan langsung. --}}
@if (in_array($status, [S::MENUNGGU_TTD, S::SELESAI], true))
    <div class="form-card">
        @if (\App\Services\Sp\SpReportService::digitalSigningEnabled())
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-signature" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Tanda Tangan</span>
            </div>
            @include('livewire.document.partials.signature-boxes', [
                'signable' => $record,
                'boxes' => $record->expectedSignerRoles(),
                'cols' => 2,
                'signatures' => $this->signatures,
            ])
            @if ($status === S::MENUNGGU_TTD)
            <p style="font-size:12px;color:var(--text-muted);margin:12px 0 0;">Urutan tanda tangan: {{ $record->revised_at ? 'HC pembuat, atasan langsung (jika ada), mengetahui, lalu karyawan' : 'atasan langsung (jika ada), HC pembuat, mengetahui, lalu karyawan' }}.</p>
            @if ($record->document && $this->canSignNow)
                <button type="button" wire:click="$dispatch('open-sign-modal', { id: '{{ $record->document->id }}' })" class="btn-primary" style="margin-top:10px;">
                    <i class="fa-solid fa-signature"></i> Tanda Tangani Sekarang
                </button>
            @endif
            @endif
        @else
            @include('livewire.sp.partials.ba-upload-panel', ['record' => $record, 'editable' => $editable])
        @endif

        @if ($status === S::MENUNGGU_TTD)
            @if ($editable)
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border-soft);">
                    <label class="form-label">Revisi Berita Acara</label>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 6px;">Membuka kembali isi untuk diedit. Semua tanda tangan yang sudah masuk dibatalkan dan diulang.</p>
                    <textarea wire:model="revision_reason" class="form-input" rows="2" placeholder="Alasan revisi"></textarea>
                    <button type="button" wire:click="revise" wire:confirm="Revisi Berita Acara? Semua tanda tangan yang sudah masuk akan dibatalkan." class="btn-secondary" style="margin-top:8px;">
                        <i class="fa-solid fa-rotate-left"></i> Revisi
                    </button>
                </div>
            @endif
        @endif
    </div>
@endif

@endif
{{-- Berita Acara selesai: 1 section utuh + kotak tanda tangan + PDF --}}
@if ($status === S::SELESAI)
    @include('livewire.sp.partials.ba-selesai-section', ['record' => $record, 'pdfUrl' => route('ba.preview', $record)])
@endif

{{-- Ganti berkas bertanda tangan: boleh selama keputusan belum ditetapkan --}}
@if ($status === S::SELESAI && ! $record->keputusan && $record->signed_file_path && $editable && ! \App\Services\Sp\SpReportService::digitalSigningEnabled())
    <div class="form-card">
        @include('livewire.sp.partials.ba-upload-panel', ['record' => $record, 'editable' => true, 'replace' => true])
    </div>
@endif

{{-- 5. Keputusan --}}
@if ($status === S::SELESAI)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-gavel" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Keputusan</span>
        </div>
        @if ($record->keputusan)
            <p style="margin:0;font-weight:700;">{{ $record->keputusan->getLabel() }}</p>
            @if ($lanjutSp && $record->sp_id)
                <div style="margin-top:12px;">
                    <a href="{{ route('sp.show', $record->sp_id) }}" wire:navigate class="btn-secondary"><i class="fa-solid fa-triangle-exclamation"></i> Lihat SP</a>
                </div>
            @endif
        @elseif ($editable)
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">Berita Acara sudah ditandatangani semua pihak dan terkunci. Putuskan apakah karyawan layak dikenai SP.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" wire:click="decide('tidak_layak_sp')" wire:confirm="Tetapkan TIDAK layak SP? Keputusan tidak bisa diubah." class="btn-secondary">Tidak Layak SP</button>
                <button type="button" wire:click="decide('lanjut_sp')" wire:confirm="Tetapkan LANJUT ke SP? Keputusan tidak bisa diubah." class="btn-primary">Lanjut ke SP</button>
            </div>
        @else
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Menunggu keputusan HC.</p>
        @endif
    </div>
@endif

<livewire:document.sign-document-modal />

{{-- 6. Langkah 2: form SP, tertanam (bukan halaman terpisah) --}}
@if ($status === S::SELESAI && $lanjutSp && ! $record->sp_id && $editable)
    <livewire:sp.create-sp-from-report-page :report-id="$record->id" :key="'sp-' . $record->id" />
@endif

{{-- Langkah 3: SP yang sudah terbit dari BA ini, ringkas + masuk ke detailnya --}}
@if ($record->sp)
    @php $sp = $record->sp; @endphp
    <div class="form-card">
        <div class="section-head" style="justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="section-icon"><i class="fa-solid fa-triangle-exclamation" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Surat Peringatan</span>
            </div>
            <a href="{{ route('sp.show', $sp) }}" wire:navigate class="btn-primary" style="padding:7px 14px;font-size:12px;">
                Buka Surat Peringatan <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <p style="font-size:13px;margin:0;">
            <strong>{{ $sp->sp_level->getLabel() }}</strong>
            @if ($sp->nomor) &middot; No. {{ $sp->nomor }} @endif
            &middot; {{ $sp->start_date->translatedFormat('d M Y') }}{{ $sp->end_date ? ' s.d. ' . $sp->end_date->translatedFormat('d M Y') : '' }}
            &middot; {{ $sp->isCancelled() ? 'Dibatalkan' : ($sp->document?->isFullySigned() ? 'Terbit' : 'Menunggu tanda tangan') }}
        </p>
    </div>
@endif
</div>
