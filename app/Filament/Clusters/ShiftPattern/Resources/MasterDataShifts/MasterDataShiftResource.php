<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts;

use App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\Pages\CreateMasterDataShift;
use App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\Pages\EditMasterDataShift;
use App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\Pages\ListMasterDataShifts;
use App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\Schemas\MasterDataShiftForm;
use App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\Tables\MasterDataShiftsTable;
use App\Filament\Clusters\ShiftPattern\ShiftPatternCluster;
use App\Helpers\PermissionHelper;
use App\Models\Shift\MasterShift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MasterDataShiftResource extends Resource
{
    protected static ?string $model = MasterShift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = ShiftPatternCluster::class;

    protected static ?string $recordTitleAttribute = 'MasterShift';

    public static function form(Schema $schema): Schema
    {
        return MasterDataShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterDataShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
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

    public static function getPages(): array
    {
        return [
            'index' => ListMasterDataShifts::route('/'),
            // 'create' => CreateMasterDataShift::route('/create'),
            // 'edit' => EditMasterDataShift::route('/{record}/edit'),
        ];
    }
    
    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_pola_shift');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_master_data_pola_shift');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_master_data_pola_shift');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('edit_master_data_pola_shift');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('delete_master_data_pola_shift');
    }
}
