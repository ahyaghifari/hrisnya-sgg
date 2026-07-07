<?php

namespace App\Filament\Resources\Master\JobPositions;

use App\Filament\Resources\Master\JobPositions\Pages\CreateJobPosition;
use App\Filament\Resources\Master\JobPositions\Pages\EditJobPosition;
use App\Filament\Resources\Master\JobPositions\Pages\ListJobPositions;
use App\Filament\Resources\Master\JobPositions\RelationManagers\UserAssignmentsRelationManager;
use App\Filament\Resources\Master\JobPositions\Schemas\JobPositionForm;
use App\Filament\Resources\Master\JobPositions\Tables\JobPositionsTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class JobPositionResource extends Resource
{
    protected static ?string $model = JobPositions::class;
    protected static ?string $navigationLabel = 'Jabatan';
    protected static string | UnitEnum | null $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 8;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function form(Schema $schema): Schema
    {
        return JobPositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobPositionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UserAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobPositions::route('/'),
            'create' => CreateJobPosition::route('/create'),
            'edit' => EditJobPosition::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_jabatan');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_master_data_jabatan');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_master_data_jabatan');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_master_data_jabatan');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_master_data_jabatan');
    }
}
