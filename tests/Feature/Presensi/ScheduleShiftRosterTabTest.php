<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleShiftRosterTab;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleRosterChangeRequest;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\Shift\ScheduleShiftRotation;
use App\Models\Shift\ScheduleShiftTeam;
use App\Models\Shift\ScheduleShiftTeamMember;
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
        // View:Schedule (Shield) = gerbang buka ScheduleShiftRosterTab (sejak 2026-08-15, lihat
        // SchedulePolicy::view()). Mode edit roster butuh Update:ShiftScheduleAssignments
        // (menggantikan kelola_jadwal_shift yang dihapus 2026-09-11).
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Roster Manual Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Punya Update:Schedule ("Edit Jadwal") sekaligus Update:ShiftScheduleAssignments - lihat
     * ScheduleShiftRosterTab::getCanEditPastDatesProperty(). Bebas ubah tanggal berapa pun
     * termasuk yang sudah lewat, beda dari actingAsAbsensi() yang dibatasi mulai hari ini. */
    private function actingAsScheduleOwner(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Schedule Owner Roster Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule', 'Update:Schedule']);

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

    /** Sel kosong (belum ada baris ScheduleShiftAssignment) mode LIHAT (bukan editMode) dikasih
     * label "N/A" (dulu "LD", diganti instruksi user), beda dari mode edit yang tetap "-". */
    public function test_empty_cell_shows_na_label_in_view_mode(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSet('editMode', false)
            ->assertSee('N/A')
            ->assertSee('Lepas Dinas', false)
            ->call('startEdit')
            ->assertDontSee('N/A');
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
            ->assertSee('Isi Jadwal')
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
            ->assertSee('Isi Jadwal')
            ->assertSet('editMode', false)
            ->call('startEdit')
            ->assertSet('editMode', true)
            ->assertSee('Simpan')
            ->assertSee('Batal');
    }

    public function test_save_roster_persists_only_changed_cells_from_draft(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        // actingAsScheduleOwner (bukan actingAsAbsensi) - sejak 2026-09-28 yang cuma punya
        // Update:ShiftScheduleAssignments (bukan Edit Jadwal) diajukan ke approval dulu lewat
        // saveRoster() (lihat ScheduleRosterChangeApprovalTest), bukan langsung tersimpan - test
        // ini murni soal "cuma sel yang berubah yang ditulis", jadi pakai user yang tetap langsung
        // tersimpan.
        $user = $this->actingAsScheduleOwner();
        $dateA = now()->addDays(1)->toDateString();
        $dateB = now()->addDays(2)->toDateString();

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

    public function test_save_roster_refreshes_grid_in_same_request(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        // actingAsScheduleOwner - lihat alasan sama di test_save_roster_persists_only_changed_cells_from_draft().
        $user = $this->actingAsScheduleOwner();
        $date = now()->addDays(1)->toDateString();

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        // Tanpa refresh halaman: computed `roster` harus sudah memuat sel baru, bukan versi cache.
        $this->assertSame($shiftType->id, $component->get('roster')[$employee->id][$date] ?? null);
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

    /** Reset Jadwal DINONAKTIFKAN sementara (instruksi user 2026-09-29) - method-nya bypass alur
     * approval Perubahan Jadwal sama sekali, beda dari saveRoster(). Ditolak di server, bukan
     * cuma disembunyikan tombolnya. Aktifkan lagi test detail (tanggal lampau tidak ikut kehapus,
     * log tercatat source=reset, dst - lihat git history baris ini) begitu fiturnya dipakai lagi. */
    public function test_reset_period_is_disabled(): void
    {
        [$schedule] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('resetPeriod')
            ->assertForbidden();
    }

    public function test_describe_roster_log_reads_as_a_human_sentence_for_empty_to_filled(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        // actingAsScheduleOwner - lihat alasan sama di test_save_roster_persists_only_changed_cells_from_draft().
        $user = $this->actingAsScheduleOwner();
        $date = now()->addDays(1)->toDateString();

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
        // actingAsScheduleOwner - lihat alasan sama di test_save_roster_persists_only_changed_cells_from_draft().
        $user = $this->actingAsScheduleOwner();
        $date = now()->addDays(1)->toDateString();

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

        // generateFromTemplate() cuma boleh isi mulai hari ini (lihat lockBeforeDate()) buat
        // role yang tidak punya Edit Jadwal - anchor & tanggal yang dicek harus hari ini.
        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('rotationStartDate', now()->toDateString())
            ->set('rotationStartDay', 1)
            ->call('generateFromTemplate');

        $this->assertTrue(
            ScheduleShiftRosterLog::where('schedule_id', $schedule->id)
                ->where('user_id', $employee->id)
                ->whereDate('date', now()->toDateString())
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
        // actingAsScheduleOwner - lihat alasan sama di test_save_roster_persists_only_changed_cells_from_draft().
        $user = $this->actingAsScheduleOwner();
        $firstDate = now();
        // Kolom di file export/import urut 1..akhir bulan dari tanggal 1 - saveRoster() cuma
        // boleh tulis mulai hari ini (lockBeforeDate()), jadi kolomnya harus nunjuk hari ini,
        // bukan tanggal 1 kalau hari ini bukan tanggal 1.
        $colToday = $firstDate->day - 1;

        // Kolom hari ini -> shift kode, kolom besok -> "L" (Libur).
        $file = $this->makeRosterImportFile(['Budi Santoso'], [
            [$colToday => $shiftType->code, $colToday + 1 => 'L'],
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

    /** Export layout baru (legenda di kiri, grid mulai kolom F): isi legenda benar, sel grid
     * diwarnai, dan file hasil export bisa langsung diimpor balik (kolom "Karyawan" dicari lewat header). */
    public function test_export_has_legend_table_left_and_can_be_imported_back(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $shiftType->update(['name' => 'Pagi', 'color' => '#f97316', 'font_color' => '#ffffff', 'start_time' => '08:00:00', 'end_time' => '14:00:00']);
        $schedule->refresh();

        $today = now()->startOfDay();
        $dates = collect(range(1, $today->daysInMonth))->map(fn ($d) => $today->copy()->day($d))->all();
        $roster = [$employee->id => [$today->toDateString() => $shiftType->id, $today->copy()->addDay()->toDateString() => 'off']];

        $export = new \App\Exports\Presensi\ScheduleShiftRosterExport($schedule, collect([$employee]), $dates, $roster, 'September 2026');
        $rows = $export->array();

        // Legenda kiri: judul, periode, header, baris Libur/Cuti/Izin (kode tetap, tidak
        // tergantung roster), lalu shift (kode | keterangan | jam kerja | tim).
        $this->assertSame('JADWAL '.mb_strtoupper($schedule->name), $rows[0][0]);
        $this->assertSame('Periode Penjadwalan September 2026', $rows[1][0]);
        $this->assertSame(['Kode Shift', 'Keterangan', 'Jam Kerja', 'Tim'], array_slice($rows[2], 0, 4));
        $this->assertSame(['L', 'Libur', '-', '-'], array_slice($rows[3], 0, 4));
        $this->assertSame(['C', 'Cuti (disetujui otomatis)', '-', '-'], array_slice($rows[4], 0, 4));
        $this->assertSame(['Izin', 'Izin (disetujui otomatis)', '-', '-'], array_slice($rows[5], 0, 4));
        $this->assertSame([$shiftType->code, 'Pagi', '08:00 s.d 14:00', '-'], array_slice($rows[6], 0, 4));
        // Kolom E kosong, grid mulai kolom F dengan header "Karyawan".
        $this->assertSame('', $rows[2][4]);
        $this->assertSame('Karyawan', $rows[2][5]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);
        $export->styles($sheet);

        // Kode shift di legenda & sel roster hari ini diwarnai warna shift; "L" pakai warna Libur.
        $this->assertSame('F97316', $sheet->getStyle('A7')->getFill()->getStartColor()->getRGB());
        $todayCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(7 + $today->day - 1).'4';
        $this->assertSame('F97316', $sheet->getStyle($todayCell)->getFill()->getStartColor()->getRGB());

        // Round-trip: simpan xlsx hasil export lalu impor balik ke roster.
        $path = storage_path('framework/testing/roster-export-'.uniqid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $file = UploadedFile::fake()->createWithContent('roster-export.xlsx', file_get_contents($path));

        $component = Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file)
            ->assertSet('editMode', true);

        $this->assertSame($shiftType->id, $component->get('draft')[$employee->id][$today->toDateString()]);
        $this->assertSame('off', $component->get('draft')[$employee->id][$today->copy()->addDay()->toDateString()]);
    }

    /** Warna tim di export: banner (mode Baris) / kolom Tim (mode Kolom) pakai warna tim + font putih,
     * "Tanpa Tim" abu-abu; kolom Tim di legenda berwarna kalau cuma satu tim (dari beberapa) yang
     * boleh memakai shiftnya. */
    public function test_export_colors_team_banner_column_and_legend_by_team_color(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $schedule->update(['use_teams' => true]);
        $schedule->refresh();

        $teamA = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A', 'color' => '#ff0000']);
        ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu B', 'color' => '#0000ff'])
            ->shiftTypes()->sync([]);
        // Regu A dibatasi cuma boleh shift ini; Regu B tanpa batasan (boleh semua) -> keduanya boleh, legenda tidak diwarnai.
        $teamA->shiftTypes()->sync([$shiftType->id]);

        $other = User::factory()->create(['company_id' => $employee->company_id, 'name' => 'Tanpa Regu Orang']);
        $date = now()->startOfMonth();
        $names = [$employee->id => 'Regu A'];

        $color = fn ($sheet, string $cell) => [
            $sheet->getStyle($cell)->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle($cell)->getFont()->getColor()->getRGB(),
        ];

        foreach (['row', 'column'] as $mode) {
            $export = new \App\Exports\Presensi\ScheduleShiftRosterExport($schedule, collect([$employee, $other]), [$date], [], 'label', $names, $mode);
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray($export->array());
            $export->styles($sheet);

            if ($mode === 'row') {
                // Banner "Regu A" baris 4 (merge F4:G4), "Tanpa Tim" baris 7.
                $this->assertSame(['FF0000', 'FFFFFF'], $color($sheet, 'F4'));
                $this->assertSame(['E2E8F0', '000000'], $color($sheet, 'F6'));
            } else {
                // Kolom Tim (F) - Regu A baris 4, Tanpa Tim baris 5.
                $this->assertSame(['FF0000', 'FFFFFF'], $color($sheet, 'F4'));
                $this->assertSame(['E2E8F0', '000000'], $color($sheet, 'F5'));
            }
        }

        // Legenda: shift ini boleh dipakai Regu A dan Regu B (tanpa batasan) -> "Semua Tim", tidak diwarnai tim.
        // Baris ke-4 (array index 6) - 3 baris tetap (L/C/I) mendahului daftar shift.
        $export = new \App\Exports\Presensi\ScheduleShiftRosterExport($schedule, collect([$employee]), [$date], [], 'label', $names);
        $rows = $export->array();
        $this->assertSame('Semua Tim', $rows[6][3]);

        // Batasi Regu B ke shift lain -> hanya Regu A yang boleh shift ini -> nama tim + warna tim, font putih.
        $otherShift = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'ZZ', 'name' => 'Lain', 'start_time' => '20:00:00', 'end_time' => '08:00:00', 'counted_work_days' => 1]);
        ScheduleShiftTeam::where('name', 'Regu B')->first()->shiftTypes()->sync([$otherShift->id]);
        $schedule->refresh();

        $export = new \App\Exports\Presensi\ScheduleShiftRosterExport($schedule, collect([$employee]), [$date], [], 'label', $names);
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray($export->array());
        $export->styles($sheet);

        $this->assertSame('Regu A', $sheet->getCell('D7')->getValue());
        $this->assertSame(['FF0000', 'FFFFFF'], $color($sheet, 'D7'));
        $this->assertSame('Regu B', $sheet->getCell('D8')->getValue());
        $this->assertSame(['0000FF', 'FFFFFF'], $color($sheet, 'D8'));
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

    /** Jadwal TIDAK pakai Tim sama sekali - import jalan normal, tidak ada pembatasan shift apa
     * pun (2026-09-14, salah satu dari 3 skenario: tanpa tim / pakai tim tanpa batasan / pakai
     * tim dengan batasan shift). */
    public function test_import_excel_without_teams_applies_shift_normally(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth();

        $this->assertFalse($schedule->fresh()->use_teams);

        $file = $this->makeRosterImportFile(['Budi Santoso'], [[0 => $shiftType->code]]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file);

        $this->assertSame($shiftType->id, $component->get('draft')[$employee->id][$date->toDateString()]);
    }

    /** Jadwal pakai Tim, karyawan sudah di-assign ke 1 tim, TAPI tim itu belum diatur daftar
     * shift-nya (kosong = tidak dibatasi) - import shift apa pun tetap boleh. */
    public function test_import_excel_with_team_but_no_shift_restriction_allows_any_shift(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $schedule->update(['use_teams' => true]);
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $team, now()->format('Y-m'), (string) $employee->id);
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth();

        // Tim ada, karyawan sudah masuk tim itu, TAPI $team->shiftTypes() masih kosong - artinya
        // tidak dibatasi, lihat ScheduleShiftTeamMember::allowedShiftTypeIdsFor().
        $file = $this->makeRosterImportFile(['Budi Santoso'], [[0 => $shiftType->code]]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file);

        $this->assertSame($shiftType->id, $component->get('draft')[$employee->id][$date->toDateString()]);
    }

    /** Jadwal pakai Tim DAN tim karyawan dibatasi cuma boleh shift tertentu - kode shift yang
     * BUKAN jatah tim itu harus dilewati saat import (sama seperti kode tidak dikenal), kode yang
     * memang jatahnya tetap diproses normal. */
    public function test_import_excel_blocks_shift_not_allowed_by_employee_team(): void
    {
        [$schedule, $shiftTypeP, $employee] = $this->makeManualShiftSchedule();
        $shiftTypeS = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'S', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00']);
        $schedule->update(['use_teams' => true]);
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $team->shiftTypes()->sync([$shiftTypeP->id]);
        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $team, now()->format('Y-m'), (string) $employee->id);
        $user = $this->actingAsAbsensi();
        $date = now()->startOfMonth();
        $nextDate = $date->copy()->addDay();

        // Kolom hari ini -> shift S (BUKAN jatah tim, harus dilewati), kolom besok -> shift P
        // (jatah tim, harus tetap masuk draft).
        $file = $this->makeRosterImportFile(['Budi Santoso'], [[0 => $shiftTypeS->code, 1 => $shiftTypeP->code]]);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', $file);

        $draft = $component->get('draft')[$employee->id];
        $this->assertSame('', $draft[$date->toDateString()]);
        $this->assertSame($shiftTypeP->id, $draft[$nextDate->toDateString()]);

        // Belum ada apa pun tersimpan permanen sampai Simpan diklik - konsisten sama alur import
        // manapun.
        $this->assertFalse(ScheduleShiftAssignment::where('user_id', $employee->id)->exists());
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

    /** Sejak 2026-09-28 tanggal lampau TIDAK lagi dilewati/skip buat yang tanpa Edit Jadwal -
     * malah ikut diajukan ke approval sama seperti tanggal lain (toh tidak pernah TERTULIS
     * SEBAGAI APPROVED sampai disetujui atasannya, lihat ScheduleRosterChangeApprovalTest). */
    public function test_save_roster_includes_past_dated_cells_in_the_approval_request_for_user_without_edit_jadwal(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $user->update(['company_id' => $schedule->company_id]);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $flow = MasterApprovalFlows::create([
            'company_id' => $schedule->company_id,
            'module' => 'schedule_roster_change',
            'name' => 'Alur Test',
            'flow_type' => 'default',
            'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
        $datePast = now()->subDay()->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$datePast}", $shiftType->id)
            ->call('saveRoster');

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $datePast)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->shift_type_id, 'Kolom approved belum boleh tertulis sebelum disetujui.');
        $this->assertSame($shiftType->id, $row->draft_shift_type_id);

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($request, 'Tanggal lampau harus ikut diajukan, bukan dilewati diam-diam.');
        $this->assertSame($request->id, $row->draft_request_id);
    }

    /** Yang punya Update:Schedule ("Edit Jadwal") bebas ubah tanggal lewat lewat saveRoster(). */
    public function test_save_roster_allows_past_dated_cells_for_user_with_edit_jadwal(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsScheduleOwner();
        $datePast = now()->subDay()->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$datePast}", $shiftType->id)
            ->call('saveRoster');

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $datePast)
                ->where('shift_type_id', $shiftType->id)->exists()
        );
    }

    /** generateFromTemplate() juga dibatasi mulai hari ini buat yang bukan Edit Jadwal - anchor
     * di masa lalu tidak boleh ikut mengisi tanggal yang sudah lewat. */
    public function test_generate_from_template_skips_past_dates_for_user_without_edit_jadwal(): void
    {
        $company = Companies::create(['code' => 'GNP', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
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
        $datePast = now()->subDay()->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('rotationStartDate', now()->startOfMonth()->toDateString())
            ->set('rotationStartDay', 1)
            ->call('generateFromTemplate');

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $datePast)->exists()
        );
    }

    /** Bulan 2+ ke depan dari bulan berjalan ASLI dikunci dari Isi Jadwal/Generate/Import/Export
     * (instruksi user 2026-09-16, sama threshold-nya sama ScheduleShiftTeamTab::periodLocked) -
     * grid tetap kelihatan (view-only), cuma tombol aksinya yang hilang & backend nolak. */
    public function test_period_two_months_ahead_hides_action_buttons_and_rejects_writes(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $lockedPeriod = now()->addMonths(2)->format('Y-m');

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('period', $lockedPeriod)
            ->assertSee('Budi Santoso')
            // "Isi Jadwal" polos ketangkep juga di tooltip sel terkunci biasa ("Mulai Isi
            // Jadwal") - cek elemen aksinya langsung biar tidak salah tangkap.
            ->assertDontSee('wire:click="startEdit"', false)
            ->assertDontSee('wire:click="exportExcel"', false)
            ->assertDontSee('Import Excel');

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('period', $lockedPeriod)
            ->call('startEdit')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('period', $lockedPeriod)
            ->call('exportExcel')
            ->assertForbidden();
    }

    /** Bulan depan (bulan berjalan + 1) TETAP bisa diisi normal - cuma 2+ bulan ke depan yang
     * dikunci. */
    public function test_period_next_month_is_still_editable(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $user = $this->actingAsAbsensi();
        $nextMonth = now()->addMonth()->format('Y-m');
        $date = now()->addMonth()->startOfMonth()->addDays(4)->toDateString();

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('period', $nextMonth)
            ->assertSee('Isi Jadwal')
            ->call('setCell', $employee->id, $date, $shiftType->id);

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('shift_type_id', $shiftType->id)
                ->exists()
        );
    }

    /** Carry-over Tim bulan depan tidak boleh cuma jalan pas buka tab Regu - buka Detail
     * Penjadwalan duluan (tanpa pernah ke tab Regu) juga harus men-trigger (instruksi user
     * 2026-09-16). Lihat ScheduleShiftTeamMember::autoCarryNextMonthFromCurrent(). */
    public function test_opening_next_month_in_roster_tab_also_auto_persists_team(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $schedule->update(['use_teams' => true]);
        $user = $this->actingAsAbsensi();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $currentMonth = now()->format('Y-m');
        $nextMonth = now()->addMonth()->format('Y-m');

        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $team, $currentMonth, $user->id);
        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $nextMonth));

        Livewire::actingAs($user)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('period', $nextMonth);

        $this->assertSame(
            $team->id,
            ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $nextMonth)?->team_id
        );
    }
}
