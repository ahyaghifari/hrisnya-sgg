<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Enum\LMS\LmsSettingEnum;
use App\Models\AuditLog;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan LMS" — copy struktur AttendanceSettingsPage/TaskSettingsPage, tapi
 * tanpa selector company karena LMS global (sama seperti Master Honorarium). Isinya
 * LmsSettingEnum::MENIT_1_JP, dipakai LmsMeetings::calculateJp() buat konversi durasi
 * pertemuan ke JP saat disimpan.
 */
#[Layout('components.layout', ['title' => 'Pengaturan LMS'])]
class LmsSettingsPage extends Component
{
    /** @var array<string, string> LmsSettingEnum value => value */
    public array $values = [];

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);

        $this->loadState();
    }

    private function loadState(): void
    {
        $keys = collect(LmsSettingEnum::cases())->map(fn ($e) => $e->value);
        $rows = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(LmsSettingEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => (string) ($rows[$e->value] ?? $e->default())])
            ->all();
    }

    public function openAuditLog(string $key): void
    {
        $this->viewingAuditKey = $key;
    }

    public function closeAuditLog(): void
    {
        $this->viewingAuditKey = null;
    }

    public function getAuditLogsProperty()
    {
        if (! $this->viewingAuditKey) {
            return collect();
        }

        return AuditLog::whereNull('company_id')
            ->where('key', $this->viewingAuditKey)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** @return LmsSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return LmsSettingEnum::cases();
    }

    public function getCanEditSettingsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_LMS->value);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_LMS->value), 403);

        foreach ($this->values as $key => $value) {
            $case = LmsSettingEnum::from($key);

            Setting::updateOrCreate(
                ['company_id' => null, 'key' => $key],
                [
                    'module' => 'lms',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $value,
                ]
            );
        }

        Notification::make()->title('Pengaturan LMS berhasil disimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.lms-settings-page');
    }
}
