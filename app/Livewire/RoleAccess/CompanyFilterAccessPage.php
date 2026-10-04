<?php

namespace App\Livewire\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Models\CompanyFilterAccess;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman portal "Akses Filter Perusahaan" (`/akses-filter-perusahaan`, 2026-09-12) - satu
 * grup dengan Role/Hak Akses di dropdown nav "Manajemen Pengguna". Config lapisan tambahan di
 * atas permission lihat_perusahaan_lain: per company+role/user, sistem mana saja yang boleh
 * dipakai filter lintas company. Dicek lewat User::canFilterByCompanyForSystem(). Pola halaman
 * sama persis RoleAccessTreePage (pilih company -> pilih role/user -> checkbox -> simpan),
 * cuma daftar checkbox-nya flat (9 SISTEM_*), bukan tree.
 */
#[Layout('components.layout', ['title' => 'Akses Filter Perusahaan'])]
class CompanyFilterAccessPage extends Component
{
    public ?string $companyId = null;

    /** 'role:{id}' atau 'user:{id}' - key gabungan supaya 1 dropdown/list cukup buat 2 tipe. */
    public ?string $granteeKey = null;

    public string $userSearch = '';

    /** @var array<int, string> nilai CustomPermissionEnum::SISTEM_* yang tercentang buat grantee terpilih */
    public array $checkedSystems = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_HAK_AKSES->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->selectFirstGrantee();
    }

    public function updatedCompanyId(): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->selectFirstGrantee();
    }

    public function updatedUserSearch(): void
    {
        $this->selectFirstGrantee();
    }

    public function selectGrantee(string $key): void
    {
        $this->granteeKey = $key;
        $this->loadChecked();
    }

    public function toggleSystem(string $value): void
    {
        $this->checkedSystems = in_array($value, $this->checkedSystems, true)
            ? array_values(array_diff($this->checkedSystems, [$value]))
            : [...$this->checkedSystems, $value];
    }

    private function selectFirstGrantee(): void
    {
        $this->granteeKey = $this->grantees->first()['key'] ?? null;
        $this->loadChecked();
    }

    private function loadChecked(): void
    {
        [$type, $id] = $this->parseGranteeKey();

        $this->checkedSystems = $id
            ? CompanyFilterAccess::where('company_id', $this->companyId)
                ->where('grantee_type', $type)
                ->where('grantee_id', $id)
                ->value('systems') ?? []
            : [];
    }

    /** @return array{0: ?string, 1: ?string} [type, id] */
    private function parseGranteeKey(): array
    {
        if (! $this->granteeKey || ! str_contains($this->granteeKey, ':')) {
            return [null, null];
        }

        [$type, $id] = explode(':', $this->granteeKey, 2);

        return [$type, $id];
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** Role lalu User digabung 1 list (key 'role:{id}' / 'user:{id}') - sama tampilan seperti
     * mockup: 1 kolom tengah berisi role & user sekaligus, bukan toggle mode terpisah. Cuma
     * yang BENAR-BENAR punya permission lihat_perusahaan_lain yang muncul di sini (role lewat
     * permission role itu sendiri, user lewat scope permission() bawaan Spatie yang cek direct
     * ATAU dari role) - percuma dikasih akses filter per sistem kalau gerbang utamanya saja
     * belum ada. User dibatasi 50 + wajib cari kalau company-nya ramai, sama pola
     * RoleAccessTreePage::getUsersProperty(). */
    public function getGranteesProperty()
    {
        $roles = Role::where('company_id', $this->companyId)
            ->whereHas('permissions', fn ($q) => $q->where('name', CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value))
            ->orderBy('name')->get()
            ->map(fn (Role $r) => ['key' => "role:{$r->id}", 'type' => 'role', 'name' => $r->name]);

        $search = trim($this->userSearch);
        $userQuery = User::whereCompany($this->companyId)
            ->permission(CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value)
            ->orderByName();

        if ($search !== '') {
            $userQuery->searchNameOrNip($search);
        }

        $users = $userQuery->limit(50)->get()
            ->map(fn (User $u) => ['key' => "user:{$u->id}", 'type' => 'user', 'name' => $u->name]);

        return $roles->concat($users);
    }

    /** @return array<int, CustomPermissionEnum> */
    public function getSystemOptionsProperty(): array
    {
        return array_values(array_filter(
            CustomPermissionEnum::cases(),
            fn (CustomPermissionEnum $case) => str_starts_with($case->value, 'sistem_')
        ));
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_HAK_AKSES->value), 403);

        [$type, $id] = $this->parseGranteeKey();

        if (! $type || ! $id || ! $this->companyId) {
            return;
        }

        $validValues = collect($this->systemOptions)->map(fn (CustomPermissionEnum $c) => $c->value)->all();
        $safeSystems = array_values(array_intersect($this->checkedSystems, $validValues));

        CompanyFilterAccess::updateOrCreate(
            ['company_id' => $this->companyId, 'grantee_type' => $type, 'grantee_id' => $id],
            ['systems' => $safeSystems]
        );

        $this->checkedSystems = $safeSystems;

        Notification::make()
            ->title('Akses filter perusahaan berhasil disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.role-access.company-filter-access-page');
    }
}
