@use('App\Enum\Sp\SpReportStatus', 'S')
<div>
<x-page-header title="Berita Acara" :back="route('sp.saya')" />

@php $status = $record->status; @endphp

<div class="card-surface" style="margin-bottom:16px;">
    <p style="font-size:13px;font-weight:700;margin:0;">{{ $status->getLabel() }}</p>
    @if ($record->keputusan)
        <p style="font-size:12.5px;margin:4px 0 0;color:var(--text-muted);">Keputusan: {{ $record->keputusan->getLabel() }}</p>
    @endif
</div>

<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Dasar &amp; Kejadian</span>
    </div>
    <p class="form-label">Dasar Klarifikasi</p>
    <div class="prose" style="margin-bottom:12px;">{!! $record->dasar_klarifikasi !!}</div>
    <p class="form-label">Kejadian</p>
    <div class="prose">{!! $record->kejadian !!}</div>
</div>

<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-comment-dots" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Klarifikasi Anda</span>
    </div>
    @if ($status === S::MENUNGGU_KLARIFIKASI)
        <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 8px;">Tulis klarifikasi Anda dengan bahasa Anda sendiri. HC akan merapikannya menjadi parafrase, dan Anda bisa memeriksanya sebelum menandatangani.</p>
        <textarea wire:model="klarifikasi" rows="7" class="form-input"></textarea>
        <div style="margin-top:12px;">
            <button type="button" wire:click="submitClarification" wire:confirm="Ajukan klarifikasi ini ke HC?" class="btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Ajukan Klarifikasi
            </button>
        </div>
    @else
        <div class="prose">{!! nl2br(e($record->subject_clarification)) !!}</div>
        @if ($status === S::KLARIFIKASI_DIAJUKAN)
            <p style="font-size:12px;color:var(--text-muted);margin:10px 0 0;">Klarifikasi sudah diajukan, menunggu HC memproses.</p>
        @endif
    @endif
</div>

@if ($this->showsHcContent)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Parafrase &amp; Analisis HC</span>
        </div>
        @foreach ([['klarifikasi_parafrase', 'Parafrase Klarifikasi Anda'], ['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
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
        @if ($this->signDocumentId)
            <p style="font-size:13px;margin:0 0 12px;">Jika parafrase di atas sudah sesuai dengan maksud Anda, setujui dan tanda tangani Berita Acara ini.</p>
            <a href="{{ route('documents.sign', $this->signDocumentId) }}" wire:navigate class="btn-primary">
                <i class="fa-solid fa-signature"></i> Setuju &amp; Tanda Tangan
            </a>

            <hr style="border:none;border-top:1px solid var(--border-soft);margin:18px 0;">

            <p style="font-size:13px;font-weight:700;margin:0 0 6px;">Parafrase tidak sesuai maksud Anda?</p>
            <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 8px;">Ajukan ulang klarifikasi dan jelaskan bagian yang tidak sesuai. Ini hanya bisa dilakukan sebelum Anda menandatangani.</p>
            <textarea wire:model="alasan_ajukan_ulang" rows="3" class="form-input" placeholder="Bagian yang tidak sesuai..."></textarea>
            <div style="margin-top:12px;">
                <button type="button" wire:click="requestReclarification" wire:confirm="Ajukan ulang klarifikasi? Parafrase HC saat ini akan dibatalkan." class="btn-secondary">Ajukan Ulang Klarifikasi</button>
            </div>
        @else
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Anda sudah menandatangani. Menunggu penandatangan pembuat dan yang mengetahui.</p>
        @endif
    </div>
@endif
</div>
