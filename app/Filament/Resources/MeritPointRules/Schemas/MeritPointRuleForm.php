<?php

namespace App\Filament\Resources\MeritPointRules\Schemas;

use App\Enum\MeritPoint\MeritPointEvent;
use App\Enum\MeritPoint\MeritPointTriggerType;
use App\Enum\MeritPoint\MeritPointType;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class MeritPointRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();
        $isGlobal = $user && PermissionHelper::hasGlobalRole($user);

        return $schema
            ->components([
                Section::make('Aturan Merit Point')
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->options(Companies::pluck('name', 'id'))
                            ->default(fn () => $isGlobal ? null : $user->company_id)
                            ->disabled(! $isGlobal)
                            ->dehydrated()
                            ->required(),

                        Select::make('type')
                            ->label('Tipe')
                            ->options(collect(MeritPointType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()]))
                            ->required(),

                        TextInput::make('name')
                            ->label('Nama Aturan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('points')
                            ->label('Poin')
                            ->numeric()
                            ->helperText('Boleh negatif untuk Penalty (mis. -5).')
                            ->required(),

                        Select::make('trigger_type')
                            ->label('Cara Pemakaian')
                            ->options(collect(MeritPointTriggerType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()]))
                            ->default(MeritPointTriggerType::MANUAL->value)
                            ->live()
                            ->helperText('Otomatis = poin dicatat sendiri oleh sistem saat kejadian di bawah terjadi. Manual = dipilih saat memberi poin.')
                            ->required(),

                        Select::make('event')
                            ->label('Kejadian Pemicu')
                            ->options(collect(MeritPointEvent::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                            ->visible(fn ($get) => $get('trigger_type') === MeritPointTriggerType::AUTOMATIC->value)
                            ->required(fn ($get) => $get('trigger_type') === MeritPointTriggerType::AUTOMATIC->value)
                            ->helperText('Cukup satu rule aktif per kejadian per perusahaan.'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
