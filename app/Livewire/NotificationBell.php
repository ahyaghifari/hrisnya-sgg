<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationBell extends Component
{
    public string $variant = 'desktop';

    protected int $previewLimit = 8;

    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function getUnreadCountLabelProperty(): string
    {
        return $this->unreadCount > 99 ? '99+' : (string) $this->unreadCount;
    }

    public function getNotificationsProperty()
    {
        return Auth::user()->notifications()->latest()->limit($this->previewLimit)->get();
    }

    public function markAsRead(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
