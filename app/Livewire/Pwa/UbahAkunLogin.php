<?php

namespace App\Livewire\Pwa;

use App\Services\Keycloak\KeycloakAdmin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ubah username & password akun SSO (Keycloak) sendiri - dulu cuma bisa lewat admin panel
 * /keycloak (instruksi user 2026-09-17). SENGAJA DIPISAH TOTAL dari password akun LOKAL HRIS
 * (UbahPassword) - dua akun, dua password, tidak disamakan/disinkron otomatis (revisi instruksi
 * user 2026-09-17: sebelumnya sempat disatukan, ternyata maunya jelas terpisah). Email TIDAK
 * BISA diubah dari sini. Akun SSO sendiri juga TIDAK BISA dibuat sendiri lewat halaman ini -
 * kalau user belum tersambung (keycloak_sub kosong), cuma ditampilkan keterangan suruh lapor ke
 * IT, form-nya tidak ditampilkan sama sekali.
 */
#[Layout('components.layout', ['title' => 'Username & Password SSO'])]
class UbahAkunLogin extends Component
{
    public ?string $username = null;

    /** Kosong = password SSO TIDAK diganti (instruksi user 2026-09-17) - cuma username yang
     * ditulis ulang kalau field ini dibiarkan kosong. */
    public ?string $password = null;

    public ?string $password_confirmation = null;

    public bool $ssoLinked = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->ssoLinked = (bool) $user->keycloak_sub && KeycloakAdmin::isConfigured();

        if (! $this->ssoLinked) {
            return;
        }

        try {
            $kcUser = app(KeycloakAdmin::class)->find($user->keycloak_sub);
            $this->username = $kcUser['username'] ?? null;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Tidak bisa ambil data Keycloak')
                ->body('Server SSO sedang tidak bisa dihubungi - username belum bisa ditampilkan/diubah sekarang.')
                ->warning()
                ->send();
        }
    }

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function save(): void
    {
        // Form tidak dirender kalau ! ssoLinked (lihat blade) - dicek ulang di sini supaya
        // request yang di-craft manual tidak bisa nembus dan nulis Keycloak buat akun yang
        // belum ada/tidak terkait.
        abort_unless($this->ssoLinked, 403);

        $data = $this->validate();
        $user = Auth::user();

        try {
            app(KeycloakAdmin::class)->update($user->keycloak_sub, ['username' => $data['username']]);

            if (filled($data['password'])) {
                app(KeycloakAdmin::class)->setPassword($user->keycloak_sub, $data['password']);
            }
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Gagal menyimpan ke Keycloak')
                ->body('Perubahan belum tersimpan - sinkronisasi ke SSO gagal, coba lagi nanti.')
                ->danger()
                ->send();

            return;
        }

        $this->reset(['password', 'password_confirmation']);

        Notification::make()
            ->title(filled($data['password']) ? 'Username & password SSO berhasil diperbarui' : 'Username SSO berhasil diperbarui')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pwa.ubah-akun-login');
    }
}
