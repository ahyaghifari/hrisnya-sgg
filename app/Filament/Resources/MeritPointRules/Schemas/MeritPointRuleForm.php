<?php

namespace App\Filament\Resources\MeritPointRules\Schemas;

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
                            ->helperText('Trigger otomatis belum dibangun (masih DEMO) - rule AUTOMATIC saat ini tetap cuma dipakai manual lewat halaman Beri Merit Point.')
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
