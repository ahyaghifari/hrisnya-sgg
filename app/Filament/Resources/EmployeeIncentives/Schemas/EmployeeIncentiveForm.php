<?php

namespace App\Filament\Resources\EmployeeIncentives\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Employee\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EmployeeIncentiveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // company_id cuma dipakai buat nyaring pilihan Penerima — bukan kolom asli
                // di tabel employee_incentives (company diturunkan dari employee->company_id).
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()
                        ->pluck('name', 'id'))
                    ->default(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (callable $set) => $set('employee_id', null)),

                Select::make('employee_id')
                    ->label('Penerima')
                    ->options(fn (callable $get) => $get('company_id')
                        ? Employee::query()
                            ->where('company_id', $get('company_id'))
                            ->orderBy('nama_lengkap')
                            ->pluck('nama_lengkap', 'id')
                        : [])
                    ->searchable()
                    ->required(),

                TextInput::make('name')
                    ->label('Nama Program')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('incentive_date')
                    ->label('Tanggal')
                    ->required(),

                TextInput::make('amount')
                    ->label('Nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Textarea::make('note')
                    ->label('Catatan')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2);
    }
}
