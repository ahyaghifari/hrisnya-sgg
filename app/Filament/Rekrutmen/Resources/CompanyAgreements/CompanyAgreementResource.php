<?php

namespace App\Filament\Rekrutmen\Resources\CompanyAgreements;

use App\Filament\Rekrutmen\Resources\CompanyAgreements\Pages\ManageCompanyAgreements;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\CompanyAgreement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
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
            Toggle::make('per_opening')->label('Naskah diisi per lowongan')
                ->helperText('Aktif: naskah dikosongkan di sini, wajib diisi saat membuat lowongan.')
                ->live()->default(false),
            RichEditor::make('content')->label('Naskah')->columnSpanFull()
                ->required(fn (Get $get) => ! $get('per_opening'))
                ->hidden(fn (Get $get) => (bool) $get('per_opening'))
                ->dehydrated()
                ->dehydrateStateUsing(fn ($state, Get $get) => $get('per_opening') ? '' : ($state ?? '')),
            Placeholder::make('per_opening_hint')
                ->label('Naskah')
                ->content('Diisi saat membuat lowongan.')
                ->visible(fn (Get $get) => (bool) $get('per_opening')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('company.name')->label('Perusahaan')->searchable()->sortable(),
                TextColumn::make('title')->label('Judul')->searchable(),
                IconColumn::make('per_opening')->label('Per Lowongan')->boolean(),
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
