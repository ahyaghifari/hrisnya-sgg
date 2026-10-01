<?php

namespace App\Console\Commands;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Services\Shift\RosterScheduleRemapper;
use Illuminate\Console\Command;

class RemapRosterToSchedule extends Command
{
    protected $signature = 'roster:remap {schedule : ID jadwal tujuan} {--from= : Tanggal mulai (Y-m-d), default hari ini}';

    protected $description = 'Pindahkan shift_type roster karyawan jadwal ini (mulai tanggal tertentu) dari jadwal asal ke shift jadwal ini, cocokkan lewat kode shift';

    public function handle(RosterScheduleRemapper $remapper): int
    {
        $schedule = Schedule::findOrFail($this->argument('schedule'));
        $userIds = ScheduleAssignment::where('schedule_id', $schedule->id)->whereNotNull('user_id')->pluck('user_id')->all();

        $result = $remapper->remap($schedule, $userIds, $this->option('from') ?: now()->toDateString());

        $this->info("{$schedule->name}: {$result['moved']} baris dipindah, {$result['unmapped']} tidak ada padanan kode.");

        return self::SUCCESS;
    }
}
