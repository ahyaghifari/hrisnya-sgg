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
            <a href="{{ route('coaching.show', $coaching) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $coaching->title }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        Coach: {{ $coaching->coachBy?->name ?? '-' }}
                        @if ($coaching->improvement_due_date)
                            &middot; Deadline {{ \Carbon\Carbon::parse($coaching->improvement_due_date)->translatedFormat('d M Y') }}
                        @endif
                    </span>
                </div>
                <span class="badge {{ $badgeClass($coaching->status) }}">
                    {{ \App\Enum\CoachingStatus::tryFrom($coaching->status)?->getLabel() ?? $coaching->status }}
                </span>
            </a>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada coaching</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Riwayat coaching untuk Anda akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
</div>
