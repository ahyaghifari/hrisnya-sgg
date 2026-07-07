<?php

namespace App\Filament\Resources\Payroll\DoctorFeeSubmissions\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\DoctorService\MasterDoctorService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DoctorFeeSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengajuan')
                    ->schema([
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->required()
                            ->default(fn () => Auth::user()?->company_id)
                            ->disabled(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()))
                            ->dehydrated(),

                        TextInput::make('sep_number')
                            ->label('No SIP'),

                        TextInput::make('patient_name')
                            ->label('Nama Pasien'),

                        TextInput::make('total_claim')
                            ->numeric()
                            ->prefix('Rp'),

                        TextInput::make('service_type')
                            ->label('Nama Service'),

                        //Select::make('service_type')
                        //    ->options([
                        //        'rawat_jalan' => 'Rawat Jalan',
                        //        'rawat_inap' => 'Rawat Inap',
                        //    ]),

                        //TextInput::make('status')
                        //    ->default('draft')
                        //    ->disabled(),

                        TextInput::make('date')
                            ->type('date')
                            ->required(),

                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Item Jasa Dokter')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('user_id')
                                    ->label('Dokter')
                                    ->relationship(
                                        name: 'doctor',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function ($query, callable $get) {
                                            $companyId = $get('../../company_id');

                                            if ($companyId) {
                                                $query->where('company_id', $companyId);
                                            }
                                        }
                                    )
                                    ->searchable()
                                    ->nullable()
                                    ->columnSpanFull()
                                    ->reactive(),

                                Toggle::make('is_manual')
                                    ->label('Input Manual')
                                    ->reactive()
                                    ->columnSpanFull(),

                                Select::make('doctor_service_id')
                                    ->label('Master Jasa')
                                    ->options(
                                        MasterDoctorService::query()
                                            ->where('is_active', true)
                                            ->pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->reactive()
                                    ->hidden(fn ($get) => $get('is_manual'))
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (!$state) {
                                            return;
                                        }

                                        $service = MasterDoctorService::find($state);

                                        if ($service) {
                                            $set('service_name', $service->name);
                                            $set('amount', $service->amount);
                                        }
                                    }),

                                TextInput::make('service_name')
                                    ->label('Nama Jasa')
                                    ->required(),

                                TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required(),

                                Textarea::make('notes')
                                    ->rows(1),
                            ])
                            ->defaultItems(1)
                            ->columns(2)
                            ->addActionLabel('Tambah Item Jasa')
                            ->grid(2)
                    ])->columnSpanFull(),
            ]);
    }
}
