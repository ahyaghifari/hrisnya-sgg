<?php

namespace App\Filament\Rekrutmen\Resources\FormSections;

use App\Filament\Rekrutmen\Resources\FormSections\Pages\CreateFormSection;
use App\Filament\Rekrutmen\Resources\FormSections\Pages\EditFormSection;
use App\Filament\Rekrutmen\Resources\FormSections\Pages\ListFormSections;
use App\Filament\Rekrutmen\Resources\FormSections\RelationManagers\FieldsRelationManager;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\FormSection;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * Form Lamaran — port dari FormSectionResource project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment`. Builder bagian + field form pendaftaran kandidat per perusahaan.
 * Field-nya dikelola lewat FieldsRelationManager di halaman edit.
 */
class FormSectionResource extends Resource
{
    protected static ?string $model = FormSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Form Lamaran';

    protected static ?string $modelLabel = 'Bagian Form';

    protected static ?string $pluralModelLabel = 'Form Lamaran';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 2;

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
            TextInput::make('title')->label('Judul Bagian')->required()->maxLength(255),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Perusahaan')->searchable()->sortable(),
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('title')->label('Judul Bagian')->searchable(),
                TextColumn::make('fields_count')->label('Jumlah Field')->counts('fields')->badge(),
            ])
            ->defaultSort('company_id')
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

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSections::route('/'),
            'create' => CreateFormSection::route('/create'),
            'edit' => EditFormSection::route('/{record}/edit'),
        ];
    }
}
