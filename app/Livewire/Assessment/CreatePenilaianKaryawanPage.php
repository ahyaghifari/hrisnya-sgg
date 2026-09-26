<?php

namespace App\Livewire\Assessment;

use App\Models\Assessment\Assessments;
use App\Models\Assessment\AssessmentUsers;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Tambah Penilaian" — HC menunjuk karyawan yang harus dinilai di 1 jadwal (Assessments) dari
 * halaman terpusat, TIDAK lagi cuma dari tombol di tab Penilaian karyawan (lihat
 * EmployeeStatistics — sekarang cuma histori read-only). Pengisian nilainya sendiri
 * tetap eksklusif atasan langsung (lihat AssessmentUserForm::$canEdit) — halaman ini cuma
 * bikin barisnya, bukan isi nilai.
 */
#[Layout('components.layout', ['title' => 'Tambah Penilaian'])]
class CreatePenilaianKaryawanPage extends Component
{
    public ?string $assessment_id = null;
    public ?string $target_id = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('create', AssessmentUsers::class), 403);
    }

    public function getJadwalOptionsProperty()
    {
        return Assessments::where('status', 'active')->orderBy('name')->pluck('name', 'id');
    }

    /** Kalau jadwal terpilih dikhususkan ke 1 jabatan, batasi kandidat ke jabatan itu saja. */
    public function getKaryawanOptionsProperty()
    {
        $assessment = $this->assessment_id ? Assessments::find($this->assessment_id) : null;

        return User::query()
            ->where('company_id', Auth::user()->company_id)
            ->when($assessment?->job_position_id, function ($q) use ($assessment) {
                $q->whereHas('activeJobPosition', fn ($jp) => $jp->where('job_position_id', $assessment->job_position_id));
            })
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected function rules(): array
    {
        return [
            'assessment_id' => ['required', 'exists:assessments,id'],
            'target_id' => ['required', 'exists:users,id'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        AssessmentUsers::firstOrCreate(
            ['assessment_id' => $data['assessment_id'], 'target_id' => $data['target_id']],
            ['created_by' => Auth::id()]
        );

        Notification::make()->title('Karyawan berhasil ditambahkan ke jadwal penilaian')->success()->send();

        $this->redirect(route('penilaian.daftar', ['jadwalId' => $data['assessment_id']]), navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.create-penilaian-karyawan-page');
    }
}
