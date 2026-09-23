<?php

namespace App\Livewire\Pwa;

use App\Services\Keycloak\KeycloakAdmin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Password akun SSO (Keycloak) sendiri — dulu juga bisa ubah username, SEKARANG TIDAK LAGI
 * (instruksi user 2026-09-23): username SSO dibakukan = NIP (konsisten dengan cara admin bikin
 * akun lewat panel /keycloak), jadi di sini cuma ditampilkan read-only, tidak bisa diketik ulang.
 *
 * Kalau user belum punya akun SSO (keycloak_sub kosong), halaman ini SEKARANG BISA bikinkan
 * sendiri (dulu cuma suruh lapor ke IT) — username otomatis NIP, cukup minta set password.
 *
 * Password bisa dipilih: SAMA dengan password HRIS (mereka ketik ulang password HRIS mereka,
 * kita verifikasi lewat password_verify() lalu pakai nilai yang sama buat Keycloak — bukan
 * baca hash HRIS, itu tidak mungkin dibalik) atau password KHUSUS SSO yang beda.
 */
#[Layout('components.layout', ['title' => 'Password SSO'])]
class UbahAkunLogin extends Component
{
    public bool $ssoLinked = false;

    public ?string $nip = null;

    /** 'same_as_hris' | 'custom' */
    public string $passwordMode = 'same_as_hris';

    /** Dipakai kalau passwordMode = same_as_hris — buat verifikasi SEKALIGUS nilai yang dipakai. */
    public ?string $hrisPassword = null;

    /** Dipakai kalau passwordMode = custom. */
    public ?string $password = null;

    public ?string $password_confirmation = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->nip = $user->nip;
        $this->ssoLinked = (bool) $user->keycloak_sub && KeycloakAdmin::isConfigured();
    }

    protected function rules(): array
    {
        if ($this->passwordMode === 'same_as_hris') {
            return [
                'hrisPassword' => ['required', 'string'],
            ];
        }

        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function save(): void
    {
        if (! KeycloakAdmin::isConfigured()) {
            abort(403);
        }

        $data = $this->validate();
        $user = Auth::user();

        if ($this->passwordMode === 'same_as_hris') {
            if (! password_verify($data['hrisPassword'], $user->password)) {
                $this->addError('hrisPassword', 'Password HRIS tidak sesuai.');

                return;
            }

            $newPassword = $data['hrisPassword'];
        } else {
            $newPassword = $data['password'];
        }

        try {
            if ($this->ssoLinked) {
                app(KeycloakAdmin::class)->setPassword($user->keycloak_sub, $newPassword, false);
            } else {
                // Belum punya akun SSO — bikinkan sekarang, username dibakukan = NIP.
                $id = app(KeycloakAdmin::class)->create([
                    'username' => $user->nip,
                    'email' => $user->email ?: null,
                    'enabled' => true,
                    'attributes' => ['nip' => [$user->nip]],
                ]);
                app(KeycloakAdmin::class)->setPassword($id, $newPassword, false);
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

        $this->reset(['hrisPassword', 'password', 'password_confirmation']);

        Notification::make()
            ->title('Password SSO berhasil disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pwa.ubah-akun-login');
    }
}
