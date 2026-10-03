{{-- Kotak tanda tangan SP (pembuat, mengetahui, YBS bila SP dari BA alur baru) dengan QR langsung di halaman. Hanya setelah SP terbit. Butuh $record (EmployeeSp). --}}
@if ($record->document)
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
