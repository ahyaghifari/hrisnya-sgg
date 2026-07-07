<?php

namespace App\Filament\Resources\Master\TaskServices;

use App\Filament\Resources\Master\TaskServices\Pages\ListTaskServices;
use App\Filament\Resources\Master\TaskServices\Schemas\TaskServiceForm;
use App\Filament\Resources\Master\TaskServices\Tables\TaskServicesTable;
use App\Models\Master\Task\TaskService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TaskServiceResource extends Resource
{
    protected static ?string $model = TaskService::class;

    protected static ?string $navigationLabel = "Layanan Penugasan";

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TaskServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskServicesTable::configure($table);
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
            'index' => ListTaskServices::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_layanan_penugasan');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_master_data_layanan_penugasan');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_master_data_layanan_penugasan');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_master_data_layanan_penugasan');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_master_data_layanan_penugasan');
    }
}
