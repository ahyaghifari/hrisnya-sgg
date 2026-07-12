<?php

namespace App\Filament\Resources\SalaryComponentAssignments;

use App\Filament\Resources\SalaryComponentAssignments\Pages\ListSalaryComponentAssignments;
use App\Filament\Resources\SalaryComponentAssignments\Pages\ViewSalaryComponentAssignment;
use App\Filament\Resources\SalaryComponentAssignments\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\SalaryComponentAssignments\Schemas\SalaryComponentAssignmentInfolist;
use App\Filament\Resources\SalaryComponentAssignments\Tables\SalaryComponentAssignmentsTable;
use App\Models\SalaryComponent\SalaryComponents;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Ringkasan SIAPA dapat komponen gaji apa — 1 baris = 1 komponen katalog (SalaryComponents,
 * company_id-nya sudah nempel di komponennya sendiri), dengan 3 kolom jumlah assignment
 * (ke Company/Pangkat/Jabatan). Beda concern dari SalaryComponentResource yang cuma ngurus
 * katalognya. Klik "Lihat" buat buka daftar assignment individual (lewat
 * RelationManagers/AssignmentsRelationManager di halaman View) — edit nilai per assignment
 * juga di situ, bukan di tabel ringkasan ini.
 */
class SalaryComponentAssignmentResource extends Resource
{
    protected static ?string $model = SalaryComponents::class;

    protected static ?string $navigationLabel = "Mapping Komponen Gaji";

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function infolist(Schema $schema): Schema
    {
        return SalaryComponentAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalaryComponentAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalaryComponentAssignments::route('/'),
            'view' => ViewSalaryComponentAssignment::route('/{record}'),
        ];
    }
}
