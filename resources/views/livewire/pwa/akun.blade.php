<div>
<x-page-header title="Akun" />

@php
    $profile = $record->profile;

    $initials = collect(preg_split('/\s+/', trim($record->name)))
        ->map(fn ($w) => mb_substr($w, 0, 1))
        ->take(2)
        ->implode('');

    $photoPath = $profile?->profile_picture[0] ?? null;
    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;

    $roleLabel = $record->roles->pluck('name')
        ->map(fn ($n) => ucwords(str_replace('_', ' ', $n)))
        ->implode(', ');
@endphp

{{-- Header identitas — ringkas, cuma preview. Detail lengkap ada di halaman Data Diri sendiri. --}}
<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:14px;">
        <x-photo-upload-cropper :avatar-url="$photoUrl" :initials="$initials ?: '?'" :size="56" />
        <div style="min-width:0;flex:1;">
            <h2 style="font-size:16px;font-weight:800;color:var(--text-heading);letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
            <p style="font-size:12.5px;color:var(--text-muted);margin-top:3px;">
                {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                @if ($profile?->department?->name)
                    <span style="color:var(--text-faint);">·</span> {{ $profile->department->name }}
                @endif
            </p>
            @if ($roleLabel)
                <p style="font-size:11.5px;color:var(--text-muted);margin-top:3px;">
                    <i class="fa-solid fa-user-shield" style="color:var(--text-faint);margin-right:5px;"></i>{{ $roleLabel }}
                </p>
            @endif
            @error('photo') <p style="color:var(--danger-text);font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
        </div>
        <a href="{{ route('profil-saya') }}" wire:navigate style="color:var(--text-faint);">
            <i class="fa-solid fa-chevron-right" style="font-size:12px;"></i>
        </a>
    </div>
</div>

{{-- Pemilih warna tema (primary). Client-side murni: window.setAccent() simpan ke localStorage
     + set data-theme di <html> (lihat layout.blade.php). Tidak ada state server. --}}
<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:16px 18px;margin-bottom:16px;">
    <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">Warna Tema</p>
    <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Ganti warna utama aplikasi sesuai selera.</p>
    <div style="display:flex;gap:14px;" id="accent-picker">
        <button type="button" data-accent="biru"  onclick="pickAccent('biru')"  class="accent-swatch" style="--sw:#042c6c;" aria-label="Biru"></button>
        <button type="button" data-accent="hijau" onclick="pickAccent('hijau')" class="accent-swatch" style="--sw:#047C54;" aria-label="Hijau"></button>
        <button type="button" data-accent="pink"  onclick="pickAccent('pink')"  class="accent-swatch" style="--sw:#a52a7a;" aria-label="Pink"></button>
        <button type="button" data-accent="ungu"  onclick="pickAccent('ungu')"  class="accent-swatch" style="--sw:#6e1a8c;" aria-label="Ungu"></button>
        <button type="button" data-accent="emas"  onclick="pickAccent('emas')"  class="accent-swatch" style="--sw:#8f6d1c;" aria-label="Emas"></button>
    </div>
</div>

<a href="{{ route('akun.ubah-password') }}" wire:navigate class="pwa-list-row" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);text-decoration:none;margin-bottom:16px;border-bottom:none;">
    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-lock"></i></div>
    <div style="flex:1;min-width:0;">
        <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;">Ubah Kata Sandi</p>
    </div>
    <i class="fa-solid fa-chevron-right"></i>
</a>

<a href="{{ route('akun.notifikasi') }}" wire:navigate class="pwa-list-row" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);text-decoration:none;margin-bottom:16px;border-bottom:none;">
    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-bell"></i></div>
    <div style="flex:1;min-width:0;">
        <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;">Pengaturan Notifikasi</p>
    </div>
    <i class="fa-solid fa-chevron-right"></i>
</a>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--danger-soft-bg) !important;color:var(--danger-soft-text) !important;border:none;border-radius:12px;padding:13px;font-size:13.5px;font-weight:700;cursor:pointer;">
        Keluar
    </button>
</form>
</div>

@push('scripts')
<script>
    function pickAccent(a) { window.setAccent(a); markAccent(a); }
    function markAccent(a) {
        document.querySelectorAll('#accent-picker .accent-swatch').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.accent === a);
        });
    }
    markAccent(localStorage.getItem('accent') || 'biru');
    document.addEventListener('livewire:navigated', function () {
        markAccent(localStorage.getItem('accent') || 'biru');
    });
</script>
@endpush
