<?php

namespace App\Filament\Resources\Master\Pangkats\Schemas;

use App\Models\Master\Companies;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PangkatsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->preload()
                    ->searchable()
                    ->columnSpanFull()
                    ->options(function () {
                        $user = Auth::user();
                        if ($user && $user->company_id && !\App\Helpers\PermissionHelper::hasGlobalRole($user)) {
                            return Companies::where('id', $user->company_id)->pluck('name', 'id');
                        }
                        return Companies::pluck('name', 'id');
                    })
                    ->default(fn () => Auth::user() ? Auth::user()->company_id : null)
                    ->visible(fn () => Auth::user() && \App\Helpers\PermissionHelper::hasGlobalRole(Auth::user())),

                TextInput::make('name')
                    ->label('Nama Pangkat')
                    ->required()
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Nama Pangkat wajib diisi.',
                    ]),
            ]);
    }
}
