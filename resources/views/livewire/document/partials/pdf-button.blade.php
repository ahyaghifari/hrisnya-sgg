{{-- Tombol buka PDF dokumen. Selama PDF final masih dibuat (semua TTD selesai, file belum jadi) tampil status "sedang diproses" + polling.
     Butuh $document (Document|null), $url, $label; opsional $class, $style. --}}
@if ($document?->isFinalizing())
    <span wire:poll.3s class="{{ $class ?? 'btn-primary' }}" style="{{ $style ?? '' }}opacity:.6;cursor:not-allowed;" aria-disabled="true">
        <i class="fa-solid fa-spinner fa-spin"></i> Dokumen sedang diproses...
    </span>
@else
    <a href="{{ $url }}" target="_blank" class="{{ $class ?? 'btn-primary' }}" style="{{ $style ?? '' }}">
        <i class="fa-solid fa-file-pdf"></i> {{ $label }}
    </a>
@endif
