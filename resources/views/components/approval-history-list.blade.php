@props(['histories'])

@php
    $actionLabel = fn (string $action) => ['requested' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'completed' => 'Selesai'][$action] ?? $action;
    $actionBadge = fn (string $action) => match ($action) {
        'approved' => 'badge-pending',
        'rejected' => 'badge-rejected',
        'completed' => 'badge-approved',
        default => 'badge-cancelled',
    };
@endphp

<div {{ $attributes->merge(['style' => 'background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);overflow:hidden;']) }}>
    <div style="padding:14px 18px;border-bottom:1px solid var(--border-soft);">
        <span class="pwa-section-title">Riwayat Approval</span>
    </div>
    @forelse ($histories as $history)
        <div class="pwa-list-row" style="align-items:flex-start;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $history->actor_name ?? '-' }}</p>
                    <span class="badge {{ $actionBadge($history->action) }}">{{ $actionLabel($history->action) }}</span>
                </div>
                @if ($history->note)
                    <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">{{ $history->note }}</p>
                @endif
                <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">{{ $history->created_at?->translatedFormat('d F Y H:i') }}</p>
            </div>
        </div>
    @empty
        <div style="padding:24px 18px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada riwayat.</p>
        </div>
    @endforelse
</div>
