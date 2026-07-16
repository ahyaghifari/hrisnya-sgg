<?php

namespace App\Filament\Resources\Payroll\FeeSubmissions\Schemas;

use App\Helpers\PermissionHelper;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class FeeSubmissionForm
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

                        TextInput::make('reference_number')
                            ->label('No Referensi'),

                        TextInput::make('subject_name')
                            ->label('Nama/Keterangan'),

                        TextInput::make('service_type')
                            ->label('Nama Service'),

                        TextInput::make('date')
                            ->type('date')
                            ->required(),

                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Item Fee')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('user_id')
                                    ->label('Penerima')
                                    ->relationship(
                                        name: 'user',
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

                                TextInput::make('name')
                                    ->label('Nama Item')
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
                            ->addActionLabel('Tambah Item')
                            ->grid(2)
                    ])->columnSpanFull(),
            ]);
    }
}
