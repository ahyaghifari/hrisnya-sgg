<?php

namespace App\Filament\Resources\LMS\TrainingReport;

use App\Filament\Resources\LMS\TrainingReport\Pages\TrainingReportPage;
use App\Models\LMS\LmsCourseBaselines;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TrainingReportResource extends Resource
{
    protected static ?string $model = LmsCourseBaselines::class;

    protected static ?string $navigationLabel = 'Laporan Pelatihan';

    protected static ?string $navigationParentItem = 'Pelatihan';

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => TrainingReportPage::route('/'),
        ];
    }
}
