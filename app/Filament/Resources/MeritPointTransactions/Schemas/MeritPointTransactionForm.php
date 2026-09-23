<?php

namespace App\Filament\Resources\MeritPointTransactions\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * 2 dari 3 cara (§3.4) yang dibangun sekarang: "Manual pakai Rule" & "Manual Bebas". "Automatic
 * Rule" belum ada mekanisme trigger-nya (§4 non-goals), jadi tidak ada form untuk itu - rule
 * dengan trigger_type AUTOMATIC tetap bisa dipilih di sini, cuma dipakainya tetap manual. Cuma
 * dipakai Create (List+Create saja, ledger tidak pernah diedit - lihat MeritPointTransactionResource).
 */
class MeritPointTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::createSchema())->columns(2);
    }

    protected static function createSchema(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $userOptions = User::query()
            ->when(
                $companyId && !PermissionHelper::hasGlobalRole($user),
                fn ($q) => $q->where('company_id', $companyId)
            )
            ->pluck('name', 'id');

        return [
            Section::make('Beri Merit Point')
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label('Karyawan')
                        ->searchable()
                        ->options($userOptions)
                        ->required(),

                    Select::make('mode')
                        ->label('Cara')
                        ->options([
                            'rule' => 'Pakai Aturan',
                            'manual' => 'Manual Bebas',
                        ])
                        ->default('rule')
                        ->live()
                        ->dehydrated(false)
                        ->required(),

                    Select::make('rule_id')
                        ->label('Aturan')
                        ->options(
                            MeritPointRule::query()
                                ->where('is_active', true)
                                ->when(
                                    $companyId && !PermissionHelper::hasGlobalRole($user),
                                    fn ($q) => $q->where('company_id', $companyId)
                                )
                                ->get()
                                ->mapWithKeys(fn ($rule) => [$rule->id => "{$rule->name} ({$rule->points})"])
                        )
                        ->live()
                        ->visible(fn (callable $get) => $get('mode') === 'rule')
                        ->required(fn (callable $get) => $get('mode') === 'rule')
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            $rule = MeritPointRule::find($get('rule_id'));
                            $set('points', $rule?->points);
                        })
                        ->columnSpanFull(),

                    TextInput::make('points')
                        ->label('Poin')
                        ->numeric()
                        ->required()
                        ->disabled(fn (callable $get) => $get('mode') === 'rule')
                        ->dehydrated()
                        ->helperText(fn (callable $get) => $get('mode') === 'rule' ? 'Snapshot dari aturan - tidak ikut berubah walau aturan diedit belakangan.' : 'Boleh negatif (penalty).'),

                    Textarea::make('reason')
                        ->label('Catatan')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }
}
