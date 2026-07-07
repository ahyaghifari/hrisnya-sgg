<?php

namespace App\Livewire\Pwa;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.pwa-layout', ['title' => 'Akun'])]
class Akun extends Component
{
    use WithFileUploads;

    public User $record;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(): void
    {
        $this->record = Auth::user()->load(['profile.jobPosition', 'profile.orgStructure.parent.parent']);
    }

    public function updatedPhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        $profile = $this->record->profile;

        if (! $profile) {
            $this->addError('photo', 'Profil belum tersedia, hubungi HC.');
            return;
        }

        $oldPath = $profile->profile_picture[0] ?? null;

        $path = $this->photo->store('profile-pictures', 'public');

        $profile->update(['profile_picture' => [$path]]);

        if ($oldPath && is_string($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->photo = null;
        $this->record->refresh();
        $this->record->load('profile');

        Notification::make()
            ->title('Foto profil berhasil diperbarui')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pwa.akun');
    }
}
