<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Models\Master\Banks;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Golongans;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use App\Models\Master\Religions;
use App\Models\Role;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Tambah Karyawan versi portal — didesain ulang (bukan port langsung Filament schema
 * admin, lihat planning/2026-07-20-portal-modul-kepegawaian-karyawan-dan-navigasi-dashboard.md
 * §5.4), tapi logic penyimpanan (merge soft-deleted, syncRoles, UserJobPositions) diambil
 * persis dari App\Filament\Resources\Employees\Pages\CreateEmployee supaya perilakunya tidak
 * menyimpang dari sisi admin.
 */
#[Layout('components.layout', ['title' => 'Tambah Karyawan'])]
class CreateKaryawan extends Component
{
    public int $step = 1;

    // Step 1 — Akun & Peran
    public string $name = '';
    public string $email = '';
    public string $nip = '';
    public string $password = '';
    public ?string $company_id = null;
    /** @var array<int, string> */
    public array $role_ids = [];
    public ?string $type_nakes = null;
    public bool $is_active = true;

    // Step 2 — Data Pribadi, Alamat, Kontak
    public string $nickname = '';
    public ?string $gender = null;
    public ?string $religion_id = null;
    public string $place_of_birth = '';
    public ?string $date_of_birth = null;
    public string $no_nik = '';
    public string $no_kk = '';
    public string $no_bpjs_kes = '';
    public string $no_bpjs_ket = '';
    public string $npwp = '';
    public ?string $bank_id = null;
    public string $bank_account_number = '';
    public string $bank_account_name = '';
    public string $country = 'Indonesia';
    public string $province = '';
    public string $city = '';
    public string $district = '';
    public string $village = '';
    public string $postal_code = '';
    public string $rt = '';
    public string $rw = '';
    public string $address = '';
    public string $phone = '';
    public string $whatsapp = '';

    // Step 3 — Data Pekerjaan
    public ?string $department_id = null;
    public ?string $division_id = null;
    public ?string $sub_division_id = null;
    public ?string $job_position_id = null;
    public ?string $pangkat_id = null;
    public ?string $golongan_id = null;
    public ?string $kelas_id = null;
    public ?string $employee_type_id = null;
    public ?string $joining_date = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->company_id = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->company_id = Auth::user()->company_id;
        }

        $this->role_ids = [];
        $this->department_id = null;
        $this->division_id = null;
        $this->sub_division_id = null;
        $this->job_position_id = null;
        $this->pangkat_id = null;
        $this->golongan_id = null;
        $this->kelas_id = null;
        $this->employee_type_id = null;
    }

    public function updatedDepartmentId(): void
    {
        $this->division_id = null;
        $this->sub_division_id = null;
    }

    public function updatedDivisionId(): void
    {
        $this->sub_division_id = null;
    }

    // --- Options (computed, per step) ---

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getRoleOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return Role::where('company_id', $this->company_id)
            ->get()
            ->mapWithKeys(fn ($role) => [$role->id => ucwords(str_replace('_', ' ', $role->name))]);
    }

    public function getReligionOptionsProperty()
    {
        return Religions::pluck('name', 'id');
    }

    public function getBankOptionsProperty()
    {
        return Banks::pluck('name', 'id');
    }

    public function getDepartmentOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->company_id)
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->pluck('name', 'id');
    }

    public function getDivisionOptionsProperty()
    {
        if (! $this->department_id) {
            return collect();
        }

        return OrgStructure::where('parent_id', $this->department_id)
            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
            ->pluck('name', 'id');
    }

    public function getSubDivisionOptionsProperty()
    {
        if (! $this->division_id) {
            return collect();
        }

        return OrgStructure::where('parent_id', $this->division_id)
            ->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value)
            ->pluck('name', 'id');
    }

    public function getJobPositionOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return JobPositions::where('company_id', $this->company_id)->pluck('name', 'id');
    }

    public function getPangkatOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return Pangkats::where('company_id', $this->company_id)->pluck('name', 'id');
    }

    public function getGolonganOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return Golongans::where('company_id', $this->company_id)->pluck('name', 'id');
    }

    public function getKelasOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return Kelas::where('company_id', $this->company_id)->pluck('name', 'id');
    }

    public function getEmployeeTypeOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return EmployeeTypes::where('company_id', $this->company_id)->pluck('name', 'id');
    }

    // --- Validation per step ---

    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')],
                'nip' => ['nullable', 'string', 'max:50'],
                'password' => ['required', 'string', 'min:8'],
                'company_id' => ['required', 'exists:m_companies,id'],
                'role_ids' => ['required', 'array', 'min:1'],
            ],
            3 => [
                'job_position_id' => ['nullable', 'exists:m_job_positions,id'],
            ],
            default => [],
        };
    }

    public function nextStep(): void
    {
        $rules = $this->rulesForStep($this->step);
        if ($rules !== []) {
            $this->validate($rules);
        }

        $this->step = min(3, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goToStep(int $step): void
    {
        // Cuma boleh lompat ke step yang sudah pernah divalidasi (mundur), tidak boleh
        // skip maju tanpa lewat validasi step di antaranya.
        if ($step < $this->step) {
            $this->step = $step;
        }
    }

    private function assertJobPositionQuota(): void
    {
        if (! $this->job_position_id) {
            return;
        }

        $jobPosition = JobPositions::find($this->job_position_id);
        if (! $jobPosition) {
            return;
        }

        $used = $jobPosition->userAssignments()->count();
        $available = ($jobPosition->actual_manpower ?? 0) - $used;

        if ($available <= 0) {
            $this->addError(
                'job_position_id',
                "Kuota jabatan ini sudah penuh (actual manpower: {$jobPosition->actual_manpower}, terisi: {$used})."
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'job_position_id' => "Kuota jabatan ini sudah penuh (actual manpower: {$jobPosition->actual_manpower}, terisi: {$used}).",
            ]);
        }
    }

    public function save(): void
    {
        $this->validate($this->rulesForStep(1));
        $this->assertJobPositionQuota();

        $orgStructureId = $this->sub_division_id ?? $this->division_id ?? $this->department_id;

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'nip' => $this->nip ?: null,
            'company_id' => $this->company_id,
            'type_nakes' => $this->type_nakes,
            'is_active' => $this->is_active,
        ];

        $profileData = [
            'nickname' => $this->nickname ?: null,
            'gender' => $this->gender,
            'place_of_birth' => $this->place_of_birth ?: null,
            'date_of_birth' => $this->date_of_birth ?: null,
            'no_nik' => $this->no_nik ?: null,
            'no_kk' => $this->no_kk ?: null,
            'no_bpjs_kes' => $this->no_bpjs_kes ?: null,
            'no_bpjs_ket' => $this->no_bpjs_ket ?: null,
            'npwp' => $this->npwp ?: null,
            'bank_id' => $this->bank_id,
            'bank_account_number' => $this->bank_account_number ?: null,
            'bank_account_name' => $this->bank_account_name ?: null,
            'country' => $this->country ?: null,
            'province' => $this->province ?: null,
            'city' => $this->city ?: null,
            'district' => $this->district ?: null,
            'village' => $this->village ?: null,
            'postal_code' => $this->postal_code ?: null,
            'rt' => $this->rt ?: null,
            'rw' => $this->rw ?: null,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'whatsapp' => $this->whatsapp ?: null,
            'religion_id' => $this->religion_id,
            'org_structure_id' => $orgStructureId,
            'job_position_id' => $this->job_position_id,
            'pangkat_id' => $this->pangkat_id,
            'golongan_id' => $this->golongan_id,
            'kelas_id' => $this->kelas_id,
            'employee_type_id' => $this->employee_type_id,
            'joining_date' => $this->joining_date ?: null,
        ];

        $user = DB::transaction(function () use ($userData, $profileData) {
            // Sama seperti CreateEmployee (admin) — kalau ada user soft-deleted dengan email/NIP
            // sama, restore & update daripada bikin baris baru (hindari duplikat).
            $existing = User::withTrashed()
                ->where(function ($q) use ($userData) {
                    $q->where('email', $userData['email']);
                    if (! empty($userData['nip'])) {
                        $q->orWhere('nip', $userData['nip']);
                    }
                })
                ->first();

            if ($existing) {
                $existing->restore();
                $existing->update($userData);
                $user = $existing;
            } else {
                $user = User::create($userData);
            }

            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);

            $user->syncRoles(Role::whereIn('id', $this->role_ids)->get());

            if ($this->job_position_id) {
                $user->jobPositions()->where('job_position_id', '!=', $this->job_position_id)->delete();

                UserJobPositions::updateOrCreate(
                    ['user_id' => $user->id, 'job_position_id' => $this->job_position_id],
                    [
                        'assigned_by' => Auth::id(),
                        'start_date' => $this->joining_date ?: now(),
                        'status' => 'active',
                    ]
                );
            }

            return $user;
        });

        Notification::make()
            ->title('Karyawan berhasil ditambahkan')
            ->success()
            ->send();

        $this->redirect(route('karyawan.show', ['record' => $user, 'from' => 'karyawan']), navigate: true);
    }

    public function render()
    {
        return view('livewire.kepegawaian.create-karyawan');
    }
}
