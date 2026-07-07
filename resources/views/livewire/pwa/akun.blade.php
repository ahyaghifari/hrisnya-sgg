<div>
<div class="mb-6">
    <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
    <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Akun</h1>
</div>

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
<a href="{{ route('pwa.data-diri') }}" wire:navigate style="display:block;background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;text-decoration:none;">
    <div style="display:flex;align-items:center;gap:14px;">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $record->name }}" style="flex-shrink:0;width:56px;height:56px;border-radius:50%;object-fit:cover;">
        @else
            <div style="flex-shrink:0;width:56px;height:56px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:17px;display:flex;align-items:center;justify-content:center;">
                {{ $initials ?: '?' }}
            </div>
        @endif
        <div style="min-width:0;flex:1;">
            <h2 style="font-size:16px;font-weight:800;color:#0f172a;letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
            <p style="font-size:12.5px;color:#64748b;margin-top:3px;">
                {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                @if ($profile?->department?->name)
                    <span style="color:#cbd5e1;">·</span> {{ $profile->department->name }}
                @endif
            </p>
        </div>
        <i class="fa-solid fa-chevron-right" style="color:#cbd5e1;font-size:12px;"></i>
    </div>
</a>

<a href="{{ route('pwa.akun.ubah-password') }}" wire:navigate class="pwa-list-row" style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);text-decoration:none;margin-bottom:16px;border-bottom:none;">
    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-lock"></i></div>
    <div style="flex:1;min-width:0;">
        <p style="font-size:13.5px;font-weight:600;color:#1e293b;margin:0;">Ubah Kata Sandi</p>
    </div>
    <i class="fa-solid fa-chevron-right"></i>
</a>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:#fee2e2;color:#b91c1c;border:none;border-radius:12px;padding:13px;font-size:13.5px;font-weight:700;cursor:pointer;">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
    </button>
</form>
</div>
