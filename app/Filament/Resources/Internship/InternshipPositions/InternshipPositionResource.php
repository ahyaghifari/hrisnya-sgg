<?php

namespace App\Filament\Resources\Internship\InternshipPositions;

use App\Filament\Resources\Internship\InternshipPositions\Pages\ListInternshipPositions;
use App\Filament\Resources\Internship\InternshipPositions\Schemas\InternshipPositionForm;
use App\Filament\Resources\Internship\InternshipPositions\Tables\InternshipPositionsTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class InternshipPositionResource extends Resource
{
    protected static ?string $model = JobPositions::class;

    protected static ?string $navigationLabel = 'Posisi Magang';

    protected static string | UnitEnum | null $navigationGroup = 'Internship';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return InternshipPositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InternshipPositionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('is_internship', true);

        $user = Auth::user();
        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternshipPositions::route('/'),
        ];
    }
}
