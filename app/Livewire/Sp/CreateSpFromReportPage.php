<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Models\Coaching\Coaching;
use App\Models\Sp\SpReport;
use App\Services\Sp\SpReportService;
use App\Services\Sp\SpService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Langkah 2 pembuatan SP (alur baru, instruksi user 2026-10-01/10-02): form SP tertanam di halaman
 * detail Berita Acara (BeritaAcaraDetailPage), bukan halaman terpisah. Hanya untuk BA yang sudah
 * selesai & diputuskan "Lanjut ke SP"; karyawan & company mengikuti BA, level SP dipilih di sini.
 * Tetap disimpan sebagai DRAFT - tanda tangan & penerbitan lewat SpDetailPage.
 */
class CreateSpFromReportPage extends Component
{
    public string $report_id;
    public ?string $nomor = null;
    public ?string $sp_level = null;
    public ?string $knows_by = null;
    public ?string $start_date = null;
    public ?string $coaching_id = null;

    /** @var array<int, string> */
    public array $items = [''];

    public function mount(string $reportId): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('Create:EmployeeSp'), 403);

        $this->report_id = $reportId;
        $this->start_date = today()->toDateString();
        $this->knows_by = $this->report->knows_by;
    }

    public function getReportProperty(): SpReport
    {
        return SpReport::with('user')->findOrFail($this->report_id);
    }

    public function getKnowsByOptionsProperty()
    {
        return \App\Models\User::where('company_id', Auth::user()->company_id)->orderBy('name')->pluck('name', 'id');
    }

    public function getCoachingOptionsProperty()
    {
        return Coaching::where('user_id', $this->report->user_id)->latest()->get()
            ->mapWithKeys(fn (Coaching $c) => [$c->id => "{$c->title} ({$c->created_at->translatedFormat('d M Y')})"]);
    }

    public function getSpLevelOptionsProperty(): array
    {
        return collect(SpLevel::cases())->mapWithKeys(fn ($level) => [$level->value => $level->getLabel()])->all();
    }

    public function getEndDatePreviewProperty(): ?string
    {
        if (! $this->sp_level || ! $this->start_date) {
            return null;
        }

        return app(SpService::class)->resolveEndDate(SpLevel::from($this->sp_level), $this->report->company_id, $this->start_date);
    }

    public function addItem(): void
    {
        $this->items[] = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items) ?: [''];
    }

    protected function rules(): array
    {
        return [
            'nomor' => ['required', 'string', 'max:255'],
            'sp_level' => ['required', 'in:' . implode(',', array_column(SpLevel::cases(), 'value'))],
            'knows_by' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string'],
            'coaching_id' => ['nullable', 'exists:coachings,id'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $report = $this->report;

        abort_unless(\App\Models\User::where('company_id', $report->company_id)->whereKey($data['knows_by'])->exists(), 403);

        if ($data['coaching_id']) {
            abort_unless(Coaching::where('id', $data['coaching_id'])->where('user_id', $report->user_id)->exists(), 403);
        }

        try {
            $sp = app(SpReportService::class)->createSp($report, Auth::user(), $data);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title('SP disimpan sebagai draft')->body('Tanda tangani & terbitkan dari halaman detail.')->success()->send();

        $this->redirect(route('sp.show', $sp), navigate: true);
    }

    public function render()
    {
        return view('livewire.sp.create-sp-from-report-page');
    }
}
