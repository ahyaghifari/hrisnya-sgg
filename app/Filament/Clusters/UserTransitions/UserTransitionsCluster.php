<?php

namespace App\Filament\Clusters\UserTransitions;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class UserTransitionsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

    protected static ?string $navigationLabel = 'Perubahan Status Karyawan';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return Gate::allows('View:UserTransitionsCluster');
    }
}
