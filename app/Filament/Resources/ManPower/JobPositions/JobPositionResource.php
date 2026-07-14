<?php

namespace App\Filament\Resources\ManPower\JobPositions;

use App\Enum\JobPositionReqStatus;
use App\Filament\Clusters\ManPowerPlaning\ManPowerPlaningCluster;
use App\Filament\Resources\ManPower\JobPositions\Pages\CreateJobPosition;
use App\Filament\Resources\ManPower\JobPositions\Pages\EditJobPosition;
use App\Filament\Resources\ManPower\JobPositions\Pages\ListJobPositions;
use App\Filament\Resources\ManPower\JobPositions\Schemas\JobPositionForm;
use App\Filament\Resources\ManPower\JobPositions\Tables\JobPositionsTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class JobPositionResource extends Resource
{
    protected static ?string $model = JobPositions::class;

    protected static ?string $cluster = ManPowerPlaningCluster::class;

    protected static ?string $navigationLabel = 'Jabatan';


    protected static ?int $navigationSort = 1;

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
            //
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

    public static function getNavigationBadge(): ?string
    {
        $jobPositionReq = JobPositions::where('status', JobPositionReqStatus::REQUESTED->value)->count();
        return $jobPositionReq > 0 ? (string)$jobPositionReq : '0';
    }
}
