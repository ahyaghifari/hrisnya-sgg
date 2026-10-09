{{-- Kotak tanda tangan SP (pembuat, mengetahui, YBS bila SP dari BA alur baru) dengan QR langsung di halaman. Hanya setelah SP terbit. Butuh $record (EmployeeSp).
     TTD digital ditahan (config sp.digital_signing): yang tampil info berkas bertanda tangan yang diunggah. --}}
@if ($record->document && ! \App\Services\Sp\SpReportService::digitalSigningEnabled())
    @if ($record->signed_file_path)
        <div class="card-surface accent accent-navy" style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb, #15803d 13%, transparent);color:#15803d;"><i class="fa-solid fa-file-circle-check"></i></div>
                <div style="min-width:0;flex:1;">
                    <p style="font-size:13px;font-weight:700;margin:0;">Surat Peringatan bertanda tangan</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0;word-break:break-all;">{{ $record->signed_file_name }} &middot; diunggah {{ $record->signedUploadedBy?->name ?? '-' }}, {{ $record->signed_uploaded_at?->translatedFormat('d M Y H:i') }}</p>
                </div>
                <a href="{{ auth()->id() === $record->user_id ? route('sp.saya.preview', $record) : route('sp.preview', $record) }}" target="_blank" class="btn-primary" style="white-space:nowrap;padding:7px 14px;font-size:12px;">
                    <i class="fa-solid fa-file-pdf"></i> Lihat Berkas
                </a>
            </div>
            @if ($canReplace ?? false)
                <hr style="border:none;border-top:1px solid var(--border-soft);margin:16px 0;">
                @include('livewire.sp.partials.ba-upload-panel', ['record' => $record, 'editable' => true, 'replace' => true, 'docLabel' => 'Surat Peringatan'])
            @endif
        </div>
    @endif
@elseif ($record->document)
    <div class="card-surface accent accent-navy" style="margin-bottom:16px;">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-signature"></i></div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Tanda Tangan</p>
        </div>
        @include('livewire.document.partials.signature-boxes', [
            'signable' => $record,
            'boxes' => ['created_by' => 'TTD Pembuat', 'knows_by' => 'TTD Mengetahui'] + ($record->requiresEmployeeSignature() ? ['employee' => 'TTD YBS'] : []),
            'signatures' => $record->document->signatures->keyBy('signer_role'),
        ])
    </div>
@endif