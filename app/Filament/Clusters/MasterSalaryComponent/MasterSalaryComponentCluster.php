<?php

namespace App\Filament\Clusters\MasterSalaryComponent;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class MasterSalaryComponentCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';
    
    protected static ?string $navigationLabel = 'Master Data Gaji';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_komponen_gaji');
    }
}
