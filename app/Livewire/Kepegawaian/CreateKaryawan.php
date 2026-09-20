<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Livewire\Kepegawaian\Concerns\HasKaryawanForm;
use App\Models\Role;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Tambah Karyawan versi portal — didesain ulang (bukan port langsung Filament schema
 * admin, lihat planning/2026-07-20-portal-modul-kepegawaian-karyawan-dan-navigasi-dashboard.md
 * §5.4), tapi logic penyimpanan (merge soft-deleted, syncRoles, UserJobPositions) diambil
 * persis dari App\Filament\Resources\Employees\Pages\CreateEmployee supaya perilakunya tidak
 * menyimpang dari sisi admin.
 *
 * Field/opsi/step di HasKaryawanForm, dipakai bareng EditKaryawan (view blade juga sama persis,
 * lihat create-karyawan.blade.php) - beda cuma di mount() (kosong di sini) dan save() (insert).
 */
#[Layout('components.layout', ['title' => 'Tambah Karyawan'])]
class CreateKaryawan extends Component
{
    use HasKaryawanForm, GatesCompanyFilterBySystem;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('create', User::class), 403);

        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEPEGAWAIAN, 'company_id');
    }

    public function save(): void
    {
        $this->validate($this->rulesForStep(1));

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
            'job_position_id' => $this->job_position_id,
            'pangkat_id' => $this->pangkat_id,
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
