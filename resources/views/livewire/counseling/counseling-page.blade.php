<div>
    <x-page-header title="Counseling" size="lg" />

    <div class="pwa-list">
        @forelse ($counselings as $counseling)
            <div class="pwa-list-row">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ \Illuminate\Support\Str::limit($counseling->issue, 60) }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">
                        {{ $counseling->created_at->translatedFormat('d M Y') }}
                        @if ($counseling->response)
                            &middot; Ditanggapi {{ $counseling->respondedBy?->name ?? 'HR' }}
                        @endif
                    </span>
                </div>
                <span class="badge {{ $counseling->status === 'done' ? 'badge-approved' : 'badge-pending' }}">
                    {{ \App\Enum\CounselingStatus::tryFrom($counseling->status)?->getLabel() ?? $counseling->status }}
                </span>
            </div>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada counseling</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Keluhan/counseling yang Anda ajukan akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
</div>
