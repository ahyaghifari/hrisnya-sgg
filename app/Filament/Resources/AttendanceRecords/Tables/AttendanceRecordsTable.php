<?php

namespace App\Filament\Resources\AttendanceRecords\Tables;

use App\Filament\Resources\AttendanceRecords\Schemas\AttendanceRecordCreateForm;
use App\Models\User;
use App\Repositories\Attendance\AttendanceImportRepository;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('D, d/m/Y'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->description(fn ($record) => "NIP: {$record->nip}")
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shift.name')
                    ->label('Shift')
                    ->description(function ($record) {
                        $shift = $record->shift;
                        if (!$shift) return null;
                        return Carbon::parse($shift->start_time)->format('H:i') . ' – ' . Carbon::parse($shift->end_time)->format('H:i');
                    })
                    ->placeholder('–')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'present'  => 'Hadir',
                        'late'     => 'Terlambat',
                        'absent'   => 'Tidak Hadir',
                        'overtime' => 'Lembur',
                        'permission'   => 'Izin',
                        'leave'    => 'Cuti',
                        'sick'     => 'Sakit',
                        'permit'   => 'Izin',
                        default    => '–',
                    })
                    ->color(fn ($state) => match ($state) {
                        'present'  => 'success',
                        'late'     => 'warning',
                        'absent'   => 'danger',
                        'overtime' => 'primary',
                        'permission'   => 'warning',
                        'leave'    => 'danger',
                        'sick'     => 'gray',
                        'permit'   => 'gray',
                        default    => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('check_in_time')
                    ->label('Masuk')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i') : '–')
                    ->color(fn ($record) => $record->late_minutes > 0 ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('check_out_time')
                    ->label('Keluar')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i') : '–')
                    ->color(fn ($record) => $record->early_leave_minutes > 0 ? 'warning' : null)
                    ->sortable(),

                TextColumn::make('working_minutes')
                    ->label('Jam Kerja')
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '–';

                        $hours = floor($state / 60);
                        $minutes = $state % 60;

                        return "{$hours}j {$minutes}m";
                    }),

                TextColumn::make('late_minutes')
                    ->label('Terlambat')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} mnt" : '–')
                    ->color(fn ($record) => ($record->late_minutes ?? 0) > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('early_leave_minutes')
                    ->label('Pulang Awal')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} mnt" : '–')
                    ->color(fn ($record) => ($record->early_leave_minutes ?? 0) > 0 ? 'warning' : 'gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('overtime_minutes')
                    ->label('Lembur')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} mnt" : '–')
                    ->color(fn ($record) => ($record->overtime_minutes ?? 0) > 0 ? 'primary' : 'gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('permission_minutes')
                    ->label('Izin')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} mnt" : '–')
                    ->color(fn ($record) => ($record->permission_minutes ?? 0) > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('permission_details')
                    ->label('Izin')
                    ->default(function ($record) {
                        if (!$record->user_id || !$record->date) return '–';

                        $permissions = \App\Models\Attendance\AttendanceRequests::where('user_id', $record->user_id)
                            ->where('type', 'permission')
                            ->where('status', 'approved')
                            ->whereHas('permissionDetails', fn ($q) => $q->where('date', $record->date))
                            ->with(['permissionDetails' => fn ($q) => $q->where('date', $record->date)])
                            ->get();

                        if ($permissions->isEmpty()) return '–';

                        $times = [];
                        foreach ($permissions as $permission) {
                            foreach ($permission->permissionDetails as $detail) {
                                $times[] = Carbon::parse($detail->start_time)->format('H:i') . '–' . Carbon::parse($detail->end_time)->format('H:i');
                            }
                        }

                        return implode(', ', $times);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('leave_status')
                    ->label('Cuti')
                    ->default(function ($record) {
                        if (!$record->user_id || !$record->date) return '–';

                        return \App\Models\Attendance\AttendanceRequests::where('user_id', $record->user_id)
                            ->where('type', 'leave')
                            ->where('status', 'approved')
                            ->whereHas('leaveDetails', fn ($q) => $q->where('start_date', '<=', $record->date)->where('end_date', '>=', $record->date))
                            ->exists() ? 'Cuti' : '–';
                    })
                    ->badge(fn ($state) => $state === 'Cuti')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'import' => 'gray',
                        'manual' => 'info',
                        'system' => 'warning',
                        default  => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Filter::make('date_range')
                    ->label('Rentang Tanggal')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->default(Carbon::now()->startOfMonth())
                            ->live()
                            ->reactive()
                            ->required(),

                        DatePicker::make('until')
                            ->label('Sampai Tanggal')
                            ->default(Carbon::now()->endOfMonth())
                            ->live()
                            ->reactive()
                            ->required(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'present'  => 'Hadir',
                        'late'     => 'Terlambat',
                        'absent'   => 'Tidak Hadir',
                        'overtime' => 'Lembur',
                        'permission'   => 'Izin',
                        'leave'    => 'Cuti',
                        'sick'     => 'Sakit',
                        'permit'   => 'Izin',
                    ]),

                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        'import' => 'Import',
                        'manual' => 'Manual',
                        'system' => 'System',
                    ]),

                Filter::make('has_late')
                    ->label('Hanya terlambat')
                    ->query(fn ($query) => $query->where('late_minutes', '>', 0))
                    ->toggle(),

                Filter::make('has_overtime')
                    ->label('Hanya lembur')
                    ->query(fn ($query) => $query->where('overtime_minutes', '>', 0))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->visible(fn ($record) => $record->exists ?? true),
                EditAction::make()
                    ->slideOver()
                    ->visible(fn ($record) => $record->exists ?? true),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->label('Tambah Absensi')
                    ->slideOver()
                    ->schema(fn (Schema $schema, $livewire) => AttendanceRecordCreateForm::configure($schema, $livewire->selectedUserId ?? null))
                    ->fillForm(fn ($livewire): array => [
                        'user_id' => $livewire->selectedUserId ?? null,
                    ])
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        if (empty($data['user_id'])) {
                            $data['user_id'] = $livewire->selectedUserId ?? null;
                        }
                        $user = User::find($data['user_id']);
                        $data['nip']    = $user?->nip;
                        $data['source'] = 'manual';
                        return $data;
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100, 'all']);
    }
}
