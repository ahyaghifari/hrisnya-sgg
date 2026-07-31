<?php

namespace App\Filament\Pages;

use App\Enum\HideableModuleEnum;
use App\Models\Master\Companies;
use App\Models\Setting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Halaman khusus Super Admin buat nyembunyiin modul/Resource tertentu per company (mis. Extra
 * Shift cuma dipakai RS, belum dipakai Holding) - lihat App\Enum\HideableModuleEnum. Matrix
 * modul x company, disimpan sebagai baris `settings` (module 'system'), dibaca lewat
 * Setting::resolve() di shouldRegisterNavigation()/canAccess() Resource terkait.
 */
class ModuleVisibilitySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEyeSlash;

    protected static ?string $navigationLabel = 'Visibilitas Modul';

    protected static ?string $title = 'Visibilitas Modul';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.module-visibility-settings';

    /** @var array<string, array<string, bool>> module key => [company_id => enabled] */
    public array $enabled = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('Super Admin') ?? false;
    }

    public function mount(): void
    {
        $companyIds = Companies::pluck('id');
        $moduleKeys = collect(HideableModuleEnum::cases())->map(fn ($m) => $m->value);

        $rows = Setting::whereIn('key', $moduleKeys)->whereIn('company_id', $companyIds)->get();

        foreach (HideableModuleEnum::cases() as $module) {
            foreach ($companyIds as $companyId) {
                $row = $rows->first(fn ($r) => $r->key === $module->value && $r->company_id === $companyId);
                $this->enabled[$module->value][$companyId] = $row ? (bool) filter_var($row->value, FILTER_VALIDATE_BOOLEAN) : true;
            }
        }
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** @return HideableModuleEnum[] */
    public function getModulesProperty(): array
    {
        return HideableModuleEnum::cases();
    }

    public function save(): void
    {
        foreach ($this->enabled as $moduleKey => $perCompany) {
            $module = HideableModuleEnum::from($moduleKey);

            foreach ($perCompany as $companyId => $isEnabled) {
                Setting::updateOrCreate(
                    ['company_id' => $companyId, 'key' => $moduleKey],
                    [
                        'module' => 'system',
                        'type' => 'boolean',
                        'label' => $module->label(),
                        'description' => $module->description(),
                        'value' => $isEnabled ? '1' : '0',
                    ]
                );
            }
        }

        Notification::make()
            ->title('Visibilitas modul berhasil disimpan')
            ->success()
            ->send();
    }
}
