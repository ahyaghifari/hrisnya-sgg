<?php

namespace App\Livewire\Kepegawaian\Concerns;

use App\Enum\OrgStructurLevel;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use Illuminate\Support\Facades\Auth;

/**
 * Form Jabatan portal (4 section, mirror JobPositionResource admin) - dipakai bareng
 * CreateJabatanPage & EditJabatanPage. $record null di Create, ke-isi di Edit::mount().
 *
 * planned_manpower cuma bisa diisi saat create - perubahan kuota berikutnya cuma lewat
 * pengajuan Permintaan Tenaga Kerja terpisah (CreateJabatanQuotaPage), TIDAK lewat form ini.
 * Field lain (nama, struktur organisasi, deskripsi, dst) langsung tersimpan tanpa approval.
 */
trait HasJabatanForm
{
    public ?JobPositions $record = null;

    // Struktur Organisasi
    public ?string $company_id = null;
    public ?string $department_id = null;
    public ?string $division_id = null;
    public ?string $sub_division_id = null;

    // Detail Jabatan
    public string $name = '';
    public ?string $parent_id = null;
    public string $description = '';
    public string $qualification = '';
    public bool $is_nakes = false;

    // Job Evaluation
    public ?string $pangkat_id = null;
    public ?string $kelas_id = null;
    public ?string $job_evaluation_score = null;

    // Analisis Beban Kerja
    public ?int $planned_manpower = null;


    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->company_id = Auth::user()->company_id;
        }

        $this->department_id = null;
        $this->division_id = null;
        $this->sub_division_id = null;
        $this->parent_id = null;
        $this->pangkat_id = null;
        $this->kelas_id = null;
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

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
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

    public function getParentOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return JobPositions::where('company_id', $this->company_id)
            ->when($this->record, fn ($q) => $q->whereKeyNot($this->record->id))
            ->pluck('name', 'id');
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

        return Kelas::where('company_id', $this->company_id)->orderBy('level')->pluck('name', 'id');
    }

    protected function fillFromRecord(JobPositions $record): void
    {
        $this->record = $record;
        $this->company_id = $record->company_id;
        $this->name = $record->name;
        $this->parent_id = $record->parent_id;
        $this->description = (string) $record->description;
        $this->qualification = (string) $record->qualification;
        $this->is_nakes = (bool) $record->is_nakes;
        $this->pangkat_id = $record->pangkat_id;
        $this->kelas_id = $record->kelas_id;
        $this->job_evaluation_score = $record->job_evaluation_score !== null ? (string) $record->job_evaluation_score : null;
        $this->planned_manpower = $record->planned_manpower;

        $org = $record->org_structure_id ? OrgStructure::with('parent.parent')->find($record->org_structure_id) : null;

        if ($org?->level === OrgStructurLevel::LEVEL_DEPARTMENT->value) {
            $this->department_id = $org->id;
        } elseif ($org?->level === OrgStructurLevel::LEVEL_DIVISION->value) {
            $this->department_id = $org->parent_id;
            $this->division_id = $org->id;
        } elseif ($org?->level === OrgStructurLevel::LEVEL_SUBDIVISION->value) {
            $this->department_id = $org->parent?->parent_id;
            $this->division_id = $org->parent_id;
            $this->sub_division_id = $org->id;
        }
    }

    protected function jabatanRules(): array
    {
        return [
            'company_id' => ['required', 'exists:m_companies,id'],
            'department_id' => ['required', 'exists:m_org_structures,id'],
            // Opsional: jabatan yang berada langsung di departemen memang ada - itu pimpinan puncak
            // departemennya. Token {divisi} di naskah Persetujuan Onboarding rekrutmen jatuh ke nama
            // departemen untuk kasus itu, lihat JobPositions::getDivisionOrUnitNameAttribute().
            'division_id' => ['nullable', 'exists:m_org_structures,id'],
            'sub_division_id' => ['nullable', 'exists:m_org_structures,id'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:m_job_positions,id'],
            'description' => ['nullable', 'string'],
            'qualification' => ['nullable', 'string'],
            'is_nakes' => ['boolean'],
            'pangkat_id' => ['nullable', 'exists:m_pangkats,id'],
            'kelas_id' => ['nullable', 'exists:m_kelas,id'],
            'job_evaluation_score' => ['nullable', 'numeric', 'min:0'],
            'planned_manpower' => [$this->record ? 'nullable' : 'required', 'integer', 'min:0'],
        ];
    }

    protected function jabatanPayload(): array
    {
        $data = [
            'company_id' => $this->company_id,
            'org_structure_id' => $this->sub_division_id ?? $this->division_id ?? $this->department_id,
            'name' => $this->name,
            'parent_id' => $this->parent_id ?: null,
            'description' => $this->description !== '' ? $this->description : null,
            'qualification' => $this->qualification !== '' ? $this->qualification : null,
            'is_nakes' => $this->is_nakes,
            'pangkat_id' => $this->pangkat_id ?: null,
            'kelas_id' => $this->kelas_id ?: null,
            'job_evaluation_score' => $this->job_evaluation_score !== null && $this->job_evaluation_score !== '' ? $this->job_evaluation_score : null,
        ];

        if (! $this->record) {
            $data['planned_manpower'] = $this->planned_manpower;
        }

        return $data;
    }
}
