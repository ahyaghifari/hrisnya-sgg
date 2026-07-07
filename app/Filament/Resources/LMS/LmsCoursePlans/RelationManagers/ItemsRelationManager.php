<?php

namespace App\Filament\Resources\LMS\LmsCoursePlans\RelationManagers;

use App\Enum\OrgStructurLevel;
use App\Models\Master\OrgStructure;
use Carbon\Carbon;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Daftar Pelatihan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Rencana Pelatihan')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Detail Pelatihan')
                            ->schema([
                                Checkbox::make('use_existing_courses')
                                    ->label('Gunakan Kursus yang Ada')
                                    ->helperText('Centang jika Anda ingin menambahkan kursus yang sudah ada ke dalam rencana pelatihan ini.')
                                    ->reactive(),

                                Select::make('existing_courses')
                                    ->label('Kursus yang Ada')
                                    ->multiple()
                                    ->options(function () {
                                        return \App\Models\LMS\LmsCourses::pluck('title', 'id')->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (callable $get): bool => (bool) $get('use_existing_courses'))
                                    ->columnSpanFull(),

                                TextInput::make('proposed_title')
                                    ->label('Judul Kursus yang Diusulkan')
                                    ->maxLength(255)
                                    ->required(fn (callable $get): bool => ! (bool) $get('use_existing_courses'))
                                    ->visible(fn (callable $get): bool => ! (bool) $get('use_existing_courses'))
                                    ->default(fn (callable $get) => (bool) $get('use_existing_courses') ? null : ''),

                                Select::make('competency_id')
                                    ->label('Kompetensi')
                                    ->options(function () {
                                        return \App\Models\Master\Competencies::pluck('name', 'id')->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),

                                Select::make('target_org_structure_id')
                                    ->label('Departemen Sasaran')
                                    ->options(function () {
                                        return OrgStructure::where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),

                                Select::make('target_job_position_id')
                                    ->label('Jabatan Sasaran')
                                    ->options(function () {
                                        return \App\Models\Master\JobPositions::pluck('name', 'id')->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),

                                TextInput::make('estimated_participants')
                                    ->label('Perkiraan Peserta')
                                    ->numeric()
                                    ->minValue(1)
                                    ->columnSpanFull(),

                                TextInput::make('estimated_cost')
                                    ->label('Perkiraan Biaya')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Jadwal Pelatihan')
                            ->schema([
                                Repeater::make('schedules')
                                    ->relationship('schedules')
                                    ->label('Jadwal Pelatihan')
                                    ->minItems(1)
                                    ->itemLabel('Jadwal Pelatihan')
                                    ->addActionLabel('Tambah Jadwal Pelatihan')
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('planned_month')
                                            ->label('Bulan')
                                            ->options([
                                                'jan' => 'Januari',
                                                'feb' => 'Februari',
                                                'mar' => 'Maret',
                                                'apr' => 'April',
                                                'may' => 'Mei',
                                                'jun' => 'Juni',
                                                'jul' => 'Juli',
                                                'aug' => 'Agustus',
                                                'sep' => 'September',
                                                'oct' => 'Oktober',
                                                'nov' => 'November',
                                                'dec' => 'Desember',
                                            ])
                                            ->nullable()
                                            ->columnSpanFull(),

                                        TextInput::make('planned_week')
                                            ->label('Minggu ke-')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(5)
                                            ->nullable()
                                            ->columnSpanFull(),

                                        DatePicker::make('planned_date')
                                            ->label('Tanggal')
                                            ->nullable()
                                            ->columnSpanFull(),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'planned' => 'Direncanakan',
                                                'rescheduled' => 'Dijadwalkan Ulang',
                                                'completed' => 'Selesai',
                                                'cancelled' => 'Dibatalkan',
                                            ])
                                            ->default('planned')
                                            ->columnSpanFull(),

                                        Textarea::make('notes')
                                            ->label('Catatan')
                                            ->rows(3)
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ])
                            ])
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Daftar Pelatihan')
            ->columns([
                TextColumn::make('proposed_title')
                    ->label('Nama Pelatihan'),

                TextColumn::make('targetDepartment.name')
                    ->label('Departemen Sasaran'),

                TextColumn::make('schedules')
                    ->label('Jadwal Pelatihan')
                    ->wrap()
                    ->getStateUsing(function ($record) {
                        $schedules = $record->schedules()->orderBy('planned_date')->get();
                        if ($schedules->isEmpty()) {
                            return '-';
                        }

                        $scheduleStrings = $schedules->map(function ($schedule) {
                            $date = $schedule->planned_date ? Carbon::parse($schedule->planned_date)->format('d M Y') : 'Tanggal belum ditentukan';
                            return "{$date} ({$schedule->status})";
                        });

                        return $scheduleStrings->implode(', ');
                    }),

                TextColumn::make('status')
                    ->badge(fn ($record) => match ($record->status) {
                        'draft' => 'default',
                        'submitted' => 'primary',
                        'approved' => 'success',
                        'scheduled' => 'warning',
                        'completed' => 'primary',
                        'cancelled' => 'red',
                        default => 'default',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->label('Status'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->slideOver()->label('Tambah Rencana Pelatihan'),
            ])
            ->recordActions([
                EditAction::make()->slideOver()->hiddenLabel(),
                DeleteAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
