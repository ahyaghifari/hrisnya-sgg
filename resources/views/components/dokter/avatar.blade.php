{{-- Foto dokter; kalau kosong, siluet sesuai jenis kelamin (L/P). Tanpa file aset: SVG inline. --}}
@props(['photo' => null, 'gender' => null, 'size' => 72])
@php $female = $gender === 'P'; @endphp
@if ($photo)
    <img src="{{ $photo }}" alt="" style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;object-fit:cover;flex-shrink:0;background:#e2e8f0;">
@else
    <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" role="img" aria-label="{{ $female ? 'Dokter perempuan' : 'Dokter laki-laki' }}" style="border-radius:50%;flex-shrink:0;display:block;">
        <rect width="64" height="64" fill="{{ $female ? '#fde7f0' : '#d9f1ee' }}"/>
        @if ($female)
            {{-- rambut panjang di belakang kepala & bahu --}}
            <path d="M18 34c0-12 6-19 14-19s14 7 14 19v14H18z" fill="#be185d" opacity=".55"/>
        @endif
        <circle cx="32" cy="26" r="9" fill="{{ $female ? '#be185d' : '#0f766e' }}" opacity=".85"/>
        <path d="M13 64c0-12 8-19 19-19s19 7 19 19z" fill="{{ $female ? '#be185d' : '#0f766e' }}" opacity=".85"/>
    </svg>
@endif
