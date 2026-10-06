<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Sp\Concerns\HasReasonItems;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Sp\SpReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Buat Berita Acara (alur baru, instruksi user 2026-10-01): HC cuma mengisi dasar
 * klarifikasi & kejadian dulu (wajib). Klarifikasi karyawan, analisis, dst menyusul lewat detail.
 */
#[Layout('components.layout', ['title' => 'Buat Berita Acara'])]
class CreateBeritaAcaraPage extends Component
{
    use HasReasonItems;

    public ?string $companyId = null;
    public ?string $user_id = null;
    public ?string $knows_by = null;
    public ?string $nomor = null;
    public array $dasar_items = [];
    public ?string $kejadian = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('Create:EmployeeSp'), 403);

        $this->companyId = $user->company_id;
        $this->dasar_items = [$this->blankReasonRow()];

        if ($prefill = User::whereCompany($user->company_id)->find(request()->query('user_id'))) {
            $this->user_id = $prefill->id;
        }
    }

    public function getEmployeeOptionsProperty()
    {
        return User::whereCompany($this->companyId)->orderByName()->get()->pluck('name', 'id');
    }

    /** Atasan langsung karyawan terpilih - ditampilkan langsung setelah karyawan dipilih. */
    public function getSupervisorProperty(): ?User
    {
        $employee = User::whereCompany($this->companyId)->find($this->user_id);

        return $employee ? app(\App\Services\SikartapReviewerResolver::class)->directSupervisorOf($employee) : null;
    }

    /** "Mengetahui" = manajer/atasan berjabatan di level Departemen. */
    public function getKnowsByOptionsProperty()
    {
        return User::whereCompany($this->companyId)->atDepartmentLevel()->orderByName()->get()->pluck('name', 'id');
    }

    public function getCompanyNameProperty(): ?string
    {
        return Companies::find($this->companyId)?->name;
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'knows_by' => ['required', 'exists:users,id'],
            'nomor' => ['nullable', 'string', 'max:255'],
            'dasar_items' => ['required', 'array', 'min:1'],
            'dasar_items.*.reason' => ['required', 'exists:sp_reasons,id'],
            'dasar_items.*.note' => ['nullable', 'string'],
            'kejadian' => ['required', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['dasar_items.*.reason' => 'pelanggaran'];
    }

    protected function reasonCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function addDasarItem(): void
    {
        $this->dasar_items = $this->addReasonRow($this->dasar_items);
    }

    public function removeDasarItem(int $index): void
    {
        $this->dasar_items = $this->removeReasonRow($this->dasar_items, $index);
    }

    public function save(): void
    {
        $data = $this->validate();

        // Karyawan & mengetahui harus satu company dengan HC - cegah id company lain lewat wire:model manual.
        abort_unless(
            User::whereCompany($this->companyId)->whereIn('id', [$data['user_id'], $data['knows_by']])->count() === count(array_unique([$data['user_id'], $data['knows_by']])),
            403
        );
        abort_unless(User::whereCompany($this->companyId)->atDepartmentLevel()->whereKey($data['knows_by'])->exists(), 403);

        $data['dasar_items'] = $this->reasonPayload($data['dasar_items']);

        $report = app(SpReportService::class)->create(Auth::user(), $data);

        Notification::make()->title('Berita Acara dibuat')->body('Lanjutkan dengan meminta klarifikasi karyawan.')->success()->send();

        $this->redirect(route('ba.show', $report), navigate: true);
    }

    public function render()
    {
        return view('livewire.sp.create-berita-acara-page');
    }
}
