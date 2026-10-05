<?php

namespace App\Filament\Resources\Task\Tasks;

use App\Filament\Resources\Task\Tasks\Pages\EditTask;
use App\Filament\Resources\Task\Tasks\Pages\ListTasks;
use App\Filament\Resources\Task\Tasks\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Task\Tasks\RelationManagers\ChecklistItemsRelationManager;
use App\Filament\Resources\Task\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Task\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Task\Tasks\Tables\TasksTable;
use App\Models\Task\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Admin untuk tugas. Menu "Tugas Individu" = penugasan individu (tanpa Board); tugas milik Board dibuka
 * lewat menu Board (TaskProjectResource) dan memakai halaman edit yang sama. Catatan pribadi "Todo Saya"
 * (self-assigned di luar Board) sengaja tidak pernah tampil di sini.
 */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationLabel = 'Tugas Individu';

    protected static ?string $modelLabel = 'Tugas';

    protected static ?string $slug = 'tugas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'requirement';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(fn (Builder $q) => $q->whereNotNull('project_id')->orWhere('is_self_assigned', false));
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ChecklistItemsRelationManager::class,
            CommentsRelationManager::class,
            AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
