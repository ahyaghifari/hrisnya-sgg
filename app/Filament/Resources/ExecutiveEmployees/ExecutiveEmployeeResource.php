<?php

namespace App\Filament\Resources\ExecutiveEmployees;

use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\ExecutiveEmployees\Pages\EditExecutiveEmployee;
use App\Filament\Resources\ExecutiveEmployees\Pages\ListExecutiveEmployees;
use App\Filament\Resources\ExecutiveEmployees\Tables\ExecutiveEmployeesTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Resource khusus buat 2 akun eksekutif (Presiden Direktur & Sekretaris Direktur,
 * User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS) - dua-duanya sengaja masuk User::NON_KARYAWAN_IDS
 * (dikeluarkan dari EmployeeResource biasa lewat global scope 'non_karyawan', lihat
 * User::boot()), jadi tidak ada cara edit Pangkat/Kelas/Tipe Karyawan/dst mereka dari admin
 * panel sama sekali sebelum resource ini ada (2026-08-12). Form-nya reuse EmployeeForm apa
 * adanya (field & wizard yang sama seperti Karyawan biasa) - cuma query & pages yang beda,
 * dibatasi ketat ke 2 ID itu saja (tidak ada Create/Delete/Import, cuma List+Edit).
 */
class ExecutiveEmployeeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Data Eksekutif';

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExecutiveEmployeesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExecutiveEmployees::route('/'),
            'edit' => EditExecutiveEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('non_karyawan')
            ->whereIn('id', User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS)
            ->with(['profile', 'company', 'roles']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
