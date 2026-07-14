<?php

namespace App\Filament\Clusters\ShiftPattern;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ShiftPatternCluster extends Cluster
{
    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static ?string $navigationLabel = 'Pola Shift';

    protected static ?int $navigationSort = 8;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function canAccess(): bool
    {
        return Gate::allows('View:ShiftPatternCluster');
    }
}
