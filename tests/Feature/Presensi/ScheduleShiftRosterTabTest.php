<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleShiftRosterTab;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\Shift\ScheduleShiftRotation;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifikasi edit manual per-sel di tab Detail Penjadwalan (setCell) — berlaku buat jadwal
 * Template maupun manual, termasuk import Excel (lihat class-level docblock di
 * ScheduleShiftRosterTab).
 */
class ScheduleShiftRosterTabTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(): User
    {
        // View:Schedule (Shield, bukan SISTEM_ABSENSI lagi) yang jadi gerbang ScheduleShiftRosterTab
        // sejak 2026-08-15 (lihat SchedulePolicy::view()) - SISTEM_ABSENSI/KELOLA_JADWAL_SHIFT
        // tetap digrant di sini karena beberapa test lain di file ini masih butuh KELOLA_JADWAL_SHIFT
        // buat cek mode edit.
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Roster Manual Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value, 'View:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeManualShiftSchedule(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Manual',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);

        return [$schedule, $shiftType, $employee];
    }

    public function test_generate_controls_are_hidden_but_manual_dropdown_still_renders(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertDontSee('Mulai Dari')
            ->assertSee('Budi Santoso')
            ->assertSee($shiftType->code);
    }

    public function test_manual_cell_edit_assigns_shift_without_any_template(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('shift_type_id', $shiftType->id)
                ->where('off_day', false)
                ->exists()
        );
    }

    public function test_cell_linked_to_shift_swap_request_cannot_be_edited(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        $reqShift = ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id,
            'user_id' => $employee->id,
            'shift_type_id' => $shiftType->id,
            'date' => $date,
            'off_day' => false,
        ]);

        $rekan = User::factory()->create(['company_id' => $schedule->company_id]);
        ShiftSwapRequests::create([
            'company_id' => $schedule->company_id,
            'requester_id' => $employee->id,
            'target_id' => $rekan->id,
            'req_schedule_id' => $reqShift->id,
            'req_date' => $date,
            'target_date' => $date,
            'status' => 'in_review',
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set('draft.' . $employee->id . '.' . $date, '')
            ->call('saveRoster');

        // Sel dikunci -> perubahan draft diabaikan, baris roster tetap utuh.
        $this->assertTrue(
            ScheduleShiftAssignment::whereKey($reqShift->id)->where('shift_type_id', $shiftType->id)->exists()
        );
    }

    public function test_manual_cell_edit_can_set_off_day(): void
    {
        [$schedule, , $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, 'off');

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->whereNull('shift_type_id')
                ->where('off_day', true)
                ->exists()
        );
    }

    public function test_manual_cell_edit_can_clear_back_to_empty(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id,
            'user_id' => $employee->id,
            'date' => $date,
            'shift_type_id' => $shiftType->id,
            'off_day' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, '');

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }

    public function test_manual_cell_edit_also_works_when_schedule_uses_template(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Rotasi',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => true,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSee('Mulai Dari')
            ->assertSee('Mulai Isi Jadwal')
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)->where('shift_type_id', $shiftType->id)->exists()
        );
    }

    public function test_grid_is_locked_until_start_edit_is_clicked(): void
    {
        [$schedule, , $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSee('Mulai Isi Jadwal')
            ->assertSet('editMode', false)
            ->call('startEdit')
            ->assertSet('editMode', true)
            ->assertSee('Simpan')
            ->assertSee('Batal');
    }

    public function test_save_roster_persists_only_changed_cells_from_draft(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $dateA = now()->startOfMonth()->addDays(4)->toDateString();
        $dateB = now()->startOfMonth()->addDays(9)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$dateA}", $shiftType->id)
            ->set("draft.{$employee->id}.{$dateB}", 'off')
            ->call('saveRoster')
            ->assertSet('editMode', false);

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $dateA)
                ->where('shift_type_id', $shiftType->id)->where('off_day', false)->exists()
        );
        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $dateB)
                ->whereNull('shift_type_id')->where('off_day', true)->exists()
        );
    }

    public function test_cancel_edit_discards_draft_changes(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('cancelEdit')
            ->assertSet('editMode', false);

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }

    public function test_reset_period_deletes_all_roster_rows_in_selected_month(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $dateInside = now()->startOfMonth()->addDays(4)->toDateString();
        $dateOutside = now()->startOfMonth()->subMonth()->addDays(4)->toDateString();

        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id, 'user_id' => $employee->id,
            'date' => $dateInside, 'shift_type_id' => $shiftType->id, 'off_day' => false,
        ]);
        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id, 'user_id' => $employee->id,
            'date' => $dateOutside, 'shift_type_id' => $shiftType->id, 'off_day' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('resetPeriod');

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $dateInside)->exists()
        );
        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $dateOutside)->exists()
        );
    }

    public function test_reset_period_logs_deleted_rows_with_reset_source(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id, 'user_id' => $employee->id,
            'date' => $date, 'shift_type_id' => $shiftType->id, 'off_day' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('resetPeriod');

        $this->assertTrue(
            ScheduleShiftRosterLog::where('schedule_id', $schedule->id)
                ->where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('old_value', $shiftType->id)
                ->whereNull('new_value')
                ->where('source', 'reset')
                ->where('changed_by', $user->id)
                ->exists()
        );
    }

    public function test_describe_roster_log_reads_as_a_human_sentence_for_empty_to_filled(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $log = ScheduleShiftRosterLog::where('schedule_id', $schedule->id)->where('user_id', $employee->id)->first();

        $this->assertStringContainsString('Mengisi jadwal Budi Santoso', $component->instance()->describeRosterLog($log));
        $this->assertStringContainsString($shiftType->code, $component->instance()->describeRosterLog($log));
    }

    public function test_save_roster_logs_changed_cells_with_manual_source(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $this->assertTrue(
            ScheduleShiftRosterLog::where('schedule_id', $schedule->id)
                ->where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->whereNull('old_value')
                ->where('new_value', $shiftType->id)
                ->where('source', 'manual')
                ->exists()
        );
    }

    public function test_generate_from_template_logs_newly_filled_cells_with_generate_source(): void
    {
        $company = Companies::create(['code' => 'GEN', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Rotasi',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => true,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);
        ScheduleShiftRotation::create([
            'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'day' => 1, 'shift_type_id' => $shiftType->id, 'off_day' => false,
        ]);
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('rotationStartDate', now()->startOfMonth()->toDateString())
            ->set('rotationStartDay', 1)
            ->call('generateFromTemplate');

        $this->assertTrue(
            ScheduleShiftRosterLog::where('schedule_id', $schedule->id)
                ->where('user_id', $employee->id)
                ->whereDate('date', now()->startOfMonth()->toDateString())
                ->whereNull('old_value')
                ->where('new_value', $shiftType->id)
                ->where('source', 'generate')
                ->exists()
        );
    }

    /** Bikin xlsx grid persis format ScheduleShiftRosterExport (2 baris judul, baris 3 header,
     * baris 4.. data) - $cells: [rowIndex 0-based dari data => [colIndex 0-based dari kolom
     * tanggal pertama => kode shift]]. */
    private function makeRosterImportFile(array $employeeNames, array $cells): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['judul'], null, 'A1');
        $sheet->fromArray(['periode'], null, 'A2');
        $sheet->fromArray(['Karyawan'], null, 'A3');

        foreach ($employeeNames as $i => $name) {
            $row = 4 + $i;
            $sheet->setCellValue("A{$row}", $name);

            foreach ($cells[$i] ?? [] as $colIndex => $code) {
                $sheet->setCellValueByColumnAndRow($colIndex + 2, $row, $code);
            }
        }

        $path = storage_path('framework/testing/roster-import-'.uniqid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        // UploadedFile::fake()->createWithContent() (bukan `new UploadedFile(...)` polos) - Livewire's
        // file upload testing helper butuh properti public $name yang cuma ada di
        // Illuminate\Http\Testing\File (dikembalikan fake()->...), bukan di UploadedFile dasar.
        return UploadedFile::fake()->createWithContent('roster-import.xlsx', file_get_contents($path));
    }

    public function test_import_excel_stages_into_draft_without_saving_until_save_roster_is_called(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $firstDate = now()->startOfMonth();

        // Kolom 0 -> tanggal 1 (shift kode), kolom 1 -> tanggal 2 ("L" = Libur).
        $file = $this->makeRosterImportFile(['Budi Santoso'], [
            [0 => $shiftType->code, 1 => 'L'],
        ]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file)
            ->assertSet('editMode', true);

        $this->assertSame($shiftType->id, $component->get('draft')[$employee->id][$firstDate->toDateString()]);
        $this->assertSame('off', $component->get('draft')[$employee->id][$firstDate->copy()->addDay()->toDateString()]);

        // Belum disimpan ke DB - masih tertampung di draft sampai user klik Simpan.
        $this->assertFalse(ScheduleShiftAssignment::where('user_id', $employee->id)->exists());

        $component->call('saveRoster');

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $firstDate->toDateString())
                ->where('shift_type_id', $shiftType->id)->where('off_day', false)->exists()
        );
        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $firstDate->copy()->addDay()->toDateString())
                ->whereNull('shift_type_id')->where('off_day', true)->exists()
        );
    }

    public function test_import_excel_skips_unrecognized_shift_codes_and_keeps_existing_value(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth();

        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id, 'user_id' => $employee->id,
            'date' => $date->toDateString(), 'shift_type_id' => $shiftType->id, 'off_day' => false,
        ]);

        // "ZZ" bukan kode shift yang dikenal di schedule ini - sel itu harus dilewati, bukan
        // dikosongkan.
        $file = $this->makeRosterImportFile(['Budi Santoso'], [[0 => 'ZZ']]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file);

        $this->assertSame($shiftType->id, $component->get('draft')[$employee->id][$date->toDateString()]);
    }

    public function test_import_excel_also_works_when_schedule_uses_template(): void
    {
        $company = Companies::create(['code' => 'TPL', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Rotasi',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => true,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);
        $user = $this->actingAsAbsensi();

        $file = $this->makeRosterImportFile(['Budi Santoso'], [[0 => $shiftType->code]]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file)
            ->assertSet('editMode', true);

        $this->assertSame($shiftType->id, $component->get('draft')[$employee->id][now()->startOfMonth()->toDateString()]);
    }

    public function test_start_edit_also_works_when_schedule_uses_template(): void
    {
        $company = Companies::create(['code' => 'TPL', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Rotasi',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => true,
        ]);
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->assertSet('editMode', true);
    }
}
