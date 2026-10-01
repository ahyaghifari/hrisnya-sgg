<?php

namespace App\Filament\Resources\EmployeeSps;

use App\Filament\Resources\EmployeeSps\Pages\CreateEmployeeSp;
use App\Filament\Resources\EmployeeSps\Pages\EditEmployeeSp;
use App\Filament\Resources\EmployeeSps\Pages\ListEmployeeSps;
use App\Filament\Resources\EmployeeSps\Schemas\EmployeeSpForm;
use App\Filament\Resources\EmployeeSps\Tables\EmployeeSpsTable;
use App\Helpers\PermissionHelper;
use App\Models\Sp\EmployeeSp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EmployeeSpResource extends Resource
{
    protected static ?string $model = EmployeeSp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string | UnitEnum | null $navigationGroup = 'Pembinaan';

    protected static ?string $navigationLabel = 'Surat Peringatan';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return EmployeeSpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeSpsTable::configure($table);
    }

    /** SP baru hanya dibuat dari Berita Acara yang selesai & diputuskan lanjut SP (portal HC,
     * instruksi user 2026-10-01) - panel admin tetap untuk melihat/mengedit SP yang sudah ada. */
    public static function canCreate(): bool
    {
        return false;
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
            'index' => ListEmployeeSps::route('/'),
            'create' => CreateEmployeeSp::route('/create'),
            'edit' => EditEmployeeSp::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest('start_date');

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
