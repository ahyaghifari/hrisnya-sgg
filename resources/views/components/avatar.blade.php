@props(['user', 'size' => 32, 'title' => null])
@php
    $photoPath = $user?->profilePhotoPath();
    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
    $initials = collect(explode(' ', $user?->full_name ?? '?'))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $fontSize = max(9, (int) ($size * 0.36));
@endphp
<div title="{{ $title ?? $user?->full_name }}" style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;background:#042c6c;display:flex;align-items:center;justify-content:center;color:#fff;font-size:{{ $fontSize }}px;font-weight:700;flex-shrink:0;overflow:hidden;">
    @if ($photoUrl)
        <img src="{{ $photoUrl }}" alt="{{ $user?->full_name }}" style="width:100%;height:100%;object-fit:cover;">
    @else
        {{ $initials ?: '?' }}
    @endif
</div>
