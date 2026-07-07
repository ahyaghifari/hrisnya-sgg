<?php

namespace App\Filament\Resources\ApprovalFlows\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ApprovalFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Alur Persetujuan')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('module')
                    ->label('Modul')
                    ->formatStateUsing
                    (function ($state) {
                        $map = [
                            'attendance_request' => 'Permintaan Izin, Cuti, Lembur',
                            'task_request' => 'Permintaan Penugasan Individu',
                            'jobposition_request' => 'Penambahan/Perubahan Jabatan',
                            'lms_course_request' => 'Pengajuan Pelatihan LMS',
                            'approval_candidate_applicant' => 'Persetujuan Pelamar Kerja',
                            'user_transition' => 'Perubahan Status Karyawan',
                            'swap_shift_request' => 'Permintaan Tukar Shift',
                            'additional_employee_request' => 'Permintaan Penambahan Karyawan',
                            'employee_replacement_request' => 'Permintaan Penggantian Karyawan',
                        ];
                        return $map[$state] ?? $state;
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('flow_type')
                    ->label('Tipe Alur')
                    ->formatStateUsing(fn ($state) => $state === 'default' ? 'Default' : 'Spesifik')
                    ->alignCenter()
                    ->badge()
                    ->colors([
                        'primary' => fn ($state) => $state === 'default',
                        'warning' => fn ($state) => $state !== 'default',
                    ]),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Tidak Aktif')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state,
                        'danger' => fn ($state) => !$state,
                    ])
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'asc')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
