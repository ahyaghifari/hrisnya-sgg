{{-- Berita Acara SELESAI: seluruh bagian dalam 1 section + kotak tanda tangan + tombol PDF.
     Dipakai sisi HC (detail BA) dan sisi karyawan (BA saya). Butuh $record (SpReport) dan $pdfUrl. --}}
<div class="form-card">
    <div class="section-head" style="justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="section-icon"><i class="fa-solid fa-file-circle-check" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Berita Acara</span>
        </div>
        @include('livewire.document.partials.pdf-button', ['document' => $record->document, 'url' => $pdfUrl, 'label' => 'Lihat PDF', 'style' => 'padding:7px 14px;font-size:12px;'])
    </div>

    <div style="display:flex;flex-direction:column;gap:2px;">
        <div><p class="form-label">Nomor</p><p style="margin:0;">{{ $record->nomor ?? '-' }}</p></div>
        <x-collapse-field label="Dasar Klarifikasi">{!! $record->dasarKlarifikasiHtml() !!}</x-collapse-field>
        <x-collapse-field label="Kejadian">{!! $record->kejadian !!}</x-collapse-field>
        <x-collapse-field label="Klarifikasi Karyawan">
            <p style="font-size:11px;color:var(--text-muted);margin:0 0 4px;">
                {{ $record->klarifikasi_diisi_hc ? 'Diisikan pembuat/HC' : 'Diajukan karyawan' }} {{ $record->klarifikasi_diajukan_at?->translatedFormat('d M Y H:i') }}
            </p>
            {!! $record->subjectClarificationHtml() !!}
        </x-collapse-field>
        @if (filled($record->klarifikasi_parafrase))
            <x-collapse-field label="Parafrase Klarifikasi">{!! $record->klarifikasi_parafrase !!}</x-collapse-field>
        @endif
        <x-collapse-field label="Analisis">{!! $record->analysis !!}</x-collapse-field>
        <x-collapse-field label="Kesimpulan">{!! $record->kesimpulan !!}</x-collapse-field>
        <x-collapse-field label="Rencana Tindak Lanjut">{!! $record->followup_plan !!}</x-collapse-field>
    </div>

    @foreach ($record->riwayat_klarifikasi ?? [] as $i => $old)
        <details style="margin-top:14px;">
            <summary style="font-size:12.5px;cursor:pointer;">Riwayat pengajuan ulang #{{ $i + 1 }} &middot; {{ $old['at'] ?? '' }}</summary>
            <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Alasan mengajukan ulang</p><p style="font-size:12.5px;margin:0;">{{ $old['alasan_ajukan_ulang'] ?? '' }}</p>
            <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Klarifikasi saat itu</p><div style="font-size:12.5px;">{!! $record->subjectClarificationHtml($old['klarifikasi'] ?? '', $old['diisi_hc'] ?? false) !!}</div>
        </details>
    @endforeach

    <hr style="border:none;border-top:1px solid var(--border-soft);margin:18px 0;">

    @include('livewire.document.partials.signature-boxes', [
        'signable' => $record,
        'boxes' => $record->expectedSignerRoles(),
            'cols' => 2,
        'signatures' => $record->document?->signatures->keyBy('signer_role') ?? collect(),
    ])
</div>
