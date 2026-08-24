<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\OrgStructurLevel;
use App\Livewire\Kepegawaian\Concerns\HasKaryawanForm;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Edit Karyawan'])]
class EditKaryawan extends Component
{
    use HasKaryawanForm;

    public function mount(User $record): void
    {
        abort_unless(
            Auth::user()->canFilterByCompany() || Auth::user()->company_id === $record->company_id,
            403
        );
        abort_unless(Auth::user()->can('update', $record), 403);

        $this->record = $record;
        $profile = $record->profile;

        $this->name = $record->name;
        $this->email = $record->email;
        $this->nip = $record->nip ?? '';
        $this->company_id = $record->company_id;
        $this->type_nakes = $record->type_nakes;
        $this->is_active = (bool) $record->is_active;
        // Bug lama: role tidak ke-load otomatis saat edit - harus diisi eksplisit di sini,
        // beda dari properti lain yang kebetulan sama nama kolomnya dengan field $record.
        $this->role_ids = $record->roles->pluck('id')->map(fn ($id) => (string) $id)->all();

        if ($profile) {
            $this->nickname = $profile->nickname ?? '';
            $this->gender = $profile->gender;
            $this->marital_status = $profile->marital_status;
            $this->religion_id = $profile->religion_id;
            $this->place_of_birth = $profile->place_of_birth ?? '';
            $this->date_of_birth = $profile->date_of_birth;
            $this->no_nik = $profile->no_nik ?? '';
            $this->no_kk = $profile->no_kk ?? '';
            $this->no_bpjs_kes = $profile->no_bpjs_kes ?? '';
            $this->no_bpjs_ket = $profile->no_bpjs_ket ?? '';
            $this->npwp = $profile->npwp ?? '';
            $this->bank_name = $profile->bank_name ?? '';
            $this->bank_account_number = $profile->bank_account_number ?? '';
            $this->bank_account_name = $profile->bank_account_name ?? '';
            $this->country = $profile->country ?? 'Indonesia';
            $this->province = $profile->province ?? '';
            $this->city = $profile->city ?? '';
            $this->district = $profile->district ?? '';
            $this->village = $profile->village ?? '';
            $this->postal_code = $profile->postal_code ?? '';
            $this->rt = $profile->rt ?? '';
            $this->rw = $profile->rw ?? '';
            $this->address = $profile->address ?? '';
            $this->phone = $profile->phone ?? '';
            $this->whatsapp = $profile->whatsapp ?? '';
            $this->email_pribadi = $profile->email_pribadi ?? '';
            $this->job_position_id = $profile->job_position_id;
            $this->pangkat_id = $profile->pangkat_id;
            $this->kelas_id = $profile->kelas_id;
            $this->employee_type_id = $profile->employee_type_id;
            $this->joining_date = $profile->joining_date;

            $this->resolveOrgStructure($profile->org_structure_id);
        }
    }

    /** org_structure_id di profile bisa nunjuk Departemen/Divisi/Sub Divisi (level 1/2/3) -
     * pecah balik ke 3 property terpisah biar dropdown cascading step 3 ke-preselect benar,
     * pola sama dengan afterStateHydrated di Filament\Resources\Employees (JobPositionForm). */
    private function resolveOrgStructure(?string $orgStructureId): void
    {
        if (! $orgStructureId) {
            return;
        }

        $org = OrgStructure::with('parent.parent')->find($orgStructureId);
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

    public function save(): void
    {
        // Bisa disimpan dari step manapun (tidak lagi wizard sekuensial di Edit, lihat
        // HasKaryawanForm::goToStep()) - validasi step 1 & 3 sekaligus, bukan cuma step aktif.
        $this->validate(array_merge($this->rulesForStep(1), $this->rulesForStep(3)));

        $orgStructureId = $this->sub_division_id ?? $this->division_id ?? $this->department_id;

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'nip' => $this->nip ?: null,
            'company_id' => $this->company_id,
            'type_nakes' => $this->type_nakes,
            'is_active' => $this->is_active,
        ];

        // Password cuma diupdate kalau diisi ulang - kosong berarti tetap pakai password lama.
        if (filled($this->password)) {
            $userData['password'] = bcrypt($this->password);
        }

        $profileData = [
            'nickname' => $this->nickname ?: null,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'place_of_birth' => $this->place_of_birth ?: null,
            'date_of_birth' => $this->date_of_birth ?: null,
            'no_nik' => $this->no_nik ?: null,
            'no_kk' => $this->no_kk ?: null,
            'no_bpjs_kes' => $this->no_bpjs_kes ?: null,
            'no_bpjs_ket' => $this->no_bpjs_ket ?: null,
            'npwp' => $this->npwp ?: null,
            'bank_name' => $this->bank_name ?: null,
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
            'email_pribadi' => $this->email_pribadi ?: null,
            'religion_id' => $this->religion_id,
            'org_structure_id' => $orgStructureId,
            'job_position_id' => $this->job_position_id,
            'pangkat_id' => $this->pangkat_id,
            'kelas_id' => $this->kelas_id,
            'employee_type_id' => $this->employee_type_id,
            'joining_date' => $this->joining_date ?: null,
        ];

        DB::transaction(function () use ($userData, $profileData) {
            $this->record->update($userData);
            $this->record->profile()->updateOrCreate(['user_id' => $this->record->id], $profileData);
            $this->record->syncRoles(Role::whereIn('id', $this->role_ids)->get());

            if ($this->job_position_id) {
                $this->record->jobPositions()->where('job_position_id', '!=', $this->job_position_id)->delete();

                UserJobPositions::updateOrCreate(
                    ['user_id' => $this->record->id, 'job_position_id' => $this->job_position_id],
                    [
                        'assigned_by' => Auth::id(),
                        'start_date' => $this->joining_date ?: now(),
                        'status' => 'active',
                    ]
                );
            } else {
                $this->record->jobPositions()->delete();
            }
        });

        Notification::make()
            ->title('Karyawan berhasil diperbarui')
            ->success()
            ->send();

        $this->redirect(route('karyawan.show', ['record' => $this->record, 'from' => 'karyawan']), navigate: true);
    }

    public function render()
    {
        return view('livewire.kepegawaian.create-karyawan');
    }
}
