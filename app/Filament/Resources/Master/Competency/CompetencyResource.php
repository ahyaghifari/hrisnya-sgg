<?php

namespace App\Filament\Resources\Master\Competency;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Master\Competency\Pages\ManageCompetencies;
use App\Models\Master\Competencies;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CompetencyResource extends BaseResource
{
    protected static ?string $model = Competencies::class;

    protected static ?string $slug = 'master/competencies';

    protected static ?string $navigationLabel = 'Kompetensi';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Kompetensi';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kompetensi')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Kompetensi')->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(50)->wrap()->placeholder('-'),
                TextColumn::make('job_positions_count')
                    ->label('Jumlah Jabatan')
                    ->counts('jobPositions')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('lms_course_competencies_count')
                    ->label('Jumlah Course')
                    ->counts('lmsCourseCompetencies')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Competencies $record) => static::canDelete($record)),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['jobPositions', 'lmsCourseCompetencies']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompetencies::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->jobPositions()->exists() && ! $record->lmsCourseCompetencies()->exists();
    }
}
