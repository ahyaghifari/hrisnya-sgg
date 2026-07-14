<?php

namespace App\Filament\Clusters\ManPowerPlaning;

use App\Enum\JobPositionReqStatus;
use App\Models\Job\ManpowerRequests;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ManPowerPlaningCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

    protected static ?string $navigationLabel = 'Manpower Planning';

    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();
        $jobPositionReq = JobPositions::where('status', JobPositionReqStatus::REQUESTED->value)
            ->where('company_id', $user->company_id)
            ->count();

        $manPowerRequest = ManpowerRequests::where('status', JobPositionReqStatus::REQUESTED->value)
            ->where('company_id', $user->company_id)
            ->count();

        $total = $jobPositionReq + $manPowerRequest;
        return $total > 0 ? (string)$total : '0';
    }

    public static function canAccess(): bool
    {
        return Gate::allows('View:ManPowerPlaningCluster');
    }
}
