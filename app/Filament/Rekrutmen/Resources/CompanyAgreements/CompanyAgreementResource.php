<?php

namespace App\Filament\Rekrutmen\Resources\CompanyAgreements;

use App\Filament\Rekrutmen\Resources\CompanyAgreements\Pages\ManageCompanyAgreements;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\CompanyAgreement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Persetujuan Onboarding per perusahaan — port dari CompanyAgreementResource project
 * `rekrutmen` (FL3 -> FL4), DB `recruitment`. Kandidat menyetujui ini di tahap
 * TTD & Onboarding (progress dicek di TtdOnboardingPage).
 */
class CompanyAgreementResource extends Resource
{
    protected static ?string $model = CompanyAgreement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Persetujuan Onboarding';

    protected static ?string $modelLabel = 'Persetujuan Onboarding';

    protected static ?string $pluralModelLabel = 'Persetujuan Onboarding';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')->label('Perusahaan')
                ->options(fn () => Company::orderBy('name')->pluck('name', 'id'))
                ->searchable()->required(),
            TextInput::make('title')->label('Judul')->required()->maxLength(255),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            RichEditor::make('content')->label('Naskah')->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Perusahaan')->searchable()->sortable(),
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('company_id')->label('Perusahaan')
                    ->options(fn () => Company::orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanyAgreements::route('/'),
        ];
    }
}
