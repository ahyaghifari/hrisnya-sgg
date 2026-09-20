<?php

namespace App\Livewire\Pwa;

use App\Livewire\Concerns\HandlesProfilePhotoUpload;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdmin;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Akun'])]
class Akun extends Component
{
    use HandlesProfilePhotoUpload;

    public User $record;

    /** True kalau akun ini tersambung ke SSO Keycloak (keycloak_sub terisi) DAN Admin API
     * terkonfigurasi - dipakai kartu "SSO" buat preview username tanpa buka
     * UbahAkunLogin dulu (instruksi user 2026-09-17). */
    public bool $ssoLinked = false;

    public ?string $ssoUsername = null;

    public function mount(): void
    {
        $this->record = Auth::user()->load(['roles', 'profile.jobPosition.orgStructure.parent.parent']);
        $this->ssoLinked = (bool) $this->record->keycloak_sub && KeycloakAdmin::isConfigured();

        if (! $this->ssoLinked) {
            return;
        }

        try {
            $kcUser = app(KeycloakAdmin::class)->find($this->record->keycloak_sub);
            $this->ssoUsername = $kcUser['username'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            // Server SSO tidak bisa dihubungi - kartu tetap tampil, cuma username-nya kosong
            // (subtitle jatuh balik ke email saja, lihat blade).
        }
    }

    public function render()
    {
        return view('livewire.pwa.akun');
    }
}
