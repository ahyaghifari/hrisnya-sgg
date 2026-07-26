<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\Master\Companies;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Baseline Pelatihan" — padanan portal dari Filament LmsCourseBaselinesResource
 * (/admin/lms/lms-course-baselines). Dipakai TrainingReportResource (admin) sebagai patokan
 * target jam/jumlah pelatihan minimum per perusahaan per periode. CRUD lewat modal (pola sama
 * seperti LmsCategoriesPage), bukan halaman terpisah — data flat, tidak butuh relasi kompleks.
 *
 * Company scoping: model pakai trait BelongsToCompany (global scope otomatis membatasi query ke
 * company_id user, kecuali role Super Admin) — jadi list TIDAK perlu filter company manual.
 * Untuk form: field Perusahaan cuma muncul kalau user Global Company (PermissionHelper::
 * hasGlobalRole), user company biasa otomatis terkunci ke company_id sendiri (persis pola
 * LmsCourseBaselinesForm admin).
 *
 * "is_active" TIDAK ada di form (persis admin) — cuma toggle cepat dari list. Deskripsi admin
 * bilang "hanya boleh 1 baseline aktif" tapi TIDAK ada penegakan di manapun (TrainingReportPage
 * malah membiarkan admin PILIH baseline aktif mana yang dipakai per company, artinya lebih dari
 * 1 baseline aktif per company memang ditoleransi) — sengaja TIDAK dipaksa single-active di sini
 * supaya tidak mendadak mematahkan behavior Training Report yang sudah ada.
 */
#[Layout('components.layout', ['title' => 'Baseline Pelatihan'])]
class LmsCourseBaselinesPage extends Component
{
    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formCompanyId = null;
    public string $formStartDate = '';
    public string $formEndDate = '';
    public string $formMinCourses = '';
    public string $formMinHours = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
    }

    public function getIsGlobalRoleProperty(): bool
    {
        return PermissionHelper::hasGlobalRole(Auth::user());
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::orderBy('name')->pluck('name', 'id');
    }

    public function getBaselinesProperty()
    {
        return LmsCourseBaselines::with('company')
            ->orderByDesc('start_date')
            ->get();
    }

    public function getCanCreateBaselineProperty(): bool
    {
        return Auth::user()->can('create', LmsCourseBaselines::class);
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', LmsCourseBaselines::class), 403);

        $this->editingId = null;
        $this->formCompanyId = $this->isGlobalRole ? null : Auth::user()->company_id;
        $this->formStartDate = '';
        $this->formEndDate = '';
        $this->formMinCourses = '';
        $this->formMinHours = '';
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $baseline = LmsCourseBaselines::findOrFail($id);

        abort_unless(Auth::user()->can('update', $baseline), 403);

        $this->editingId = $baseline->id;
        $this->formCompanyId = $baseline->company_id;
        $this->formStartDate = (string) $baseline->start_date;
        $this->formEndDate = (string) $baseline->end_date;
        $this->formMinCourses = (string) $baseline->min_courses;
        $this->formMinHours = (string) $baseline->min_hours;
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
        $subject = $this->editingId ? LmsCourseBaselines::findOrFail($this->editingId) : LmsCourseBaselines::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $rules = [
            'formStartDate' => ['required', 'date'],
            'formEndDate' => ['required', 'date', 'after_or_equal:formStartDate'],
            'formMinCourses' => ['required', 'integer', 'min:0'],
            'formMinHours' => ['required', 'integer', 'min:0'],
        ];

        if ($this->isGlobalRole) {
            $rules['formCompanyId'] = ['required', 'exists:m_companies,id'];
        }

        $validated = $this->validate($rules, [], [
            'formCompanyId' => 'perusahaan',
            'formStartDate' => 'tanggal mulai',
            'formEndDate' => 'tanggal selesai',
            'formMinCourses' => 'jumlah kursus minimum',
            'formMinHours' => 'jumlah jam minimum',
        ]);

        $data = [
            'company_id' => $this->isGlobalRole ? $validated['formCompanyId'] : Auth::user()->company_id,
            'start_date' => $validated['formStartDate'],
            'end_date' => $validated['formEndDate'],
            'min_courses' => $validated['formMinCourses'],
            'min_hours' => $validated['formMinHours'],
        ];

        if ($this->editingId) {
            LmsCourseBaselines::whereKey($this->editingId)->update($data);
        } else {
            $data['is_active'] = true;
            $data['created_by'] = Auth::id();
            LmsCourseBaselines::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Baseline berhasil diubah' : 'Baseline baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function toggleActive(string $id): void
    {
        $baseline = LmsCourseBaselines::findOrFail($id);

        abort_unless(Auth::user()->can('update', $baseline), 403);

        $baseline->update(['is_active' => ! $baseline->is_active]);
    }

    public function removeBaseline(string $id): void
    {
        $baseline = LmsCourseBaselines::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $baseline), 403);

        $baseline->delete();

        Notification::make()->title('Baseline berhasil dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.lms-course-baselines-page');
    }
}
