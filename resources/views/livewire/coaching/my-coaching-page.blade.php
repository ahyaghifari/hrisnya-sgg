<div>
    <x-page-header title="Coaching" size="lg" />

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
                <i class="fa-solid fa-chevron-right"></i>
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
