<?php

namespace App\Livewire\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

/**
 * Dipakai App\Livewire\Pwa\Akun (selalu diri sendiri) dan
 * App\Livewire\EmployeeAttendanceStatistics (halaman Data Diri, BISA dibuka HC/atasan untuk
 * lihat data orang lain) - abort_unless di bawah WAJIB ada di server, bukan cuma sembunyikan
 * tombol di blade, karena komponen kedua itu bisa dibuka buat lihat user lain.
 *
 * Class yang pakai trait ini harus punya properti `User $record` (target foto yang diedit).
 */
trait HandlesProfilePhotoUpload
{
    use WithFileUploads;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    public function updatedPhoto(): void
    {
        abort_unless($this->record->id === Auth::id(), 403);

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
}
