<?php

namespace App\Filament\Resources\LMS\LmsCourseBaselines\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LmsCourseBaselinesForm
{
    public static function configure(Schema $schema): Schema
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
                                if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
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
                    ])
            ]);
    }
}
