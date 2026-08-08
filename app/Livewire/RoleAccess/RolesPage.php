<?php

namespace App\Livewire\RoleAccess;

use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Role;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman portal "Role" (`/role`) — CRUD role POLOS (nama + perusahaan) saja, sengaja TIDAK
 * dibundel dengan checkbox permission seperti RoleForm.php (Filament `/admin/roles`). Assign
 * permission ke role tetap lewat halaman terpisah "Hak Akses" (RoleAccessTreePage) - dua
 * dropdown "Role" & "Hak Akses" ini sekarang satu grup nav "Manajemen Pengguna" (instruksi user
 * 2026-08-08), tapi tanggung jawabnya tetap dipisah: sini cuma bikin/ubah-nama/hapus role,
 * di sana isi permission-nya.
 */
#[Layout('components.layout', ['title' => 'Role'])]
class RolesPage extends Component
{
    public ?string $companyId = null;

    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formName = null;
    public ?string $formCompanyId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', Role::class), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** Sama logikanya dengan RoleAccessTreePage::getRolesProperty() - role global (company_id
     * null, mis. Super Admin) cuma kelihatan buat user holding, role lain scoped per company. */
    public function getRolesProperty()
    {
        $user = Auth::user();

        if (! $user->company_id || PermissionHelper::hasGlobalRole($user)) {
            return $this->companyId
                ? Role::where('company_id', $this->companyId)->withCount(['users', 'permissions'])->orderBy('name')->get()
                : Role::whereNull('company_id')->withCount(['users', 'permissions'])->orderBy('name')->get();
        }

        return Role::where('company_id', $user->company_id)->withCount(['users', 'permissions'])->orderBy('name')->get();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', Role::class);
    }

    private function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, ['super_admin', 'Super Admin'], true);
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', Role::class), 403);

        $this->editingId = null;
        $this->formName = null;
        $this->formCompanyId = $this->companyId;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $role = Role::findOrFail($id);

        abort_unless(Auth::user()->can('update', $role), 403);
        abort_if($this->isProtectedRole($role), 403, 'Role Super Admin tidak bisa diubah dari sini.');

        $this->editingId = $role->id;
        $this->formName = $role->name;
        $this->formCompanyId = $role->company_id;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        $ability = $this->editingId ? 'update' : 'create';
        $subject = $this->editingId ? Role::findOrFail($this->editingId) : Role::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $companyRequired = Auth::user()->company_id !== null && ! PermissionHelper::hasGlobalRole(Auth::user());

        $this->validate([
            'formName' => 'required|string|max:255',
            'formCompanyId' => $companyRequired ? 'required|exists:m_companies,id' : 'nullable|exists:m_companies,id',
        ], [], [
            'formName' => 'nama role',
            'formCompanyId' => 'perusahaan',
        ]);

        $duplicate = Role::where('name', $this->formName)
            ->where('guard_name', 'web')
            ->where('company_id', $this->formCompanyId)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('formName', 'Role dengan nama ini sudah ada di perusahaan tersebut.');

            return;
        }

        $data = [
            'name' => $this->formName,
            'guard_name' => 'web',
            'company_id' => $this->formCompanyId,
        ];

        if ($this->editingId) {
            Role::whereKey($this->editingId)->update($data);
        } else {
            Role::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Role berhasil diubah' : 'Role baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function removeRole(string $id): void
    {
        $role = Role::withCount('users')->findOrFail($id);

        abort_unless(Auth::user()->can('delete', $role), 403);
        abort_if($this->isProtectedRole($role), 403, 'Role Super Admin tidak bisa dihapus.');
        abort_if($role->users_count > 0, 403, 'Role ini masih dipakai oleh user - pindahkan usernya dulu sebelum dihapus.');

        $role->delete();

        Notification::make()
            ->title('Role berhasil dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.role-access.roles-page');
    }
}
