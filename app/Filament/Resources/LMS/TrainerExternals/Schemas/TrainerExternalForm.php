<?php

namespace App\Filament\Resources\LMS\TrainerExternals\Schemas;

use App\Helpers\PermissionHelper;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TrainerExternalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Trainer Eksternal')
                    ->description('Form untuk membuat atau mengedit data trainer eksternal.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->label('Tipe Trainer')
                            ->options([
                                'internal' => 'Internal',
                                'external' => 'Eksternal',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Select::make('user_id')
                            ->label('Trainer')
                            ->relationship('user')
                            ->options(function () {
                                $companyId = Auth::user()->company_id;
                                
                                if ($companyId && !PermissionHelper::hasGlobalRole(Auth::user())) {
                                    return \App\Models\User::where('company_id', $companyId)
                                        ->pluck('name', 'id');
                                }

                                return \App\Models\User::get()->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->preload()
                            ->columnSpanFull()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($get('type') === 'internal' && $state) {
                                    $user = \App\Models\User::find($state);
                                    $set('name', $user?->name);
                                    $set('email', $user?->email);
                                    $set('phone', $user?->phone);
                                }
                            })
                            ->visible(fn (callable $get) => $get('type') === 'internal'),
                        
                        TextInput::make('name')
                            ->label('Nama Trainer')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->default(fn(callable $get) => 
                                $get('type') === 'external' ? null : \App\Models\User::find($get('user_id'))?->name
                            )
                            ->disabled(fn (callable $get) => $get('type') === 'internal')
                            ->dehydrated(true),

                        TextInput::make('expertise')
                            ->label('Keahlian')
                            ->required()
                            ->maxLength(255)
                            ->dehydrated(true)
                            ->columnSpanFull(),

                        Grid::make()
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->default(fn(callable $get) => 
                                        $get('type') === 'external' ? null : \App\Models\User::find($get('user_id'))?->email
                                    )
                                    ->disabled(fn (callable $get) => $get('type') === 'internal')
                                    ->dehydrated(true),

                                TextInput::make('phone')
                                    ->label('Nomor Telepon')
                                    ->required()
                                    ->maxLength(20)
                                    ->default(fn(callable $get) => 
                                        $get('type') === 'external' ? null : \App\Models\User::find($get('user_id'))?->phone
                                    )
                                    ->dehydrated(true),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        TextInput::make('company')
                            ->label('Perusahaan')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('address')
                            ->label('Alamat')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
