<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
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
    public ?string $companyId = null;
    public ?string $user_id = null;
    public ?string $knows_by = null;
    public ?string $nomor = null;
    public ?string $dasar_klarifikasi = null;
    public ?string $kejadian = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('Create:EmployeeSp'), 403);

        $this->companyId = $user->company_id;

        if ($prefill = User::where('company_id', $user->company_id)->find(request()->query('user_id'))) {
            $this->user_id = $prefill->id;
        }
    }

    public function getEmployeeOptionsProperty()
    {
        return User::where('company_id', $this->companyId)->orderBy('name')->pluck('name', 'id');
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
            'dasar_klarifikasi' => ['required', 'string'],
            'kejadian' => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        // Karyawan & mengetahui harus satu company dengan HC - cegah id company lain lewat wire:model manual.
        abort_unless(
            User::where('company_id', $this->companyId)->whereIn('id', [$data['user_id'], $data['knows_by']])->count() === count(array_unique([$data['user_id'], $data['knows_by']])),
            403
        );

        $report = app(SpReportService::class)->create(Auth::user(), $data);

        Notification::make()->title('Berita Acara dibuat')->body('Lanjutkan dengan meminta klarifikasi karyawan.')->success()->send();

        $this->redirect(route('ba.show', $report), navigate: true);
    }

    public function render()
    {
        return view('livewire.sp.create-berita-acara-page');
    }
}
