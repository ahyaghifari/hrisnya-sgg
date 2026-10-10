@props(['data' => []])
{{-- Penanda notifikasi undangan (klarifikasi, coaching, dst.) - ungu #8B5CF6, sama dengan widget Undangan di beranda. --}}
@if (($data['request_type'] ?? null) === 'invitation')
    <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:1px 7px;margin-bottom:3px;border-radius:99px;background:color-mix(in srgb, #8B5CF6 15%, transparent);color:#6d3fd1;">
        <i class="fa-solid fa-envelope-open-text" style="font-size:9px;"></i> {{ $data['invitation_label'] ?? 'Undangan' }}
    </span>
@endif