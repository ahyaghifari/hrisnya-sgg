<?php

namespace App\Filament\Resources\Master\Companies;

use App\Filament\Resources\Master\Companies\Pages\CreateCompanies;
use App\Filament\Resources\Master\Companies\Pages\EditCompanies;
use App\Filament\Resources\Master\Companies\Pages\ListCompanies;
use App\Filament\Resources\Master\Companies\RelationManagers\JobEvaluationsRelationManager;
use App\Filament\Resources\Master\Companies\Schemas\CompaniesForm;
use App\Filament\Resources\Master\Companies\Tables\CompaniesTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CompaniesResource extends Resource
{
    protected static ?string $model = Companies::class;

    protected static ?string $navigationLabel = "Perusahaan";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'companies';

    public static function form(Schema $schema): Schema
    {
        return CompaniesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            JobEvaluationsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompanies::route('/create'),
            'edit' => EditCompanies::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('id', $user->company_id);
        }

        return $query;
    }
}
