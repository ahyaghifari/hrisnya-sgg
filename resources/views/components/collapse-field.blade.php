{{-- Bagian isi dokumen yang bisa dibuka/tutup, default tertutup supaya halaman detail ringkas. --}}
@props(['label'])
<div x-data="{ open: false }" style="margin:0 0 6px;">
    <button type="button" @click="open = !open" style="display:flex;align-items:center;gap:6px;background:none;border:none;padding:4px 0;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:600;color:var(--text-body);">
        <i class="fa-solid fa-chevron-right" style="font-size:9px;transition:transform 0.15s;" :style="open ? 'transform:rotate(90deg)' : ''"></i> {{ $label }}
    </button>
    <div x-show="open" style="display:none;font-size:12px;line-height:1.5;color:var(--text-body);padding:4px 0 6px 15px;">{{ $slot }}</div>
</div>
