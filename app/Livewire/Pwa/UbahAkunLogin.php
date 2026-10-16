<?php

namespace App\Livewire\Pwa;

use App\Services\Keycloak\KeycloakAdmin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Aktivasi akun SSO (Keycloak). Konsep (instruksi user 2026-10-16): password SSO = password HRIS,
 * username SSO = NIP. User cukup ketik password HRIS-nya; kita verifikasi lewat password_verify()
 * lalu pakai nilai yang sama buat Keycloak (hash HRIS tidak bisa dibalik). Ganti/reset password
 * HRIS berikutnya otomatis disinkron ke Keycloak lewat AuthService.
 *
 * Kalau akun SSO sudah ada (keycloak_sub terisi), form yang sama dipakai buat menyamakan ulang.
 */
#[Layout('components.layout', ['title' => 'Password SSO'])]
class UbahAkunLogin extends Component
{
    public bool $ssoLinked = false;

    public ?string $nip = null;

    public ?string $hrisPassword = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->nip = $user->nip;
        $this->ssoLinked = (bool) $user->keycloak_sub && KeycloakAdmin::isConfigured();
    }

    public function save(): void
    {
        if (! KeycloakAdmin::isConfigured()) {
            abort(403);
        }

        $data = $this->validate(['hrisPassword' => ['required', 'string']]);
        $user = Auth::user();

        if (! password_verify($data['hrisPassword'], $user->password)) {
            $this->addError('hrisPassword', 'Password HRIS tidak sesuai.');

            return;
        }

        try {
            if ($this->ssoLinked) {
                app(KeycloakAdmin::class)->setPassword($user->keycloak_sub, $data['hrisPassword'], false);
            } else {
                // Belum punya akun SSO — bikinkan sekarang, username dibakukan = NIP.
                $id = app(KeycloakAdmin::class)->create([
                    'username' => $user->nip,
                    'email' => $user->email ?: null,
                    'enabled' => true,
                    'attributes' => ['nip' => [$user->nip]],
                ]);
                app(KeycloakAdmin::class)->setPassword($id, $data['hrisPassword'], false);
                $user->forceFill(['keycloak_sub' => $id])->save();
                $this->ssoLinked = true;
            }
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Gagal menyimpan ke Keycloak')
                ->body('Perubahan belum tersimpan — sinkronisasi ke SSO gagal, coba lagi nanti.')
                ->danger()
                ->send();

            return;
        }

        $this->reset('hrisPassword');

        Notification::make()
            ->title('Akun SSO aktif — password sama dengan password HRIS')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pwa.ubah-akun-login');
    }
}
