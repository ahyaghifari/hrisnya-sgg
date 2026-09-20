<?php

namespace App\Filament\Resources\ManPower\JobPositions;

use App\Enum\JobPositionReqStatus;
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
use UnitEnum;

class JobPositionResource extends Resource
{
    protected static ?string $model = JobPositions::class;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static ?string $navigationLabel = 'Jabatan';

    // Menu Jabatan sudah ada di grup Master Perusahaan (Master\JobPositions\JobPositionResource), jadi
    // entri di grup Kepegawaian dihapus dari navigasi (2026-09-20, instruksi user). Class ini TETAP ada:
    // halaman Edit-nya memuat persetujuan kuota jabatan & ManpowerRequests memakai canCreate()/canEdit()-nya.
    protected static bool $shouldRegisterNavigation = false;

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
        $query = parent::getEloquentQuery()->with('pendingQuotaRequest');

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
