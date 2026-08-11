<?php

namespace App\Filament\Pages\PayrollComponents;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PayrollComponentDetail extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.payroll-components.payroll-component-detail';

    protected static ?string $slug = 'komponen-gaji/{company}';

    protected static bool $shouldRegisterNavigation = false;

    public Companies $company;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Key `settings` string yang isinya closed-set pilihan (dipakai match arm di Calculator),
     * bukan freetext — kalau dibiarin TextInput biasa gampang typo dan bikin Calculator diam-diam
     * tidak jalan (value tidak match arm mana pun). Cuma didaftarkan di sini, tidak menyentuh
     * struktur tabel `settings` (tetap generic key/value).
     *
     * @return array<string, array<string, string>>
     */
    private static function settingSelectOptions(): array
    {
        return [
            'insentif_metode' => [
                'master' => 'Master (input manual)',
                'otomatis' => 'Otomatis (dari matriks Tunjangan Jabatan)',
            ],
            'izin_seharian_metode' => [
                'keseluruhan' => 'Keseluruhan (gross salary)',
                'gaji_pokok' => 'Gaji Pokok saja',
            ],
            'bon_metode' => [
                'simple' => 'Simple (1 baris nominal)',
                'detail' => 'Detail (rincian banyak baris)',
            ],
        ];
    }

    public function mount(Companies $company): void
    {
        $this->company = $company;

        $this->form->fill($this->fillComponentForm($company));
    }

    public function getTitle(): string
    {
        return 'Komponen Gaji' . (isset($this->company) ? ' — ' . $this->company->name : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(PayrollComponents::getUrl()),

            Action::make('save')
                ->label('Simpan')
                ->icon('heroicon-o-check')
                ->action(function () {
                    $this->saveComponentForm($this->company, $this->form->getState());
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->componentFormSchema())
            ->statePath('data');
    }

    /** @return array<int, Section> */
    private function componentFormSchema(): array
    {
        $groups = [
            ['type' => PayrollComponentTypeEnum::EARNING, 'label' => 'Pendapatan'],
            ['type' => PayrollComponentTypeEnum::DEDUCTION, 'label' => 'Potongan'],
        ];

        $sections = [];

        foreach ($groups as ['type' => $type, 'label' => $label]) {
            $cases = collect(PayrollComponentEnum::cases())
                ->reject(fn (PayrollComponentEnum $case) => in_array($case, PayrollComponentEnum::alwaysOn(), true))
                ->filter(fn (PayrollComponentEnum $case) => $case->type() === $type);

            $sections[] = Section::make($label)
                ->columnSpanFull()
                ->schema($cases->map(fn (PayrollComponentEnum $case) => $this->componentToggleSchema($case))->all());
        }

        return $sections;
    }

    private function componentToggleSchema(PayrollComponentEnum $case): Section
    {
        $activeKey = "active_{$case->value}";

        $settingFields = collect($case->requiredSettings())
            ->map(fn (string $key) => $this->settingField($key)->visible(fn ($get) => (bool) $get($activeKey)))
            ->all();

        return Section::make($case->label())
            ->columnSpanFull()
            ->compact()
            ->schema([
                Toggle::make($activeKey)
                    ->label('Aktifkan')
                    ->live(),
                ...$settingFields,
            ]);
    }

    private function settingField(string $key): TextInput|Select|Toggle
    {
        $global = Setting::query()->where('key', $key)->whereNull('company_id')->first();

        $label = $global?->label ?? Str::headline($key);
        $help = $global?->description;
        $type = $global?->type ?? 'string';
        $options = self::settingSelectOptions()[$key] ?? null;

        $fieldKey = "setting_{$key}";

        if ($options !== null) {
            return Select::make($fieldKey)->options($options)->label($label)->helperText($help)->required();
        }

        if ($type === 'boolean') {
            return Toggle::make($fieldKey)->label($label)->helperText($help);
        }

        if (in_array($type, ['integer', 'decimal'], true)) {
            return TextInput::make($fieldKey)
                ->numeric()
                ->step($type === 'decimal' ? 0.01 : 1)
                ->label($label)
                ->helperText($help)
                ->required();
        }

        return TextInput::make($fieldKey)->label($label)->helperText($help)->required();
    }

    /** @return array<string, mixed> */
    private function fillComponentForm(Companies $record): array
    {
        $data = [];

        $activeByComponent = CompanyPayrollComponent::query()
            ->where('company_id', $record->id)
            ->pluck('is_active', 'component');

        foreach (PayrollComponentEnum::cases() as $case) {
            if (in_array($case, PayrollComponentEnum::alwaysOn(), true)) {
                continue;
            }

            $data["active_{$case->value}"] = (bool) ($activeByComponent[$case->value] ?? false);

            foreach ($case->requiredSettings() as $key) {
                $data["setting_{$key}"] = Setting::resolve($key, $record->id);
            }
        }

        return $data;
    }

    private function saveComponentForm(Companies $record, array $data): void
    {
        DB::transaction(function () use ($record, $data) {
            foreach (PayrollComponentEnum::cases() as $case) {
                if (in_array($case, PayrollComponentEnum::alwaysOn(), true)) {
                    continue;
                }

                CompanyPayrollComponent::updateOrCreate(
                    ['company_id' => $record->id, 'component' => $case->value],
                    ['is_active' => (bool) ($data["active_{$case->value}"] ?? false)]
                );

                foreach ($case->requiredSettings() as $key) {
                    if (! array_key_exists("setting_{$key}", $data)) {
                        continue;
                    }

                    $global = Setting::query()->where('key', $key)->whereNull('company_id')->first();

                    Setting::updateOrCreate(
                        ['company_id' => $record->id, 'key' => $key],
                        [
                            'module' => $global->module ?? 'payroll',
                            'type' => $global->type ?? 'string',
                            'label' => $global->label ?? $key,
                            'description' => $global->description ?? null,
                            'value' => $data["setting_{$key}"],
                        ]
                    );
                }
            }
        });

        Notification::make()->title('Komponen gaji berhasil disimpan')->success()->send();
    }

    public static function canAccess(): bool
    {
        return Gate::allows('View:PayrollComponents');
    }
}
