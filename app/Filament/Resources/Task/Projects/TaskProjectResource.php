<?php

namespace App\Filament\Resources\Task\Projects;

use App\Filament\Resources\Task\Projects\Pages\EditTaskProject;
use App\Filament\Resources\Task\Projects\Pages\ListTaskProjects;
use App\Filament\Resources\Task\Projects\RelationManagers\ListsRelationManager;
use App\Filament\Resources\Task\Projects\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Task\Projects\RelationManagers\TasksRelationManager;
use App\Models\Master\Companies;
use App\Models\Task\TaskProject;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin Board tugas: buka Board dulu (edit info Board, List, anggota), lalu tab "Tugas" menampilkan
 * tugas-tugas di dalamnya; klik tugas membuka halaman edit TaskResource. Board dibuat dari portal
 * (TaskService::createProject menyiapkan List bawaan), jadi tidak ada tombol Buat di sini.
 */
class TaskProjectResource extends Resource
{
    protected static ?string $model = TaskProject::class;

    protected static ?string $navigationLabel = 'Board';

    protected static ?string $modelLabel = 'Board';

    protected static ?string $slug = 'board-tugas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama Board')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->label('Deskripsi')->rows(3)->columnSpanFull(),
            Select::make('owner_id')->label('Pemilik')->searchable()
                ->getSearchResultsUsing(fn (string $search) => User::query()->searchNameOrNip($search)->limit(50)->get()->pluck('name', 'id')->all())
                ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
            Select::make('company_id')->label('Perusahaan')->options(fn () => Companies::pluck('name', 'id'))->searchable(),
            DateTimePicker::make('archived_at')->label('Diarsipkan pada')
                ->helperText('Isi untuk mengarsipkan Board (hanya bisa dilihat), kosongkan untuk mengaktifkan lagi.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Board')->searchable()->sortable(),
                TextColumn::make('owner.name')->label('Pemilik')->placeholder('-'),
                TextColumn::make('company.name')->label('Perusahaan')->placeholder('-'),
                TextColumn::make('tasks_count')->label('Tugas')->counts('tasks'),
                TextColumn::make('members_count')->label('Anggota')->counts('members'),
                IconColumn::make('archived_at')->label('Diarsipkan')->boolean()
                    ->getStateUsing(fn (TaskProject $record) => $record->isArchived()),
            ])
            ->filters([
                TernaryFilter::make('archived')->label('Arsip')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('archived_at'),
                        false: fn ($q) => $q->whereNull('archived_at'),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->recordActions([EditAction::make()->label('Buka'), DeleteAction::make()])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
            ListsRelationManager::class,
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskProjects::route('/'),
            'edit' => EditTaskProject::route('/{record}/edit'),
        ];
    }
}
