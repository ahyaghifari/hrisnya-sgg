<?php

namespace App\Livewire\Pwa;

use App\Services\Auth\AuthService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Password akun LOKAL HRIS - beda dari password SSO Keycloak (lihat UbahAkunLogin, "Username &
 * Password SSO"). Judul halaman ini sengaja polos "Ubah Password" TANPA embel-embel "HRIS" -
 * yang perlu dibedakan cuma pas 2 pilihan ini berdampingan (kartu di halaman Akun), bukan di
 * judul halamannya sendiri (instruksi user 2026-09-17). */
#[Layout('components.layout', ['title' => 'Ubah Password'])]
class UbahPassword extends Component
{
    public ?string $current_password = null;
    public ?string $new_password = null;
    public ?string $new_password_confirmation = null;

    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function changePassword(AuthService $authService): void
    {
        $data = $this->validate();

        $success = $authService->changePassword(Auth::user(), $data['current_password'], $data['new_password']);

        if (! $success) {
            $this->addError('current_password', 'Kata sandi saat ini salah.');
            return;
        }

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        Notification::make()
            ->title('Kata sandi HRIS berhasil diubah')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pwa.ubah-password');
    }
}
