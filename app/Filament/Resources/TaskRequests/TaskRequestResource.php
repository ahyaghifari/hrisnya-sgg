<?php

namespace App\Filament\Resources\TaskRequests;

use App\Enum\Task\TaskStatus;
use App\Filament\Resources\TaskRequests\Pages\CreateTaskRequest;
use App\Filament\Resources\TaskRequests\Pages\EditTaskRequest;
use App\Filament\Resources\TaskRequests\Pages\ListTaskRequests;
use App\Filament\Resources\TaskRequests\Pages\ViewTaskRequest;
use App\Filament\Resources\TaskRequests\Schemas\TaskRequestForm;
use App\Filament\Resources\TaskRequests\Schemas\TaskRequestInfolist;
use App\Filament\Resources\TaskRequests\Tables\TaskRequestsTable;
use App\Helpers\PermissionHelper;
use App\Models\Task\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaskRequestResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationLabel = 'Penugasan Individu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return static::$model::where('status', TaskStatus::PENDING->name)->count() ?: 0;
    }

    public static function form(Schema $schema): Schema
    {
        return TaskRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskRequests::route('/'),
            'edit' => EditTaskRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
