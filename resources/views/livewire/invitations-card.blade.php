{{-- Widget beranda Undangan - gaya sama dengan kartu Tugas/Pelatihan Saya (.pwa-list + .pwa-card-header), header ungu #8B5CF6 dengan font putih.
     grid-column penuh di .bento. Kosong = disembunyikan. --}}
@php $invitations = $this->invitations; @endphp
<div @if ($invitations->isEmpty()) style="display:none;" @else style="grid-column:1/-1;" @endif>
    @if ($invitations->isNotEmpty())
        <style>
            .pwa-card-header-invite { background: linear-gradient(135deg, #8B5CF6 0%, #7c4ee0 100%); }
            .pwa-card-header-invite .pwa-section-title, .pwa-card-header-invite .pwa-section-link { color: #fff; }
            .pwa-card-header-invite .pwa-card-header-deco-1, .pwa-card-header-invite .pwa-card-header-deco-2 { background: #fff; }
            .invite-ico { flex: none; width: 38px; height: 38px; border-radius: 11px; background: color-mix(in srgb, #8B5CF6 14%, transparent); color: #8B5CF6; display: flex; align-items: center; justify-content: center; font-size: 15px; }
            .invite-badge { flex: none; font-size: 11px; font-weight: 700; background: color-mix(in srgb, #8B5CF6 14%, transparent); color: #6d3fd1; border-radius: 999px; padding: 3px 10px; white-space: nowrap; }
            .invite-badge.soon { background: #8B5CF6; color: #fff; }
        </style>
        <div class="pwa-list">
            <div class="pwa-card-header pwa-card-header-invite">
                <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
                <div class="pwa-card-header-content">
                    <span class="pwa-section-title"><i class="fa-solid fa-envelope-open-text" style="font-size:12px;margin-right:4px;"></i> Undangan</span>
                    <span class="pwa-section-link" style="color:#fff;">{{ $invitations->count() }} mendatang</span>
                </div>
            </div>
            @foreach ($invitations as $invitation)
                @php
                    $days = (int) now()->startOfDay()->diffInDays($invitation->scheduled_at->copy()->startOfDay());
                    $when = $days === 0 ? 'Hari ini' : ($days === 1 ? 'Besok' : $days . ' hari lagi');
                    $mine = $invitation->user_id === auth()->id();
                @endphp
                <a href="{{ route('undangan.show', $invitation) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <span class="invite-ico"><i class="fa-solid {{ $invitation->type->icon() }}"></i></span>
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">{{ $invitation->type->label() }}{{ $mine ? '' : ' · ' . $invitation->user?->name }}</p>
                        <span style="font-size:11.5px;color:var(--text-muted);">{{ $invitation->scheduled_at->translatedFormat('l, d M Y') }} · {{ $invitation->scheduled_at->format('H:i') }} &middot; {{ $invitation->location }}</span>
                        @if ($invitation->note)
                            <span style="display:block;font-size:11.5px;color:var(--text-body);margin-top:2px;"><i class="fa-solid fa-clipboard-list" style="font-size:10px;margin-right:4px;color:#8B5CF6;"></i>{{ \Illuminate\Support\Str::limit($invitation->note, 90) }}</span>
                        @endif
                    </div>
                    <span class="invite-badge {{ $days <= 1 ? 'soon' : '' }}">{{ $when }}</span>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>
    @endif
</div>
