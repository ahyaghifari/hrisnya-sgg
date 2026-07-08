<?php

namespace App\Filament\Resources\Payroll\DoctorFeeSubmissions;

use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages\CreateDoctorFeeSubmission;
use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages\EditDoctorFeeSubmission;
use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages\ListDoctorFeeSubmissions;
use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages\ViewDoctorFeeSubmission;
use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Schemas\DoctorFeeSubmissionForm;
use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Schemas\DoctorFeeSubmissionInfolist;
use App\Filament\Resources\Payroll\DoctorFeeSubmissions\Tables\DoctorFeeSubmissionsTable;
use App\Helpers\PermissionHelper;
use App\Models\DoctorService\DoctorFeeSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DoctorFeeSubmissionResource extends Resource
{
    protected static ?string $model = DoctorFeeSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = "Pengajuan Jasa Dokter";

    public static function form(Schema $schema): Schema
    {
        return DoctorFeeSubmissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DoctorFeeSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorFeeSubmissionsTable::configure($table);
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
            'index' => ListDoctorFeeSubmissions::route('/'),
            'create' => CreateDoctorFeeSubmission::route('/create'),
            'view' => ViewDoctorFeeSubmission::route('/{record}'),
            'edit' => EditDoctorFeeSubmission::route('/{record}/edit'),
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
