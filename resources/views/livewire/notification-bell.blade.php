@if ($variant === 'mobile')
    <a href="{{ route('notifikasi') }}" wire:navigate class="pwa-app-bar-bell" wire:poll.30s aria-label="Notifikasi">
        <i class="fa-regular fa-bell"></i>
        @if ($this->unreadCount > 0)
            <span class="pwa-app-bar-dot"></span>
        @endif
    </a>
@else
    <a href="{{ route('notifikasi') }}" wire:navigate class="nav-bell" wire:poll.30s aria-label="Notifikasi">
        <i class="fa-regular fa-bell text-sm"></i>
        @if ($this->unreadCount > 0)
            <span class="nav-bell-dot"></span>
        @endif
    </a>
@endif
