<?php

namespace App\Livewire\Task;

use App\Enum\CustomPermissionEnum;
use App\Enum\Task\TaskSettingEnum;
use App\Models\Master\Companies;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan Tugas" — copy struktur AttendanceSettingsPage persis, cuma isinya
 * TaskSettingEnum (boolean) bukan AttendanceSettingEnum (integer).
 */
#[Layout('components.layout', ['title' => 'Pengaturan Tugas'])]
class TaskSettingsPage extends Component
{
    public ?string $companyId = null;

    /** @var array<string, string> TaskSettingEnum value => value (company terpilih) */
    public array $values = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_TUGAS->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');

        $this->loadCompanyState();
    }

    public function selectCompany(string $companyId): void
    {
        $this->companyId = $companyId;
        $this->loadCompanyState();
    }

    private function loadCompanyState(): void
    {
        if (! $this->companyId) {
            $this->values = [];
            return;
        }

        $keys = collect(TaskSettingEnum::cases())->map(fn ($e) => $e->value);

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $keys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(TaskSettingEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => (string) ($companyRows[$e->value] ?? $globalFallback[$e->value] ?? $e->default())])
            ->all();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** @return TaskSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return TaskSettingEnum::cases();
    }

    public function save(): void
    {
        if (! $this->companyId) {
            return;
        }

        foreach ($this->values as $key => $value) {
            $case = TaskSettingEnum::from($key);

            Setting::updateOrCreate(
                ['company_id' => $this->companyId, 'key' => $key],
                [
                    'module' => 'task',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $value,
                ]
            );
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Pengaturan Tugas {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.task.task-settings-page');
    }
}
