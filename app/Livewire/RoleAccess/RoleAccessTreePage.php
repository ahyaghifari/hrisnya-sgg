<?php

namespace App\Livewire\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Role;
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
 */
#[Layout('components.layout', ['title' => 'Hak Akses'])]
class RoleAccessTreePage extends Component
{
    public ?string $companyId = null;
    public ?string $roleId = null;

    /** @var array<int, string> nama permission yang tercentang buat role terpilih */
    public array $checked = [];

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

        $this->selectFirstRole();
    }

    /** wire:model.live="roleId" (Select Role). */
    public function updatedRoleId(): void
    {
        $this->loadChecked();
    }

    private function selectFirstRole(): void
    {
        $this->roleId = $this->roles->first()?->id;
        $this->loadChecked();
    }

    private function loadChecked(): void
    {
        $this->checked = $this->roleId
            ? Role::findOrFail($this->roleId)->permissions->pluck('name')->all()
            : [];
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

    public function getTreeProperty(): array
    {
        return PermissionTreeBuilder::build();
    }

    public function getIsSuperAdminRoleProperty(): bool
    {
        return $this->selectedRole?->name === 'super_admin' || $this->selectedRole?->name === 'Super Admin';
    }

    public function save(array $checkedNames): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_HAK_AKSES->value), 403);

        $role = $this->roleId ? Role::find($this->roleId) : null;

        if (! $role || $role->name === 'super_admin' || $role->name === 'Super Admin') {
            return;
        }

        // Tolak nama yang bukan leaf tree valid — cegah role dikasih permission random
        // lewat request tampering (payload array dikirim manual dari client).
        $validNames = PermissionTreeBuilder::allLeafNames($this->tree);
        $safeNames = array_values(array_intersect($checkedNames, $validNames));

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
