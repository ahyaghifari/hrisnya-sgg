<?php

namespace App\Filament\Resources\Internship\InternEmployees;

use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Filament\Resources\Internship\InternEmployees\Pages\CreateInternEmployee;
use App\Filament\Resources\Internship\InternEmployees\Pages\EditInternEmployee;
use App\Filament\Resources\Internship\InternEmployees\Pages\ListInternEmployees;
use App\Helpers\PermissionHelper;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Daftar karyawan yang jabatannya ditandai "Posisi Magang" (InternshipPositionResource). Form &
 * table di-reuse LANGSUNG dari EmployeeResource (App\Filament\Resources\Employees) - satu-satunya
 * beda cuma scope query. Tidak ada kolom/tabel/model baru buat "karyawan magang" itu sendiri,
 * intern TETAP baris users biasa - yang membedakan cuma jabatannya.
 */
class InternEmployeeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Karyawan Magang';

    protected static string | UnitEnum | null $navigationGroup = 'Internship';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['profile', 'company', 'roles'])
            ->whereHas('profile.jobPosition', fn (Builder $q) => $q->where('is_internship', true));

        $user = Auth::user();
        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternEmployees::route('/'),
            'create' => CreateInternEmployee::route('/create'),
            'edit' => EditInternEmployee::route('/{record}/edit'),
        ];
    }
}
