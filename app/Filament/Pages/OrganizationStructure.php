<?php

namespace App\Filament\Pages;

use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class OrganizationStructure extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.organization-structure';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Struktur Organisasi';

    // protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

    protected static ?string $title = 'Struktur Organisasi';

    public ?array $data = [];

    public function mount(): void
    {
        $defaultCompanyId = Auth::user()?->company_id ?? Companies::query()->first()?->id;

        $this->form->fill([
            'company_id' => $defaultCompanyId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Perusahaan')
                    ->description('Pilih perusahaan untuk melihat struktur organisasinya.')
                    ->columnSpanFull()
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user()))
                    ->schema([
                        Select::make('company_id')
                            ->label('Pilih Perusahaan')
                            ->options(fn () => Companies::pluck('name', 'id'))
                            ->live()
                            ->required()
                            ->placeholder('Pilih perusahaan...'),
                    ]),
            ])
            ->statePath('data');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('View:OrganizationStructure');
    }
}
