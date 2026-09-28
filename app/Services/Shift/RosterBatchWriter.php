<?php

namespace App\Services\Shift;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\CustomPermissionEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penulis batch roster harian - diekstrak dari
 * App\Livewire\Presensi\ScheduleShiftRosterTab::persistRosterBatch() (2026-09-28) supaya bisa
 * dipakai juga oleh alur approval (App\Services\Shift\ScheduleRosterChangeService::executeApproved(),
 * dieksekusi di luar request Livewire begitu Kepala Ruangan tanpa Update:Schedule disetujui
 * atasannya) tanpa duplikasi logic. Gerbang-gerbang (swap-lock, leave-lock, past-date-lock, jatah
 * Tim) TETAP di pemanggil (saveRoster()/ScheduleRosterChangeService) - class ini murni penulis
 * DB atas $pending yang sudah lolos validasi.
 */
class RosterBatchWriter
{
    /** @param array<int, array{user_id:string, date:string, before:?string, value:?string}> $pending */
    public function write(Schedule $schedule, array $pending, string $actorId): void
    {
        if (! $pending) {
            return;
        }

        $userIds = array_unique(array_column($pending, 'user_id'));
        $dates = array_unique(array_column($pending, 'date'));

        $existingIds = $this->shiftAssignmentQuery($schedule)->whereIn('user_id', $userIds)
            ->whereBetween('date', [min($dates), max($dates)])
            ->get(['id', 'user_id', 'date'])
            ->keyBy(fn ($row) => $row->user_id . '|' . $row->date->toDateString())
            ->map(fn ($row) => $row->id);

        $now = now();
        $companyId = $schedule->company_id;

        $deleteIds = [];
        $upsertRows = [];
        $logRows = [];

        foreach ($pending as $cell) {
            $key = $cell['user_id'] . '|' . $cell['date'];
            $existingId = $existingIds[$key] ?? null;

            if (! $cell['value']) {
                if ($existingId) {
                    $deleteIds[] = $existingId;
                }
            } else {
                $upsertRows[] = [
                    'id' => $existingId ?: (string) Str::ulid(),
                    'user_id' => $cell['user_id'],
                    'date' => $cell['date'],
                    'company_id' => $companyId,
                    'shift_type_id' => $cell['value'] === 'off' ? null : $cell['value'],
                    'off_day' => $cell['value'] === 'off',
                    'created_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($cell['value'] === 'off' && $cell['before'] !== 'off') {
                    $this->refundLeaveDayIfBecameOffDay($cell['user_id'], $cell['date']);
                }
            }

            $logRows[] = [
                'id' => (string) Str::ulid(),
                'schedule_id' => $schedule->id,
                'user_id' => $cell['user_id'],
                'date' => $cell['date'],
                'old_value' => $cell['before'],
                'new_value' => $cell['value'],
                'source' => 'manual',
                'changed_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($schedule, $deleteIds, $upsertRows, $logRows) {
            if ($deleteIds) {
                $this->shiftAssignmentQuery($schedule)->whereIn('id', $deleteIds)->delete();
            }

            if ($upsertRows) {
                ScheduleShiftAssignment::upsert(
                    $upsertRows,
                    ['user_id', 'date'],
                    ['company_id', 'shift_type_id', 'off_day', 'created_by', 'updated_at']
                );
            }

            if ($logRows) {
                ScheduleShiftRosterLog::insert($logRows);
            }
        });
    }

    /** Public - dipakai juga ScheduleRosterChangeService buat cari/timpa baris draft dengan
     * scope company yang sama persis. */
    public function shiftAssignmentQuery(Schedule $schedule): \Illuminate\Database\Eloquent\Builder
    {
        $query = ScheduleShiftAssignment::query();

        if (Auth::check() && $schedule->company_id !== Auth::user()->company_id
            && Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_ABSENSI)) {
            $query->withoutGlobalScope('company');
        }

        return $query;
    }

    private function refundLeaveDayIfBecameOffDay(string $userId, string $date): void
    {
        $detail = AttendanceRequestLeaveDetails::query()
            ->whereIn('category', [LeaveTypeCategoryEnum::TAHUNAN->value, LeaveTypeCategoryEnum::HAID->value])
            ->where('total_days', '>', 0)
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereNotNull('end_date')->where('start_date', '<=', $date)->where('end_date', '>=', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereNull('end_date')->where('start_date', $date);
                });
            })
            ->whereIn('request_id', AttendanceRequests::query()->select('id')
                ->where('user_id', $userId)->where('type', 'leave')->where('status', 'approved'))
            ->first();

        $detail?->decrement('total_days');
    }
}
