<?php

namespace App\Filament\Clusters\EmployeeDocument;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class EmployeeDocumentCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';
    
    protected static ?string $navigationLabel = 'Dokumen Kepegawaian';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Gate::allows('view_dokumen_karyawan');
    }
}
