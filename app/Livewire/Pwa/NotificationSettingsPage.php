<?php

namespace App\Livewire\Pwa;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Pengaturan Notifikasi'])]
class NotificationSettingsPage extends Component
{
    /** @var array<string, bool> */
    public array $selections = [];

    public function mount(NotificationPreferenceService $service): void
    {
        $user = Auth::user();

        $this->selections = collect(NotificationTypeEnum::cases())
            ->mapWithKeys(fn (NotificationTypeEnum $case) => [$case->value => $service->isEnabled($user, $case)])
            ->all();
    }

    public function save(NotificationPreferenceService $service): void
    {
        $user = Auth::user();

        foreach (NotificationTypeEnum::cases() as $case) {
            $service->setEnabled($user, $case, (bool) ($this->selections[$case->value] ?? true));
        }

        Notification::make()
            ->title('Pengaturan notifikasi disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pwa.notification-settings-page');
    }
}
