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
@endphp

{{-- Header identitas — ringkas, cuma preview. Detail lengkap ada di halaman Data Diri sendiri. --}}
<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:14px;">
        <label style="position:relative;flex-shrink:0;cursor:pointer;">
            @if ($photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="{{ $record->name }}" style="width:56px;height:56px;border-radius:50%;object-fit:cover;opacity:0.6;">
            @elseif ($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $record->name }}" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
            @else
                <div style="width:56px;height:56px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:17px;display:flex;align-items:center;justify-content:center;">
                    {{ $initials ?: '?' }}
                </div>
            @endif
            <div style="position:absolute;bottom:-2px;right:-2px;width:20px;height:20px;border-radius:50%;background:#042c6c;border:2px solid #fff;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-camera" style="color:#fff;font-size:9px;" wire:loading.remove wire:target="photo"></i>
                <i class="fa-solid fa-spinner fa-spin" style="color:#fff;font-size:9px;" wire:loading wire:target="photo"></i>
            </div>
            <input type="file" wire:model="photo" accept="image/*" style="position:absolute;inset:0;opacity:0;width:56px;height:56px;cursor:pointer;">
        </label>
        <div style="min-width:0;flex:1;">
            <h2 style="font-size:16px;font-weight:800;color:#0f172a;letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
            <p style="font-size:12.5px;color:#64748b;margin-top:3px;">
                {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                @if ($profile?->department?->name)
                    <span style="color:#cbd5e1;">·</span> {{ $profile->department->name }}
                @endif
            </p>
            @error('photo') <p style="color:#ef4444;font-size:11px;margin-top:3px;">{{ $message }}</p> @enderror
        </div>
        <a href="{{ route('data-diri') }}" wire:navigate style="color:#cbd5e1;">
            <i class="fa-solid fa-chevron-right" style="font-size:12px;"></i>
        </a>
    </div>
</div>

<a href="{{ route('akun.ubah-password') }}" wire:navigate class="pwa-list-row" style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);text-decoration:none;margin-bottom:16px;border-bottom:none;">
    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-lock"></i></div>
    <div style="flex:1;min-width:0;">
        <p style="font-size:13.5px;font-weight:600;color:#1e293b;margin:0;">Ubah Kata Sandi</p>
    </div>
    <i class="fa-solid fa-chevron-right"></i>
</a>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:#fee2e2 !important;color:#b91c1c !important;border:none;border-radius:12px;padding:13px;font-size:13.5px;font-weight:700;cursor:pointer;">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
    </button>
</form>
</div>
