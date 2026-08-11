<?php

namespace App\Livewire\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccess\PermissionTreeBuilder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman portal "Hak Akses" (`/hak-akses`) — pengganti tampilan checkbox datar RoleForm.php
 * (`/admin/roles`) dengan tree Container -> Subject -> Permission, biar tidak perlu scroll
 * puluhan section buat cari 1 permission. RoleForm.php TETAP ada (belum dihapus) - dua
 * tempat ini sengaja berjalan paralel dulu sampai tree ini teruji, keduanya baca/tulis
 * permission Spatie yang sama jadi tidak ada data yang bisa "beda" antara satu dengan lain.
 *
 * Mode "User" (2026-08-10) — assign permission LANGSUNG ke 1 user, di luar role-nya. Sengaja
 * ADDITIVE ONLY (instruksi user): tidak bisa mencabut/override permission yang didapat dari
 * role lewat sini, cuma nambah ekstra. Tree-nya baca/tulis `getDirectPermissions()`/
 * `syncPermissions()` milik USER (tabel pivot `model_has_permissions`), method Spatie itu
 * TIDAK menyentuh `role_has_permissions` sama sekali — jadi aman, gak mungkin sengaja/tidak
 * sengaja mengubah izin role dari mode ini.
 */
#[Layout('components.layout', ['title' => 'Hak Akses'])]
class RoleAccessTreePage extends Component
{
    public ?string $companyId = null;
    public ?string $roleId = null;

    /** 'role' | 'user' — nentuin tree ini baca/tulis permission Role atau permission langsung
     * ke User (additive, lihat docblock class). */
    public string $mode = 'role';

    public ?string $userId = null;
    public string $userSearch = '';

    /** @var array<int, string> nama permission yang tercentang buat role/user terpilih */
    public array $checked = [];

    /** @var array<int, string> nama permission yang sudah didapat user dari role-nya (mode
     * 'user' saja) - ditampilkan tercentang+disabled+badge di tree, TIDAK ikut disimpan lewat
     * save() (itu murni permission langsung/direct, lihat syncPermissions() di save()) supaya
     * tidak keliru dikira bisa dicabut dari sini (instruksi user 2026-08-11). */
    public array $roleGrantedPermissions = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_HAK_AKSES->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->selectFirstRole();
    }

    /** wire:model.live="companyId" (Select Perusahaan) — Livewire panggil otomatis tiap
     * kali properti ini berubah dari input, tidak perlu wire:click manual lagi. */
    public function updatedCompanyId(): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->mode === 'role' ? $this->selectFirstRole() : $this->selectFirstUser();
    }

    /** wire:model.live="mode" (toggle Role/User). */
    public function updatedMode(): void
    {
        $this->mode === 'role' ? $this->selectFirstRole() : $this->selectFirstUser();
    }

    /** wire:model.live.debounce="userSearch". */
    public function updatedUserSearch(): void
    {
        $this->selectFirstUser();
    }

    /** wire:model.live="roleId" (Select Role). */
    public function updatedRoleId(): void
    {
        $this->loadChecked();
    }

    /** wire:model.live="userId" (Select User). */
    public function updatedUserId(): void
    {
        $this->loadChecked();
    }

    private function selectFirstRole(): void
    {
        $this->roleId = $this->roles->first()?->id;
        $this->loadChecked();
    }

    private function selectFirstUser(): void
    {
        $this->userId = $this->users->first()?->id;
        $this->loadChecked();
    }

    private function loadChecked(): void
    {
        if ($this->mode === 'user') {
            $user = $this->userId ? User::findOrFail($this->userId) : null;

            $this->checked = $user?->getDirectPermissions()->pluck('name')->all() ?? [];
            $this->roleGrantedPermissions = $user?->getPermissionsViaRoles()->pluck('name')->unique()->values()->all() ?? [];

            return;
        }

        $this->checked = $this->roleId
            ? Role::findOrFail($this->roleId)->permissions->pluck('name')->all()
            : [];
        $this->roleGrantedPermissions = [];
    }

    /** Sama pola SalaryComponentsPage::getCompaniesProperty() — semua company selalu tampil,
     * halaman sudah digerbangi KELOLA_HAK_AKSES sendiri di mount(). */
    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getRolesProperty()
    {
        $user = Auth::user();

        if (! $user->company_id || PermissionHelper::hasGlobalRole($user)) {
            return $this->companyId
                ? Role::where('company_id', $this->companyId)->orderBy('name')->get()
                : Role::whereNull('company_id')->orderBy('name')->get();
        }

        return Role::where('company_id', $user->company_id)->orderBy('name')->get();
    }

    public function getSelectedRoleProperty(): ?Role
    {
        return $this->roleId ? Role::find($this->roleId) : null;
    }

    /** Sama pola scoping getRolesProperty() — dibatasi 50 baris + wajib cari kalau company-nya
     * ramai, biar select tidak berat/kepanjangan (bukan halaman list, jadi tidak dipaginasi). */
    public function getUsersProperty()
    {
        $user = Auth::user();
        $search = trim($this->userSearch);

        $query = User::query()->orderBy('name');

        if ($user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        } elseif ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"));
        }

        return $query->limit(50)->get();
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    public function getTreeProperty(): array
    {
        return PermissionTreeBuilder::build();
    }

    public function getIsSuperAdminRoleProperty(): bool
    {
        if ($this->mode === 'user') {
            return false;
        }

        return $this->selectedRole?->name === 'super_admin' || $this->selectedRole?->name === 'Super Admin';
    }

    public function save(array $checkedNames): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_HAK_AKSES->value), 403);

        // Tolak nama yang bukan leaf tree valid — cegah role/user dikasih permission random
        // lewat request tampering (payload array dikirim manual dari client).
        $validNames = PermissionTreeBuilder::allLeafNames($this->tree);
        $safeNames = array_values(array_intersect($checkedNames, $validNames));

        if ($this->mode === 'user') {
            $user = $this->userId ? User::find($this->userId) : null;

            if (! $user) {
                return;
            }

            // syncPermissions() di sini cuma nulis tabel pivot model_has_permissions milik
            // USER ini - TIDAK menyentuh role_has_permissions sama sekali, jadi permission
            // dari role user itu tetap utuh apa adanya (additive, bukan override).
            $user->syncPermissions($safeNames);
            $this->checked = $safeNames;

            Notification::make()
                ->title("Hak akses tambahan untuk \"{$user->name}\" berhasil disimpan")
                ->success()
                ->send();

            return;
        }

        $role = $this->roleId ? Role::find($this->roleId) : null;

        if (! $role || $role->name === 'super_admin' || $role->name === 'Super Admin') {
            return;
        }

        $role->syncPermissions($safeNames);
        $this->checked = $safeNames;

        Notification::make()
            ->title("Hak akses role \"{$role->name}\" berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.role-access.role-access-tree-page');
    }
}
