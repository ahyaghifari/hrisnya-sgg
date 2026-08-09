@if ($variant === 'mobile')
    <div wire:poll.30s>
    <button type="button" onclick="toggleNotifSheet()" class="pwa-app-bar-bell" aria-label="Notifikasi">
        <i class="fa-regular fa-bell"></i>
        @if ($this->unreadCount > 0)
            <span class="pwa-app-bar-dot">{{ $this->unreadCountLabel }}</span>
        @endif
    </button>

    <div id="notif-sheet-backdrop" class="pwa-menu-sheet-backdrop" onclick="toggleNotifSheet()"></div>
    <div id="notif-sheet" class="pwa-fullsheet">
        <div class="pwa-fullsheet-header">
            <span class="pwa-fullsheet-title">Notifikasi</span>
            <button type="button" onclick="toggleNotifSheet()" class="pwa-fullsheet-close" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="padding:10px 16px 0;display:flex;justify-content:flex-end;">
            <button type="button" wire:click="markAllAsRead" @disabled($this->unreadCount === 0) class="btn-ghost" style="padding:6px 12px;font-size:12px;">
                <i class="fa-solid fa-check-double"></i> Tandai Semua Dibaca
            </button>
        </div>

        <div class="pwa-fullsheet-body">
            <div class="pwa-list">
                @forelse ($this->notifications as $notification)
                    <div
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="pwa-list-row"
                        style="align-items:flex-start;cursor:pointer;{{ $notification->read_at ? '' : 'background:var(--surface-alt, rgba(4,44,108,0.04));' }}"
                    >
                        <div style="width:8px;height:8px;border-radius:50%;margin-top:6px;flex-shrink:0;background:{{ $notification->read_at ? 'transparent' : '#ef4444' }};"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13.5px;font-weight:700;color:var(--text-body);">{{ $notification->data['title'] ?? 'Notifikasi' }}</div>
                            <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">{{ $notification->data['body'] ?? '' }}</div>
                            <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="pwa-list-row" style="justify-content:center;color:var(--text-faint);font-size:13px;padding:32px 16px;">
                        Belum ada notifikasi.
                    </div>
                @endforelse
            </div>

            <div style="padding:14px 16px;">
                <a href="{{ route('notifikasi') }}" wire:navigate class="btn-ghost" style="width:100%;justify-content:center;">
                    Lihat Semua Notifikasi
                </a>
            </div>
        </div>
    </div>
    </div>
@else
    <div class="dropdown">
        <button type="button" class="nav-bell" wire:poll.30s aria-label="Notifikasi">
            <i class="fa-regular fa-bell text-sm"></i>
            @if ($this->unreadCount > 0)
                <span class="nav-bell-dot">{{ $this->unreadCountLabel }}</span>
            @endif
        </button>

        <div class="dropdown-menu dropdown-menu-right dropdown-menu-notif">
            <div class="dropdown-notif-header">
                <span>Notifikasi</span>
                <button type="button" wire:click="markAllAsRead" @disabled($this->unreadCount === 0) class="dropdown-notif-mark-all">Tandai Semua Dibaca</button>
            </div>

            <div class="dropdown-notif-list">
                @forelse ($this->notifications as $notification)
                    <div
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="dropdown-notif-item"
                        style="{{ $notification->read_at ? '' : 'background:var(--surface-alt, rgba(4,44,108,0.04));' }}"
                    >
                        <div class="dropdown-notif-dot" style="background:{{ $notification->read_at ? 'transparent' : '#ef4444' }};"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:12.5px;font-weight:700;color:var(--text-body);">{{ $notification->data['title'] ?? 'Notifikasi' }}</div>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $notification->data['body'] ?? '' }}</div>
                            <div style="font-size:10.5px;color:var(--text-faint);margin-top:2px;">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div style="padding:24px 12px;text-align:center;color:var(--text-faint);font-size:12.5px;">Belum ada notifikasi.</div>
                @endforelse
            </div>

            <a href="{{ route('notifikasi') }}" wire:navigate class="dropdown-notif-footer">Lihat Semua Notifikasi</a>
        </div>
    </div>
@endif

@once
    @push('scripts')
        <script>
            function toggleNotifSheet() {
                document.getElementById('notif-sheet').classList.toggle('open');
                document.getElementById('notif-sheet-backdrop').classList.toggle('open');
            }
        </script>
    @endpush
@endonce
