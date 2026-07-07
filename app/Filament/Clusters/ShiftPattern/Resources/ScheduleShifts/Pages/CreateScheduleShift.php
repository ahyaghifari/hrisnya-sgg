<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Pages;

use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\ScheduleShiftResource;
use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Schemas\ScheduleShiftForm;
use App\Models\Shift\ShiftScheduleAssignments;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateScheduleShift extends CreateRecord
{
    protected static string $resource = ScheduleShiftResource::class;

    /**
     * Bulk-create shift assignments from Repeater groups.
     * Each group defines a set of users + their shift across the selected date range.
     * Different groups on the same date range can hold different shifts.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $period = CarbonPeriod::create($data['start_date'], $data['end_date']);

        $dates = collect($period)
            ->filter(fn (Carbon $d) => !($data['exclude_weekends'] && $d->isWeekend()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->values();

        if ($dates->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Tidak ada jadwal yang disimpan')
                ->body('Tidak ada hari efektif dalam rentang tanggal yang dipilih.')
                ->send();

            return new ShiftScheduleAssignments();
        }

        $groups    = $data['assignments'] ?? [];
        $companyId = Auth::user()->company_id;
        $createdBy = Auth::id();
        $now       = now();
        $rows      = [];

        // Build flat row list: each group × each user × each date
        foreach ($groups as $group) {
            $userIds = (array) ($group['user_ids'] ?? []);
            $shiftId = $group['shift_id'] ?? null;
            $notes   = $group['notes'] ?? null;

            if (empty($userIds) || !$shiftId) {
                continue;
            }

            foreach ($dates as $date) {
                foreach ($userIds as $userId) {
                    // Key by user+date so later groups override earlier ones for same pair
                    $rows["{$userId}|{$date}"] = [
                        'id'         => (string) Str::ulid(),
                        'company_id' => $companyId,
                        'user_id'    => $userId,
                        'shift_id'   => $shiftId,
                        'date'       => $date,
                        'notes'      => $notes,
                        'created_by' => $createdBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $rows = array_values($rows);

        if (empty($rows)) {
            Notification::make()
                ->warning()
                ->title('Tidak ada jadwal yang disimpan')
                ->body('Pastikan setiap grup memiliki karyawan dan shift yang dipilih.')
                ->send();

            return new ShiftScheduleAssignments();
        }

        DB::transaction(function () use ($rows, $data) {
            if ($data['override_existing']) {
                ShiftScheduleAssignments::upsert(
                    $rows,
                    ['user_id', 'date'],
                    ['shift_id', 'notes', 'updated_at']
                );
            } else {
                ShiftScheduleAssignments::insertOrIgnore($rows);
            }
        });

        Notification::make()
            ->success()
            ->title('Jadwal berhasil disimpan')
            ->body(count($rows) . ' jadwal shift telah di-assign.')
            ->send();

        return ShiftScheduleAssignments::where('user_id', $rows[0]['user_id'])
            ->where('date', $rows[0]['date'])
            ->firstOrNew();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        // Suppress default notification - we send a custom one in handleRecordCreation
        return null;
    }
}

