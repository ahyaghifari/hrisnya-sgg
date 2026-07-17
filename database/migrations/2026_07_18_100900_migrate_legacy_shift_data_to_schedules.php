<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * COPY (bukan MOVE) data lama ke struktur baru — tabel lama (`m_shifts`,
     * `shift_templates`, `shift_template_days`, `shift_schedule_assignments`) TIDAK disentuh/
     * didrop sesi ini (lihat planning §10 Tahap 1). ID baris assignment/template dipertahankan
     * sama dengan aslinya supaya Tahap 2 (repoint `shift_swap_requests`, cutover Filament) tidak
     * perlu remapping ID.
     *
     * Per company: semua baris `m_shifts` digabung jadi 1 `schedules` ("Jadwal Shift Default",
     * schedule_type=shift) — keputusan user (planning §7 poin 1, dikonfirmasi 2026-07-18).
     */
    public function up(): void
    {
        $now = now();

        // 1. m_shifts -> schedules (1 per company) + schedule_shift_types
        $shiftsByCompany = DB::table('m_shifts')->whereNull('deleted_at')->get()->groupBy('company_id');
        $shiftTypeIdMap = []; // old m_shifts.id => new schedule_shift_types.id

        foreach ($shiftsByCompany as $companyId => $shifts) {
            $scheduleId = (string) Str::ulid();

            DB::table('schedules')->insert([
                'id' => $scheduleId,
                'company_id' => $companyId,
                'name' => 'Jadwal Shift Default',
                'schedule_type' => 'shift',
                'org_structure_id' => $shifts->first()->org_structure_id,
                'color' => null,
                'flexible_checkin_from' => null,
                'flexible_checkin_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($shifts as $shift) {
                $shiftTypeId = (string) Str::ulid();
                $shiftTypeIdMap[$shift->id] = $shiftTypeId;

                // Bug data lama: shift overnight (end_time <= start_time) tapi is_overnight=0
                // (ditemukan saat audit sesi ini, mis. "Shift Malam" 20:00-08:00) — dibetulkan
                // di sini, bukan ditebak/diikuti apa adanya.
                $isOvernight = (bool) $shift->is_overnight || $shift->end_time <= $shift->start_time;

                DB::table('schedule_shift_types')->insert([
                    'id' => $shiftTypeId,
                    'schedule_id' => $scheduleId,
                    'code' => $shift->code,
                    'name' => $shift->name,
                    'shift_type' => $shift->shift_type,
                    'start_time' => $shift->start_time,
                    'break_minutes' => 0,
                    'end_time' => $shift->end_time,
                    'is_overnight' => $isOvernight,
                    'color' => $shift->color,
                    'created_at' => $shift->created_at,
                    'updated_at' => $shift->updated_at,
                ]);
            }
        }

        // 2. shift_templates -> schedule_shift_templates (schedule_id dari mapping company di atas)
        // Ambil schedule_id per company dari baris yang baru dibuat.
        $scheduleIdByCompany = DB::table('schedules')->pluck('id', 'company_id');

        $templateIdMap = [];
        foreach (DB::table('shift_templates')->whereNull('deleted_at')->get() as $template) {
            $scheduleId = $scheduleIdByCompany[$template->company_id] ?? null;

            if (! $scheduleId) {
                continue; // company tanpa m_shifts sama sekali — tidak ada schedule buat ditempeli
            }

            $templateIdMap[$template->id] = $template->id; // ID dipertahankan sama

            DB::table('schedule_shift_templates')->insert([
                'id' => $template->id,
                'schedule_id' => $scheduleId,
                'name' => $template->name,
                'description' => $template->description,
                'cycle_length' => $template->cycle_length,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ]);
        }

        // 3. shift_template_days -> schedule_shift_template_days
        foreach (DB::table('shift_template_days')->get() as $day) {
            if (! isset($templateIdMap[$day->shift_template_id])) {
                continue;
            }

            DB::table('schedule_shift_template_days')->insert([
                'id' => (string) Str::ulid(),
                'schedule_shift_template_id' => $day->shift_template_id,
                'day_number' => $day->day_number,
                'shift_type_id' => $day->shift_id ? ($shiftTypeIdMap[$day->shift_id] ?? null) : null,
                'is_off_day' => $day->is_off_day,
                'created_at' => $day->created_at,
                'updated_at' => $day->updated_at,
            ]);
        }

        // 4. shift_schedule_assignments -> schedule_shift_assignments (ID dipertahankan sama)
        DB::table('shift_schedule_assignments')->orderBy('id')->chunk(500, function ($assignments) use ($shiftTypeIdMap) {
            $rows = [];

            foreach ($assignments as $a) {
                if (! isset($shiftTypeIdMap[$a->shift_id])) {
                    continue; // shift_id yatim (sudah dihapus dari m_shifts) — skip, bukan tebak
                }

                $rows[] = [
                    'id' => $a->id,
                    'company_id' => $a->company_id,
                    'user_id' => $a->user_id,
                    'shift_type_id' => $shiftTypeIdMap[$a->shift_id],
                    'date' => $a->date,
                    'notes' => $a->notes,
                    'schedule_status' => $a->schedule_status,
                    'created_by' => $a->created_by,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->updated_at,
                ];
            }

            if ($rows !== []) {
                DB::table('schedule_shift_assignments')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        DB::table('schedule_shift_assignments')->truncate();
        DB::table('schedule_shift_template_days')->truncate();
        DB::table('schedule_shift_templates')->truncate();
        DB::table('schedule_shift_types')->truncate();
        DB::table('schedules')->truncate();
    }
};
