<div>
    <x-page-header title="Coaching" size="lg" />

    @php
        $badgeClass = fn (string $status) => match ($status) {
            'completed' => 'badge-approved',
            'rejected' => 'badge-rejected',
            'in_progress', 'submitted' => 'badge-pending',
            default => 'badge-cancelled',
        };
    @endphp

    <div class="pwa-list">
        @forelse ($coachings as $coaching)
            <div class="pwa-list-row">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $coaching->user?->name ?? '-' }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        {{ $coaching->title }}
                        &middot; Coach: {{ $coaching->coachBy?->name ?? '-' }}
                    </span>
                </div>
                <span class="badge {{ $badgeClass($coaching->status) }}">
                    {{ \App\Enum\CoachingStatus::tryFrom($coaching->status)?->getLabel() ?? $coaching->status }}
                </span>
            </div>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada coaching</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Coaching yang Anda buat untuk bawahan akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
</div>
