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
    <p style="font-size:13px;margin:0;"><strong>{{ $record->user?->name }}</strong>
        <span style="color:var(--text-muted);">&middot; dibuat oleh {{ $record->createdBy?->name }} &middot; mengetahui {{ $record->knowsBy?->name }}</span></p>
    @if ($record->keputusan)
        <p style="font-size:13px;margin:6px 0 0;font-weight:700;">Keputusan: {{ $record->keputusan->getLabel() }}</p>
    @endif
</div>

{{-- Setelah SELESAI: seluruh bagian BA dijadikan 1 section (lihat bawah), 4 section proses disembunyikan. --}}
@if ($status !== S::SELESAI)
{{-- 1. Dasar & kejadian: bisa diedit hanya saat draft --}}
<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Dasar &amp; Kejadian</span>
    </div>
    @if ($status === S::DRAFT && $editable && $editingDraft)
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Nomor</label>
                <input type="text" wire:model="nomor" class="form-input">
            </div>
            <div>
                <label class="form-label">Dasar Klarifikasi <span style="color:var(--danger-text);">*</span></label>
                @include('livewire.sp.partials.dasar-items-editor', ['idPrefix' => 'trix-ba-d-dasar'])
            </div>
            <div>
                <label class="form-label">Kejadian <span style="color:var(--danger-text);">*</span></label>
                <div wire:ignore>
                    <input type="hidden" id="trix-ba-d-kejadian" wire:model="kejadian" value="{{ $kejadian }}">
                    <trix-editor input="trix-ba-d-kejadian" style="min-height:90px;"></trix-editor>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
            <button type="button" wire:click="saveDraft" class="btn-secondary">Simpan Draft</button>
            @if ($record->kejadian && $record->dasarItems->isNotEmpty())
                <button type="button" wire:click="cancelEditDraft" class="btn-ghost">Batal</button>
            @endif
        </div>
    @else
        <p class="form-label">Nomor</p><p style="margin:0 0 12px;">{{ $record->nomor ?? '-' }}</p>
        <p class="form-label">Dasar Klarifikasi</p><div class="prose" style="margin-bottom:12px;">{!! $record->dasarKlarifikasiHtml() !!}</div>
        <p class="form-label">Kejadian</p><div class="prose">{!! $record->kejadian !!}</div>
        @if ($status === S::DRAFT && $editable)
            <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                <button type="button" wire:click="editDraft" class="btn-secondary"><i class="fa-solid fa-pen"></i> Edit</button>
                <button type="button" wire:click="requestClarification" wire:confirm="Minta klarifikasi dari karyawan sekarang? Dasar & kejadian tidak bisa diubah lagi." class="btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Minta Klarifikasi Karyawan
                </button>
            </div>
        @endif
    @endif
</div>

{{-- 2. Klarifikasi karyawan (apa adanya) --}}
@if ($status !== S::DRAFT)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-comment-dots" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Klarifikasi Karyawan</span>
        </div>
        @if ($status === S::MENUNGGU_KLARIFIKASI && $record->klarifikasi_diisi_hc && $editable)
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 8px;">{{ $record->user?->name }} mengizinkan HC mengisikan klarifikasinya. Tulis apa adanya sesuai keterangan karyawan.</p>
            <div wire:ignore>
                <input type="hidden" id="trix-ba-klarifikasi" wire:model="klarifikasi_karyawan" value="{{ $klarifikasi_karyawan }}">
                <trix-editor input="trix-ba-klarifikasi" style="min-height:140px;"></trix-editor>
            </div>
            <div style="margin-top:12px;">
                <button type="button" wire:click="fillClarification" wire:confirm="Simpan klarifikasi atas nama karyawan dan lanjut ke analisis?" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Klarifikasi Karyawan
                </button>
            </div>
        @elseif ($status === S::MENUNGGU_KLARIFIKASI)
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ $record->klarifikasi_diisi_hc ? "{$record->user?->name} mengizinkan HC mengisikan klarifikasi." : "Menunggu {$record->user?->name} mengisi dan mengajukan klarifikasi." }}
            </p>
        @else
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 8px;">
                {{ $record->klarifikasi_diisi_hc ? 'Diisikan HC atas izin karyawan' : 'Diajukan karyawan' }} {{ $record->klarifikasi_diajukan_at?->translatedFormat('d M Y H:i') }} &middot; ditampilkan apa adanya
            </p>
            <div class="prose">{!! $record->subjectClarificationHtml() !!}</div>
        @endif

        @foreach ($record->riwayat_klarifikasi ?? [] as $i => $old)
            <details style="margin-top:12px;">
                <summary style="font-size:12.5px;cursor:pointer;">Riwayat pengajuan ulang #{{ $i + 1 }} &middot; {{ $old['at'] ?? '' }}</summary>
                <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Alasan mengajukan ulang</p><p style="font-size:12.5px;margin:0;">{{ $old['alasan_ajukan_ulang'] ?? '' }}</p>
                <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Klarifikasi saat itu</p><p style="font-size:12.5px;margin:0;">{!! $record->subjectClarificationHtml($old['klarifikasi'] ?? '', $old['diisi_hc'] ?? false) !!}</p>
                <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Parafrase HC saat itu</p><p style="font-size:12.5px;margin:0;">{!! nl2br(e($old['parafrase'] ?? '')) !!}</p>
            </details>
        @endforeach
    </div>
@endif

{{-- 3. Parafrase, analisis, kesimpulan, tindak lanjut --}}
@if (in_array($status, [S::KLARIFIKASI_DIAJUKAN, S::MENUNGGU_TTD, S::SELESAI], true))
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Parafrase &amp; Analisis HC</span>
        </div>
        @if ($status === S::KLARIFIKASI_DIAJUKAN && $editable && $editingAnalysis)
            <div style="display:flex;flex-direction:column;gap:16px;">
                @foreach ([['klarifikasi_parafrase', 'Parafrase Klarifikasi'], ['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
                    <div>
                        @php $parafraseOpsional = $field === 'klarifikasi_parafrase' && $record->klarifikasi_diisi_hc; @endphp
                        <label class="form-label">{{ $label }} @unless ($parafraseOpsional)<span style="color:var(--danger-text);">*</span>@endunless</label>
                        @if ($parafraseOpsional)
                            <p style="font-size:12px;color:var(--text-muted);margin:0 0 6px;">Klarifikasi ditulis HC sendiri, sudah disalin ke sini. Tidak perlu diparafrase lagi, boleh diubah atau dikosongkan.</p>
                        @endif
                        <div wire:ignore>
                            <input type="hidden" id="trix-ba-a-{{ $field }}" wire:model="{{ $field }}" value="{{ $$field }}">
                            <trix-editor input="trix-ba-a-{{ $field }}" style="min-height:90px;"></trix-editor>
                        </div>
                    </div>
                @endforeach
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                <button type="button" wire:click="saveAnalysis" class="btn-secondary">Simpan</button>
                @if (filled($record->analysis) && filled($record->kesimpulan) && filled($record->followup_plan))
                    <button type="button" wire:click="cancelEditAnalysis" class="btn-ghost">Batal</button>
                @endif
            </div>
        @else
            @foreach ([['klarifikasi_parafrase', 'Parafrase Klarifikasi'], ['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
                <p class="form-label">{{ $label }}</p><div class="prose" style="margin-bottom:12px;">{!! $record->{$field} !!}</div>
            @endforeach
            @if ($status === S::KLARIFIKASI_DIAJUKAN && $editable)
                <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                    <button type="button" wire:click="editAnalysis" class="btn-secondary"><i class="fa-solid fa-pen"></i> Edit</button>
                    <button type="button" wire:click="sendForSigning" wire:confirm="FIX Berita Acara sekarang? Isi akan dikunci dan tidak bisa diubah lagi. Tombol tanda tangan muncul setelahnya." class="btn-primary">
                        <i class="fa-solid fa-lock"></i> FIX Berita Acara
                    </button>
                </div>
            @endif
        @endif
    </div>
@endif

{{-- 4. Tanda tangan --}}
@if (in_array($status, [S::MENUNGGU_TTD, S::SELESAI], true))
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-signature" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Tanda Tangan</span>
        </div>
        @include('livewire.document.partials.signature-boxes', [
            'signable' => $record,
            'boxes' => ['created_by' => 'TTD Pembuat', 'knows_by' => 'TTD Mengetahui', 'employee' => 'TTD YBS'],
            'signatures' => $this->signatures,
        ])
        @if ($status === S::MENUNGGU_TTD)
            <p style="font-size:12px;color:var(--text-muted);margin:12px 0 0;">Pembuat menandatangani lebih dulu, lalu karyawan dan yang mengetahui.</p>
            @if ($record->document && $this->canSignNow)
                <button type="button" wire:click="$dispatch('open-sign-modal', { id: '{{ $record->document->id }}' })" class="btn-primary" style="margin-top:10px;">
                    <i class="fa-solid fa-signature"></i> Tanda Tangani Sekarang
                </button>
            @endif
        @endif
    </div>
@endif

@endif

{{-- Berita Acara selesai: 1 section utuh + kotak tanda tangan + PDF --}}
@if ($status === S::SELESAI)
    @include('livewire.sp.partials.ba-selesai-section', ['record' => $record, 'pdfUrl' => route('ba.preview', $record)])
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
