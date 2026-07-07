<?php

namespace App\Filament\Resources\Payroll\MasterDoctorServices;

use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Filament\Resources\Payroll\MasterDoctorServices\Pages\CreateMasterDoctorService;
use App\Filament\Resources\Payroll\MasterDoctorServices\Pages\EditMasterDoctorService;
use App\Filament\Resources\Payroll\MasterDoctorServices\Pages\ListMasterDoctorServices;
use App\Filament\Resources\Payroll\MasterDoctorServices\Schemas\MasterDoctorServiceForm;
use App\Filament\Resources\Payroll\MasterDoctorServices\Tables\MasterDoctorServicesTable;
use App\Models\DoctorService\MasterDoctorService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MasterDoctorServiceResource extends Resource
{
    protected static ?string $model = MasterDoctorService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    protected static ?string $navigationLabel = "Jasa Dokter";

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MasterDoctorServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterDoctorServicesTable::configure($table);
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
            'index' => ListMasterDoctorServices::route('/'),
            //'create' => CreateMasterDoctorService::route('/create'),
            //'edit' => EditMasterDoctorService::route('/{record}/edit'),
        ];
    }
}
