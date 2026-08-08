<?php

namespace App\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layout', ['title' => 'Notifikasi'])]
class NotificationsPage extends Component
{
    use WithPagination;

    protected int $perPage = 20;

    public function getNotificationsProperty(): LengthAwarePaginator
    {
        return Auth::user()->notifications()->paginate($this->perPage);
    }

    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
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
        return view('livewire.notifications-page');
    }
}
