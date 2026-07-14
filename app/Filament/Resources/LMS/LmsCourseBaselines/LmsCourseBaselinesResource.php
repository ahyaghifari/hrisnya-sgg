<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines;

use App\Filament\Resources\LMS\LmsCourseBaselines\Pages\CreateLmsCourseBaselines;
use App\Filament\Resources\LMS\LmsCourseBaselines\Pages\EditLmsCourseBaselines;
use App\Filament\Resources\LMS\LmsCourseBaselines\Pages\ListLmsCourseBaselines;
use App\Filament\Resources\LMS\LmsCourseBaselines\Schemas\LmsCourseBaselinesForm;
use App\Filament\Resources\LMS\LmsCourseBaselines\Tables\LmsCourseBaselinesTable;
use App\Models\LMS\LmsCourseBaselines;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LmsCourseBaselinesResource extends Resource
{
    protected static ?string $model = LmsCourseBaselines::class;

    protected static ?string $slug = 'lms/lms-course-baselines';

    protected static ?string $navigationLabel = "Baseline Pelatihan";

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 8;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LmsCourseBaselinesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsCourseBaselinesTable::configure($table);
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
            'index' => ListLmsCourseBaselines::route('/'),
            'create' => CreateLmsCourseBaselines::route('/create'),
            'edit' => EditLmsCourseBaselines::route('/{record}/edit'),
        ];
    }
}
