@props(['avatarUrl' => null, 'initials' => '?', 'size' => 56, 'wireModel' => 'photo'])

{{-- Crop 1:1 di browser SEBELUM upload (Cropper.js, lihat window.photoCropper di app.js) - input
file di sini SENGAJA tanpa wire:model, hasil crop yang dikirim ke Livewire lewat $wire.upload()
manual, bukan file mentah yang dipilih user. --}}
<div x-data="photoCropper('{{ $wireModel }}')" style="position:relative;flex-shrink:0;width:{{ $size }}px;height:{{ $size }}px;">
    <label style="position:relative;display:block;width:{{ $size }}px;height:{{ $size }}px;cursor:pointer;">
        <template x-if="previewUrl">
            <img :src="previewUrl" alt="" style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;object-fit:cover;">
        </template>
        <template x-if="!previewUrl">
            <div>
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;object-fit:cover;">
                @else
                    <div style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:{{ (int) ($size * 0.3) }}px;display:flex;align-items:center;justify-content:center;">{{ $initials }}</div>
                @endif
            </div>
        </template>

        <div style="position:absolute;bottom:-2px;right:-2px;width:20px;height:20px;border-radius:50%;background:#042c6c;border:2px solid var(--surface);display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid fa-camera" style="color:#fff;font-size:9px;" x-show="!uploading"></i>
            <i class="fa-solid fa-spinner fa-spin" style="color:#fff;font-size:9px;" x-show="uploading" x-cloak></i>
        </div>

        <input type="file" accept="image/*" x-ref="fileInput" x-on:change="onFileSelected" style="position:absolute;inset:0;opacity:0;width:{{ $size }}px;height:{{ $size }}px;cursor:pointer;">
    </label>

    <div x-show="cropping" x-cloak class="photo-cropper-overlay" x-on:click.self="cancelCrop">
        <div class="photo-cropper-box">
            <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Sesuaikan Foto</h3>
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 12px;">Geser dan perbesar untuk memilih bagian foto yang dipakai.</p>
            <div class="photo-cropper-canvas-wrap">
                <img x-ref="cropImage" style="max-width:100%;display:block;">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button type="button" class="btn-ghost" x-on:click="cancelCrop">Batal</button>
                <button type="button" class="btn-primary" x-on:click="confirmCrop">Gunakan Foto</button>
            </div>
        </div>
    </div>
</div>
