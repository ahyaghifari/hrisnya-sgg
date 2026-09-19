<?php

namespace App\Livewire;

use App\Support\TaskNotificationUrl;
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
        $notification = Auth::user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        // Notifikasi tugas: langsung buka halaman terkait (Board kalau tugas ada di Board).
        if ($notification && ($url = TaskNotificationUrl::for($notification->data))) {
            $this->redirect($url, navigate: true);
        }
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
