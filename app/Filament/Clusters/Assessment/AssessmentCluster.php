<?php

namespace App\Filament\Clusters\Assessment;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AssessmentCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';
    
    protected static ?string $navigationLabel = 'Penilaian Karyawan';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return Gate::allows('view_penilaian_karyawan');
    }
}
