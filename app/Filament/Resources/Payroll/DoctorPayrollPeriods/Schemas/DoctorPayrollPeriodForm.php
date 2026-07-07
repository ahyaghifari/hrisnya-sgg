<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Schemas;

use App\Helpers\PermissionHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DoctorPayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payrole Period')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->required()
                            ->default(fn () => Auth::user()?->company_id)
                            ->disabled(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()))
                            ->dehydrated(),

                        DatePicker::make('start_date')
                            ->required(),

                        DatePicker::make('end_date')
                            ->required()
                            ->after('start_date'),
                    ])->columnSpanFull(),
            ]);
    }
}
