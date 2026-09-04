<?php

namespace App\Filament\Rekrutmen\Resources\Quizzes;

use App\Filament\Rekrutmen\Resources\Quizzes\Pages\ManageQuizzes;
use App\Models\Rekrutmen\Quiz;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Kuis — port dari QuizResource project `rekrutmen` (FL3 -> FL4), DB `recruitment`. CRUD
 * metadata kuis (nama, durasi, instruksi). Soal-nya dikelola di halaman "Soal Kuis"
 * (QuizQuestionsPage) — tombol "Kelola Soal" di tabel.
 */
class QuizResource extends Resource
{
    protected static ?string $model = Quiz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $navigationLabel = 'Kuis';

    protected static ?string $modelLabel = 'Kuis';

    protected static ?string $pluralModelLabel = 'Kuis';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kuis')->schema([
                TextInput::make('name')
                    ->label('Nama Kuis')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Satu kuis bisa dipakai di banyak lowongan.'),

                TextInput::make('duration_minutes')
                    ->label('Batas Waktu (menit)')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Sejak kandidat menekan "Mulai Tes".'),

                Textarea::make('instructions')
                    ->label('Instruksi Tes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Kuis')->searchable()->sortable(),
                TextColumn::make('questions_count')->label('Jumlah Soal')->counts('questions')->alignCenter(),
                TextColumn::make('job_openings_count')->label('Dipakai di Lowongan')->counts('jobOpenings')->alignCenter(),
                TextColumn::make('duration_minutes')->label('Durasi')->suffix(' menit')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('kelolaSoal')
                    ->label('Kelola Soal')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (Quiz $record) => \App\Filament\Rekrutmen\Pages\QuizQuestionsPage::getUrl() . '?quiz=' . $record->id),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuizzes::route('/'),
        ];
    }
}
