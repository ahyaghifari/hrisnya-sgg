@use('App\Enum\Sp\SpReportStatus', 'S')
<div>
<x-page-header title="Detail Surat Peringatan" :back="route('profil-saya')" />

@php $status = $record->status; @endphp

<div class="card-surface" style="margin-bottom:16px;">
    @include('livewire.sp.partials.stage-chips', ['report' => $record, 'sp' => $record->sp?->isIssued() ? $record->sp : null, 'chipsStyle' => 'margin-bottom:10px;', 'chipsSize' => '12px'])
    <p style="font-size:13px;font-weight:700;margin:0;">Tahap Berita Acara: {{ $status->getLabel() }}</p>
    <p style="font-size:12.5px;color:var(--text-muted);margin:6px 0 0;">
        Dibuat oleh <strong style="color:var(--text-heading);">{{ $record->createdBy?->name ?? '-' }}</strong>
        &middot; Mengetahui <strong style="color:var(--text-heading);">{{ $record->knowsBy?->name ?? '-' }}</strong>
    </p>
    <p style="font-size:12.5px;color:var(--text-muted);margin:4px 0 0;">
        Dibuat <strong style="color:var(--text-heading);">{{ $record->created_at->translatedFormat('j M Y, H:i') }}</strong>
    </p>
    @if ($record->sp?->isIssued())
        <a href="{{ route('sp.saya.show', $record->sp) }}" wire:navigate class="btn-primary" style="margin-top:10px;padding:7px 14px;font-size:12px;">
            Buka Surat Peringatan <i class="fa-solid fa-arrow-right"></i>
        </a>
    @endif
    @if ($record->keputusan)
        <p style="font-size:12.5px;margin:4px 0 0;color:var(--text-muted);">Keputusan: {{ $record->keputusan->getLabel() }}</p>
    @endif
</div>

{{-- Setelah SELESAI: seluruh isi BA + tanda tangan + PDF dalam 1 section (sama dengan sisi HC). --}}
@if ($status === S::SELESAI)
    @include('livewire.sp.partials.ba-selesai-section', ['record' => $record, 'pdfUrl' => route('sp.saya.ba.preview', $record)])
@else
<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Dasar &amp; Kejadian</span>
    </div>
    <p class="form-label">Dasar Klarifikasi</p>
    <div class="prose" style="margin-bottom:12px;">{!! $record->dasarKlarifikasiHtml() !!}</div>
    <p class="form-label">Kejadian</p>
    <div class="prose">{!! $record->kejadian !!}</div>
</div>

<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-comment-dots" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Klarifikasi Anda</span>
    </div>
    @if ($status === S::MENUNGGU_KLARIFIKASI)
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Pembuat Berita Acara sedang menyusun klarifikasi Anda.</p>
    @else
        <div class="prose">{!! $record->subjectClarificationHtml() !!}</div>
        @if ($record->klarifikasi_diisi_hc)
            <p style="font-size:11.5px;color:var(--text-muted);margin:6px 0 0;">Diisikan oleh pembuat/HC.</p>
        @endif
        @if ($status === S::KLARIFIKASI_DIAJUKAN)
            <p style="font-size:12px;color:var(--text-muted);margin:10px 0 0;">Klarifikasi sudah diajukan, menunggu HC memproses.</p>
        @endif
    @endif
</div>

@if ($this->showsHcContent)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Analisis HC</span>
        </div>
        @foreach ([['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
            <p class="form-label">{{ $label }}</p>
            <div class="prose" style="margin-bottom:12px;">{!! $record->{$field} !!}</div>
        @endforeach
    </div>
@endif

@if ($status === S::MENUNGGU_TTD)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-signature" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Tanggapan Anda</span>
        </div>
        @include('livewire.document.partials.signature-boxes', [
            'signable' => $record,
            'boxes' => ['created_by' => 'TTD Pembuat', 'knows_by' => 'TTD Mengetahui', 'employee' => 'TTD YBS'],
            'signatures' => $record->document?->signatures->keyBy('signer_role') ?? collect(),
        ])
        <div style="height:14px;"></div>
        @if ($this->signDocumentId)
            <p style="font-size:13px;margin:0 0 12px;">Berita Acara sudah ditandatangani pembuat. Jika isinya sudah sesuai dengan maksud Anda, setujui dan tanda tangani.</p>
            <button type="button" wire:click="$dispatch('open-sign-modal', { id: '{{ $this->signDocumentId }}' })" class="btn-primary">
                <i class="fa-solid fa-signature"></i> Setuju &amp; Tanda Tangan
            </button>
        @elseif ($record->document?->signatures->contains('signer_role', 'employee'))
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Anda sudah menandatangani. Menunggu penandatangan yang mengetahui.</p>
        @else
            <p style="font-size:13px;margin:0 0 12px;">Menunggu pembuat Berita Acara menandatangani lebih dulu. Anda akan diminta menandatangani setelahnya.</p>

            <p style="font-size:13px;font-weight:700;margin:0 0 6px;">Isi klarifikasi tidak sesuai keterangan Anda?</p>
            <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 8px;">Ajukan ulang klarifikasi dan jelaskan bagian yang tidak sesuai. Ini hanya bisa dilakukan sebelum pembuat menandatangani.</p>
            <textarea wire:model="alasan_ajukan_ulang" rows="3" class="form-input" placeholder="Bagian yang tidak sesuai..."></textarea>
            <div style="margin-top:12px;">
                <button type="button" wire:click="requestReclarification" wire:confirm="Ajukan ulang klarifikasi? Berita Acara kembali ke tahap penyusunan klarifikasi." class="btn-secondary">Ajukan Ulang Klarifikasi</button>
            </div>
        @endif
    </div>
@endif

@endif

<livewire:document.sign-document-modal />
</div>
