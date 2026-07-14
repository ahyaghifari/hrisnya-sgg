<?php

namespace App\Filament\Resources\MasterDataShifts;

use App\Filament\Clusters\ShiftPattern\ShiftPatternCluster;
use App\Filament\Resources\MasterDataShifts\Pages\CreateMasterDataShift;
use App\Filament\Resources\MasterDataShifts\Pages\EditMasterDataShift;
use App\Filament\Resources\MasterDataShifts\Pages\ListMasterDataShifts;
use App\Filament\Resources\MasterDataShifts\Schemas\MasterDataShiftForm;
use App\Filament\Resources\MasterDataShifts\Tables\MasterDataShiftsTable;
use App\Helpers\PermissionHelper;
use App\Models\Shift\MasterShift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MasterDataShiftResource extends Resource
{
    protected static ?string $model = MasterShift::class;

    protected static ?string $cluster = ShiftPatternCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Master Data';

    protected static ?int $navigationSort = 1;


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
}
