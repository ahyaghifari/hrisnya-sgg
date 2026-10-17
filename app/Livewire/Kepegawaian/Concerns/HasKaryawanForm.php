<?php

namespace App\Livewire\Kepegawaian\Concerns;

use App\Enum\OrgStructurLevel;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use App\Models\Master\Religions;
use App\Models\Role;
use Illuminate\Validation\Rule;

/**
 * Form Tambah/Edit Karyawan portal (3 step) - dipakai bareng CreateKaryawan & EditKaryawan
 * supaya satu-satunya sumber kebenaran field/opsi/validasi. Beda create vs edit HANYA di
 * mount() (kosong vs isi dari $record) dan save() (insert vs update) - kedua class itu sendiri
 * yang tentukan itu, trait ini cuma state form + opsi + navigasi step.
 *
 * $record nullable: null di CreateKaryawan (belum ada user), ke-isi di EditKaryawan::mount().
 * Dipakai buat bedakan aturan password (wajib diisi cuma saat create) tanpa perlu override
 * rulesForStep() di 2 tempat.
 */
trait HasKaryawanForm
{
    public ?\App\Models\User $record = null;

    public int $step = 1;

    // Step 1 — Akun & Peran
    public string $name = '';
    public string $email = '';
    public string $nip = '';
    public string $password = '';
    public ?string $company_id = null;
    /** @var array<int, string> */
    public array $role_ids = [];
    public bool $is_active = true;
    public bool $is_dokter = false;

    public bool $is_dokter_umum = false;

    // Step 2 — Data Pribadi, Alamat, Kontak
    public string $nickname = '';
    public ?string $gender = null;
    public ?string $marital_status = null;
    public ?string $religion_id = null;
    public string $place_of_birth = '';
    public ?string $date_of_birth = null;
    public string $no_nik = '';
    public string $no_kk = '';
    public string $no_bpjs_kes = '';
    public string $no_bpjs_ket = '';
    public string $npwp = '';
    public string $bank_name = '';
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
    public string $email_pribadi = '';

    // Step 3 — Data Pekerjaan
    public ?string $department_id = null;
    public ?string $division_id = null;
    public ?string $sub_division_id = null;
    public ?string $job_position_id = null;
    public ?string $pangkat_id = null;
    public ?string $kelas_id = null;
    public ?string $employee_type_id = null;
    public ?string $joining_date = null;

    public function updatedCompanyId(): void
    {
        if (! \Illuminate\Support\Facades\Auth::user()->canFilterByCompany()) {
            $this->company_id = \Illuminate\Support\Facades\Auth::user()->company_id;
        }

        $this->role_ids = [];
        $this->department_id = null;
        $this->division_id = null;
        $this->sub_division_id = null;
        $this->job_position_id = null;
        $this->pangkat_id = null;
        $this->kelas_id = null;
        $this->employee_type_id = null;
    }

    public function updatedDepartmentId(): void
    {
        $this->division_id = null;
        $this->sub_division_id = null;
    }

    /** Departemen/Divisi/Sub Divisi karyawan TIDAK disimpan di profil - murni turunan struktur jabatan
     * (jabatan.org_structure_id). Begitu jabatan dipilih/dikosongkan, tiga field tampilan di form
     * ikut diisi/dikosongkan. */
    public function updatedJobPositionId(): void
    {
        $org = $this->job_position_id
            ? JobPositions::with('orgStructure.parent.parent')->find($this->job_position_id)?->orgStructure
            : null;

        $this->department_id = $this->division_id = $this->sub_division_id = null;

        if (! $org) {
            return;
        }

        match ($org->level) {
            OrgStructurLevel::LEVEL_DEPARTMENT->value => $this->department_id = $org->id,
            OrgStructurLevel::LEVEL_DIVISION->value => [
                $this->department_id = $org->parent_id,
                $this->division_id = $org->id,
            ],
            OrgStructurLevel::LEVEL_SUBDIVISION->value => [
                $this->department_id = $org->parent?->parent_id,
                $this->division_id = $org->parent_id,
                $this->sub_division_id = $org->id,
            ],
            default => null,
        };
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

        return Pangkats::where('company_id', $this->company_id)->orderByDesc('level')->pluck('name', 'id');
    }

    public function getSelectedPangkatProperty(): ?Pangkats
    {
        return $this->pangkat_id ? Pangkats::find($this->pangkat_id) : null;
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
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->record?->id)],
                'nip' => ['nullable', 'string', 'max:50'],
                // Password wajib diisi cuma saat create (belum ada $record) - saat edit,
                // kosongkan berarti tidak ganti password lama.
                'password' => [$this->record ? 'nullable' : 'required', 'string', 'min:8'],
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
        // Edit ($record sudah ada) - datanya sudah lengkap/valid dari awal, jadi bebas lompat
        // ke step manapun tanpa harus urut. Create tetap wizard sekuensial: cuma boleh mundur
        // ke step yang sudah pernah divalidasi, tidak boleh skip maju tanpa lewat validasi
        // step di antaranya (lihat nextStep()).
        if ($this->record || $step < $this->step) {
            $this->step = max(1, min(3, $step));
        }
    }
}
