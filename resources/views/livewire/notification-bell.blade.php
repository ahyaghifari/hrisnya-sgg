@if ($variant === 'mobile')
    {{-- x-data (bukan class JS polos + getElementById) - state buka/tutup HARUS lewat sesuatu
    yang selamat dari morph wire:poll.30s/wire:click di komponen ini. Class ".open" yang
    ditoggle manual sebelumnya TIDAK ada di HTML hasil render server, jadi tiap poll/klik
    markAsRead menimpanya balik ke tertutup - sheet keliatan "tidak ngefek" pas diklik
    (dilaporkan user 2026-09-18). Alpine x-data hidup di DOM node yang sama, selamat dari
    morph (pola sama dipakai popover Generate di ScheduleShiftRosterTab). --}}
    <div wire:poll.30s x-data="{ open: false }">
    <button type="button" @click="open = true" class="pwa-app-bar-bell" aria-label="Notifikasi">
        <i class="fa-regular fa-bell"></i>
        @if ($this->unreadCount > 0)
            <span class="pwa-app-bar-dot">{{ $this->unreadCountLabel }}</span>
        @endif
    </button>

    <div class="pwa-menu-sheet-backdrop" :class="{ open: open }" @click="open = false"></div>
    <div class="pwa-fullsheet" :class="{ open: open }">
        <div class="pwa-fullsheet-header">
            <span class="pwa-fullsheet-title">Notifikasi</span>
            <button type="button" @click="open = false" class="pwa-fullsheet-close" aria-label="Tutup">
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
    {{-- x-data (bukan .dropdown:hover CSS biasa) - dropdown ini punya tombol wire:click di
    dalamnya (mark all/mark 1 dibaca), beda dari dropdown nav lain yang isinya cuma link polos.
    Celah 10px antara tombol lonceng & menu (lihat .dropdown-menu di app.css) gampang bikin
    hover kelepas pas mouse turun mendiagonal ke tombol/item di dalam menu - klik jadi kayak
    "tidak ngefek" (dilaporkan user 2026-09-18). Alpine buka/tutup lewat klik tidak kena celah
    itu, dan state-nya selamat dari morph wire:poll.30s/wire:click (sama pola popover Generate
    di ScheduleShiftRosterTab). --}}
    <div class="dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
        <button type="button" class="nav-bell" wire:poll.30s @click="open = ! open" aria-label="Notifikasi">
            <i class="fa-regular fa-bell text-sm"></i>
            @if ($this->unreadCount > 0)
                <span class="nav-bell-dot">{{ $this->unreadCountLabel }}</span>
            @endif
        </button>

        <div class="dropdown-menu dropdown-menu-right dropdown-menu-notif" :class="{ 'is-open': open }">
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
