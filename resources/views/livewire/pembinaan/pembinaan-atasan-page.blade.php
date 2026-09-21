<div>
<x-page-header title="Coaching & Counseling" subtitle="Coaching & counseling yang kamu tangani sebagai coach/konselor" />

<div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;margin-bottom:14px;">
    <button type="button" wire:click="setActiveTab('coaching')" style="padding:6px 16px;font-size:12.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $activeTab === 'coaching' ? 'var(--surface)' : 'transparent' }};color:{{ $activeTab === 'coaching' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $activeTab === 'coaching' ? 'var(--shadow-card)' : 'none' }};"><i class="fa-solid fa-clipboard-check"></i> Coaching</button>
    <button type="button" wire:click="setActiveTab('counseling')" style="padding:6px 16px;font-size:12.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $activeTab === 'counseling' ? 'var(--surface)' : 'transparent' }};color:{{ $activeTab === 'counseling' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $activeTab === 'counseling' ? 'var(--shadow-card)' : 'none' }};"><i class="fa-solid fa-comments"></i> Counseling</button>
</div>

@if ($activeTab === 'coaching')
    @php $coachings = $this->coachings; @endphp
    <div class="pwa-list">
        @forelse ($coachings as $coaching)
            <a href="{{ route('coaching.show', $coaching) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $coaching->user?->name ?? '-' }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">{{ $coaching->title }}</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada coaching</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Coaching yang kamu tangani sebagai coach akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
    <div style="margin-top:16px;">
        {{ $coachings->links('livewire.partials.pagination') }}
    </div>
@else
    @php $counselings = $this->counselings; @endphp
    <div class="pwa-list">
        @forelse ($counselings as $counseling)
            <a href="{{ route('counseling.kelola.show', $counseling) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $counseling->user?->name ?? '-' }}</p>
                    @if ($counseling->isContentVisibleTo(auth()->user()))
                        <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $counseling->issue }}</p>
                    @else
                        <p style="font-size:11.5px;color:var(--text-faint);margin-top:2px;"><i class="fa-solid fa-lock"></i> Rahasia</p>
                    @endif
                </div>
                <span class="badge {{ $counseling->status === 'done' ? 'badge-approved' : 'badge-pending' }}">
                    {{ \App\Enum\CounselingStatus::tryFrom($counseling->status)?->getLabel() ?? $counseling->status }}
                </span>
            </a>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada counseling</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Konsul yang kamu tangani sebagai konselor akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
    <div style="margin-top:16px;">
        {{ $counselings->links('livewire.partials.pagination') }}
    </div>
@endif
</div>
