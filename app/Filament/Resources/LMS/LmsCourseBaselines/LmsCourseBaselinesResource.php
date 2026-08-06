<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines;

use App\Filament\Resources\LMS\LmsCourseBaselines\Pages\ManageLmsCourseBaselines;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\Master\Companies;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LmsCourseBaselinesResource extends Resource
{
    protected static ?string $model = LmsCourseBaselines::class;

    protected static ?string $slug = 'lms/lms-course-baselines';

    protected static ?string $navigationLabel = 'Baseline Pelatihan';

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 8;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Baseline Pelatihan')
                    ->description('Kelola baseline pelatihan, hanya dapat memiliki satu baseline pelatihan yang aktif')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Pilih Perusahaan')
                            ->columnSpanFull()
                            ->options(function () {
                                $user = Auth::user();
                                if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
                                    return Companies::where('id', $user->company_id)
                                        ->pluck('name', 'id');
                                }

                                return Companies::pluck('name', 'id');
                            })
                            ->default(function () {
                                $user = Auth::user();

                                return $user ? $user->company_id : null;
                            }),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->placeholder('Pilih Tanggal Mulai'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->placeholder('Pilih Tanggal Selesai'),

                        TextInput::make('min_courses')
                            ->label('Jumlah Kursus Minimum')
                            ->numeric()
                            ->required()
                            ->placeholder('Masukkan Jumlah Kursus Minimum'),

                        TextInput::make('min_hours')
                            ->label('Jumlah Jam Minimum')
                            ->numeric()
                            ->required()
                            ->placeholder('Masukkan Jumlah Jam Minimum'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')->label('Tanggal Mulai')->date('j F Y'),
                TextColumn::make('end_date')->label('Tanggal Selesai')->date('j F Y'),
                TextColumn::make('min_courses')->label('Jumlah Kursus Minimum')->alignCenter(),
                TextColumn::make('min_hours')->label('Jumlah Jam Minimum')->alignCenter(),
                TextColumn::make('is_active')->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Tidak Aktif')
                    ->badge()
                    ->colors([
                        0 => 'danger',
                        1 => 'success',
                    ])->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel(),

                DeleteAction::make()
                    ->hiddenLabel(),
            ]);
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
            'index' => ManageLmsCourseBaselines::route('/'),
        ];
    }
}
