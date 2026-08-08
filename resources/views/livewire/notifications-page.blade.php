<div>
    <x-page-header title="Notifikasi" subtitle="{{ $this->unreadCount > 0 ? $this->unreadCount . ' belum dibaca' : 'Semua sudah dibaca' }}" />

    @if ($this->unreadCount > 0)
        <div class="mb-4" style="display:flex;justify-content:flex-end;">
            <button type="button" wire:click="markAllAsRead" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">
                <i class="fa-solid fa-check-double"></i> Tandai Semua Dibaca
            </button>
        </div>
    @endif

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

    @if ($this->notifications->hasPages())
        <div style="padding:14px 0;">
            {{ $this->notifications->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>
