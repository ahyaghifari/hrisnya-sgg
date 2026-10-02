<?php

namespace App\Filament\Resources\Master\Holidays\Pages;

use App\Filament\Resources\Master\Holidays\HolidayResource;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;

class ManageHolidays extends ManageRecords
{
    protected static string $resource = HolidayResource::class;

    protected static ?string $title = 'Hari Libur & Cuti Bersama';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exemptPositions')
                ->label('Jabatan Tanpa Cuti Bersama')
                ->color('gray')
                ->modalHeading('Jabatan yang tidak terkena cuti bersama')
                ->modalDescription('Jabatan terpilih kuota Cuti Tahunannya hanya kuota - cuti diambil, tanpa potongan cuti bersama.')
                ->schema([
                    Select::make('company_id')
                        ->label('Perusahaan')
                        ->options(fn () => Companies::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn ($state, Set $set) => $set('job_position_ids', DB::table('collective_leave_exempt_positions')
                            ->whereIn('job_position_id', JobPositions::where('company_id', $state)->select('id'))
                            ->pluck('job_position_id')->all())),
                    Select::make('job_position_ids')
                        ->label('Jabatan')
                        ->multiple()
                        ->searchable()
                        ->options(fn (Get $get) => JobPositions::where('company_id', $get('company_id'))->orderBy('name')->pluck('name', 'id')),
                ])
                ->action(function (array $data) {
                    $ids = $data['job_position_ids'] ?? [];
                    DB::transaction(function () use ($data, $ids) {
                        // Ganti daftar milik perusahaan ini saja; perusahaan lain tidak tersentuh.
                        DB::table('collective_leave_exempt_positions')
                            ->whereIn('job_position_id', JobPositions::where('company_id', $data['company_id'])->select('id'))
                            ->delete();
                        DB::table('collective_leave_exempt_positions')->insert(array_map(
                            fn ($id) => ['job_position_id' => $id, 'created_at' => now(), 'updated_at' => now()],
                            $ids,
                        ));
                    });
                    Notification::make()->title('Daftar jabatan disimpan')->success()->send();
                }),

            CreateAction::make()
                ->label('Tambah Hari Libur'),
        ];
    }
}
