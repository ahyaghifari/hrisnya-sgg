{{-- Unggah dokumen yang sudah ditandatangani langsung (TTD digital ditahan), dipakai Berita Acara dan Surat Peringatan.
     Butuh $record, $editable; opsional $replace (ganti file), $printUrl, $docLabel, $signersText. --}}
@php
    $docLabel = $docLabel ?? 'Berita Acara';
    $printUrl = $printUrl ?? route('ba.preview', $record);
    $signersText = $signersText ?? 'atasan langsung, HC, yang mengetahui, dan karyawan';
@endphp
<div class="section-head">
    <div class="section-icon"><i class="fa-solid fa-file-arrow-up" style="color:var(--brand);font-size:12px;"></i></div>
    <span class="section-title">{{ ($replace ?? false) ? 'Ganti Berkas Bertanda Tangan' : $docLabel . ' Bertanda Tangan' }}</span>
</div>

@unless ($replace ?? false)
    <ol style="font-size:13px;color:var(--text-body);margin:0 0 12px;padding-left:18px;line-height:1.7;">
        <li>Cetak {{ $docLabel }} lewat tombol di bawah.</li>
        <li>Tandatangani langsung oleh {{ $signersText }}.</li>
        <li>Unggah hasil scan atau fotonya.</li>
    </ol>
    <a href="{{ $printUrl }}" target="_blank" class="btn-secondary" style="margin-bottom:14px;">
        <i class="fa-solid fa-print"></i> Cetak {{ $docLabel }} (PDF)
    </a>
@endunless

@if ($editable)
    <div>
        <label class="form-label">Berkas (PDF, JPG, atau PNG, maks. 10 MB)</label>
        <input type="file" wire:model="signedFile" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
        <div wire:loading wire:target="signedFile" style="font-size:12px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
        @error('signedFile') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        @error('status') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        <button type="button" wire:click="uploadSigned" wire:loading.attr="disabled" wire:target="uploadSigned,signedFile" class="btn-primary" style="margin-top:10px;">
            <i class="fa-solid fa-upload"></i> {{ ($replace ?? false) ? 'Ganti Berkas' : 'Unggah ' . $docLabel }}
        </button>
    </div>
@else
    <p style="font-size:13px;color:var(--text-muted);margin:0;">Menunggu HC mengunggah {{ $docLabel }} yang sudah ditandatangani.</p>
@endif