<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Pemateri" — padanan portal dari App\Filament\Resources\LMS\Trainers\TrainersResource, yang
 * sebelumnya tidak ada UI create/edit di mana pun (Trainers cuma bisa di-listing, bukan dibuat).
 * Otorisasi CRUD lewat Policy (Auth::user()->can('create'/'update'/'delete', ...)) yang
 * di-generate Shield dari Resource itu — BUKAN gerbang manual seperti Honorarium (yang memang
 * modelnya tidak punya Resource/Policy sama sekali).
 *
 * Pemateri yang sudah pernah mengisi pelatihan (trainings_count > 0, dihitung dari jumlah
 * COURSE DISTINCT lewat relasi meetings — bukan jumlah baris pertemuan) tidak boleh dihapus,
 * cuma bisa diaktifkan/dinonaktifkan (instruksi user 2026-07-26).
 */
#[Layout('components.layout', ['title' => 'Pemateri'])]
class TrainersPage extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'aktif';

    public bool $showFormModal = false;
    public ?string $editingId = null;
    public string $formName = '';
    public string $formType = 'external';
    public ?string $formUserId = null;
    public string $formExpertise = '';
    public string $formEmail = '';
    public string $formPhone = '';
    public string $formCompany = '';
    public string $formAddress = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
    }

    public function getTrainersProperty()
    {
        $search = trim($this->search);

        return Trainers::query()
            ->withCount([
                'meetings as trainings_count' => fn ($q) => $q->select(DB::raw('count(distinct course_id)')),
            ])
            ->where('is_active', $this->status === 'aktif')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->appends([
                'search' => $this->search,
                'status' => $this->status,
            ]);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getEmployeeOptionsProperty()
    {
        return User::where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function getCanCreateTrainerProperty(): bool
    {
        return Auth::user()->can('create', Trainers::class);
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', Trainers::class), 403);

        $this->editingId = null;
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $trainer = Trainers::findOrFail($id);

        abort_unless(Auth::user()->can('update', $trainer), 403);

        $this->editingId = $trainer->id;
        $this->formName = $trainer->name;
        $this->formType = $trainer->type;
        $this->formUserId = $trainer->user_id;
        $this->formExpertise = (string) $trainer->expertise;
        $this->formEmail = (string) $trainer->email;
        $this->formPhone = (string) $trainer->phone;
        $this->formCompany = (string) $trainer->company;
        $this->formAddress = (string) $trainer->address;
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
        $subject = $this->editingId ? Trainers::findOrFail($this->editingId) : Trainers::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $validated = $this->validate([
            'formName' => 'required|string|max:255',
            'formType' => 'required|in:internal,external',
            'formUserId' => 'nullable|exists:users,id',
            'formExpertise' => 'nullable|string|max:255',
            'formEmail' => 'nullable|email|max:255',
            'formPhone' => 'nullable|string|max:255',
            'formCompany' => 'nullable|string|max:255',
            'formAddress' => 'nullable|string',
        ], [], [
            'formName' => 'nama',
            'formType' => 'tipe',
            'formUserId' => 'karyawan',
            'formExpertise' => 'keahlian',
            'formEmail' => 'email',
            'formPhone' => 'telepon',
            'formCompany' => 'perusahaan asal',
            'formAddress' => 'alamat',
        ]);

        $data = [
            'name' => $validated['formName'],
            'type' => $validated['formType'],
            'user_id' => $validated['formUserId'] ?: null,
            'expertise' => $validated['formExpertise'] ?: null,
            'email' => $validated['formEmail'] ?: null,
            'phone' => $validated['formPhone'] ?: null,
            'company' => $validated['formCompany'] ?: null,
            'address' => $validated['formAddress'] ?: null,
        ];

        if ($this->editingId) {
            Trainers::whereKey($this->editingId)->update($data);
        } else {
            Trainers::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Pemateri berhasil diubah' : 'Pemateri baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    private function trainingsCount(Trainers $trainer): int
    {
        return (int) $trainer->meetings()->distinct('course_id')->count('course_id');
    }

    public function removeTrainer(string $id): void
    {
        $trainer = Trainers::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $trainer), 403);

        if ($this->trainingsCount($trainer) > 0) {
            Notification::make()
                ->title('Pemateri ini sudah pernah mengisi pelatihan, tidak bisa dihapus — nonaktifkan saja')
                ->danger()
                ->send();

            return;
        }

        $trainer->delete();

        Notification::make()->title('Pemateri berhasil dihapus')->success()->send();
    }

    public function toggleActive(string $id): void
    {
        $trainer = Trainers::findOrFail($id);

        abort_unless(Auth::user()->can('update', $trainer), 403);

        $trainer->update(['is_active' => ! $trainer->is_active]);

        Notification::make()
            ->title($trainer->is_active ? 'Pemateri diaktifkan' : 'Pemateri dinonaktifkan')
            ->success()
            ->send();
    }

    private function resetForm(): void
    {
        $this->formName = '';
        $this->formType = 'external';
        $this->formUserId = null;
        $this->formExpertise = '';
        $this->formEmail = '';
        $this->formPhone = '';
        $this->formCompany = '';
        $this->formAddress = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.lms.trainers-page');
    }
}
