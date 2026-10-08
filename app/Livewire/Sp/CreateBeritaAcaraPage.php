<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Sp\Concerns\HasReasonItems;
use App\Models\Master\Companies;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Sp\SpReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Buat Berita Acara (alur baru, instruksi user 2026-10-01): semua isian HC bisa diisi langsung
 * (2026-10-07). Dasar & kejadian wajib untuk simpan draft; sisanya boleh menyusul lewat detail
 * tapi wajib lengkap saat FIX.
 */
#[Layout('components.layout', ['title' => 'Buat Berita Acara'])]
class CreateBeritaAcaraPage extends Component
{
    use HasReasonItems;

    public ?string $companyId = null;
    public ?string $invitation_id = null;
    public ?string $user_id = null;
    public ?string $knows_by = null;
    public ?string $coaching_id = null;
    public ?string $nomor = null;
    public array $dasar_items = [];
    public ?string $kejadian = null;
    public ?string $subject_clarification = null;
    public ?string $analysis = null;
    public ?string $kesimpulan = null;
    public ?string $followup_plan = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('Create:EmployeeSp'), 403);

        $this->companyId = $user->company_id;
        $this->dasar_items = [$this->blankReasonRow()];

        // BA hanya dibuat dari undangan klarifikasi yang masih terbuka; karyawan mengikuti undangan.
        $invitation = Invitation::ofType(\App\Enum\InvitationType::SP_KLARIFIKASI)->where('company_id', $user->company_id)->findOrFail(request()->query('invitation_id'));
        abort_unless($invitation->isOpen(), 404);

        $this->invitation_id = $invitation->id;
        $this->user_id = $invitation->user_id;
    }

    /** Coaching karyawan ini yang bisa dicatat sebagai sumber (opsional). */
    public function getCoachingOptionsProperty()
    {
        return \App\Models\Coaching\Coaching::where('user_id', $this->user_id)->latest()->get()
            ->mapWithKeys(fn ($c) => [$c->id => "{$c->title} ({$c->created_at->translatedFormat('d M Y')})"]);
    }

    public function getInvitationProperty(): Invitation
    {
        return Invitation::with(['user', 'supervisor.activeJobPosition.jobPosition'])->findOrFail($this->invitation_id);
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
            'knows_by' => ['required', 'exists:users,id'],
            'coaching_id' => ['nullable', 'exists:coachings,id'],
            'nomor' => ['nullable', 'string', 'max:255'],
            'dasar_items' => ['required', 'array', 'min:1'],
            'dasar_items.*.reason' => ['required', \Illuminate\Validation\Rule::exists('sp_reasons', 'id')->where('kind', \App\Models\Sp\SpReason::KIND_DASAR)],
            'dasar_items.*.note' => ['nullable', 'string'],
            'kejadian' => ['required', 'string'],
            'subject_clarification' => ['nullable', 'string'],
            'analysis' => ['nullable', 'string'],
            'kesimpulan' => ['nullable', 'string'],
            'followup_plan' => ['nullable', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['dasar_items.*.reason' => 'dasar klarifikasi'];
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

        abort_unless(User::whereCompany($this->companyId)->atDepartmentLevel()->whereKey($data['knows_by'])->exists(), 403);

        $data['dasar_items'] = $this->reasonPayload($data['dasar_items']);

        $report = app(SpReportService::class)->create(Auth::user(), $data + ['invitation_id' => $this->invitation_id]);

        Notification::make()->title('Berita Acara dibuat')->body('Tersimpan sebagai draft. Lengkapi isinya lalu FIX.')->success()->send();

        $this->redirect(route('ba.show', $report), navigate: true);
    }

    public function render()
    {
        return view('livewire.sp.create-berita-acara-page');
    }
}
