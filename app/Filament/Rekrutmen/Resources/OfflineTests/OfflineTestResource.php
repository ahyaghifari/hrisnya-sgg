<?php

namespace App\Filament\Rekrutmen\Resources\OfflineTests;

use App\Filament\Rekrutmen\Resources\OfflineTests\Pages\ManageOfflineTests;
use App\Models\Rekrutmen\OfflineTest;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** CRUD master Tes Offline (nama/konteks tes) — assign ke lowongan dilakukan di tab "Tes". */
class OfflineTestResource extends Resource
{
    protected static ?string $model = OfflineTest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Tes Offline';

    protected static ?string $modelLabel = 'Tes Offline';

    protected static ?string $pluralModelLabel = 'Tes Offline';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tes Offline')->schema([
                TextInput::make('name')
                    ->label('Nama / Konteks Tes')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Mis. "Tes Tulis Matematika" — satu tes bisa dipakai di banyak lowongan.'),

                Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama / Konteks Tes')->searchable()->sortable(),
                TextColumn::make('description')->label('Keterangan')->limit(60)->placeholder('—'),
                TextColumn::make('job_openings_count')->label('Dipakai di Lowongan')->counts('jobOpenings')->alignCenter(),
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
            'index' => ManageOfflineTests::route('/'),
        ];
    }
}
