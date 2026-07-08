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
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaskServiceResource extends Resource
{
    protected static ?string $model = TaskService::class;

    protected static ?string $navigationLabel = "Layanan Penugasan";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

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
}
