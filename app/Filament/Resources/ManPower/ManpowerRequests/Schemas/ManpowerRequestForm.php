<?php

namespace App\Filament\Resources\ManPower\ManpowerRequests\Schemas;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Models\Master\JobPositions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ManpowerRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        $type = request()->query('type');
        
        return $schema
            ->components([
                Section::make('Form Perubahan ' . ($type === 'quota' ? 'Kuota' : 'Karyawan'))
                    ->description('Form untuk mengajukan perubahan ' . ($type === 'quota' ? 'kuota' : 'karyawan'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('company_id')
                            ->default(fn () => Auth::user()->company_id),

                        Hidden::make('request_type')
                            ->afterStateHydrated(function ($component, $state) {
                                if (request()->query('type') === 'quota') {
                                    $component->state('quota');
                                } else if (request()->query('type') === 'employee_change') {
                                    $component->state('employee_change');
                                }
                            })
                            ->default($type),

                        Hidden::make('status'),

                        Select::make('job_position_id')
                            ->label('Jabatan')
                            ->options(function () {
                                return JobPositions::all()->pluck('name', 'id')->toArray();
                            })
                            ->default(fn () => request()->query('job_position_id'))
                            ->afterStateHydrated(function ($component, $state) {
                                if (! $state && request()->query('job_position_id')) {
                                    $component->state(request()->query('job_position_id'));
                                }
                            })
                            ->preload()
                            ->searchable()
                            ->columnSpan(2)
                            ->live()
                            ->required(),

                        Select::make('type')
                            ->label('Tipe Perubahan Tenaga Kerja')
                            ->options(function (callable $get) {
                                $requestType = $get('request_type') ?? request()->query('type');
                                if ($requestType === 'quota') {
                                    return [
                                        ManpowerRequestType::ADDITIONAL_QUOTA->value => ManpowerRequestType::ADDITIONAL_QUOTA->getLabel(),
                                    ];
                                }
                                return collect(ManpowerRequestType::cases())
                                    ->reject(fn ($case) => $case === ManpowerRequestType::ADDITIONAL_QUOTA)
                                    ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                                    ->toArray();
                            })
                            ->disabled(fn () => request()->query('type') === 'quota')
                            ->default(function () {
                                if (request()->query('type') === 'quota') {
                                    return ManpowerRequestType::ADDITIONAL_QUOTA->value;
                                }
                                return null;
                            })
                            ->afterStateHydrated(function ($component, $state) {
                                if (request()->query('type') === 'quota') {
                                    $component->state(ManpowerRequestType::ADDITIONAL_QUOTA->value);
                                }
                            })
                            ->dehydrated(true),

                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->helperText(function (callable $get) {
                                $jp = JobPositions::find($get('job_position_id'));
                                $quota = $jp?->actual_manpower - $jp?->userAssignments()->count();

                                return 'Kuota tersedia: ' . $quota;
                            })
                            ->maxValue(function (callable $get) {
                                if ($get('request_type') === 'quota') {
                                    return null;
                                }

                                $jp = JobPositions::find($get('job_position_id'));
                                $quota = $jp?->actual_manpower - $jp?->userAssignments()->count();

                                return $quota;
                            })
                            ->minValue(0)
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                if ($get('request_type') !== 'quota') {
                                    $jp = JobPositions::find($get('job_position_id'));
                                    $quota = $jp?->actual_manpower - $jp?->userAssignments()->count();
    
                                    if ($state > $quota) {
                                        $set('quantity', $quota);
                                    }
                                }
                            }),

                        Textarea::make('notes')
                            ->columnSpan(2)
                            ->label('Catatan'),

                        FileUpload::make('file_path')
                            ->label('Lampiran')
                            ->disk('public')
                            ->directory(fn (callable $get) => 'manpower-requests/' . $get('job_position_id') . '/' . $get('type') . '/')
                            ->columnSpan(2)
                            ->openable()
                            ->nullable(),
                    ]),
            ]);
    }
}
