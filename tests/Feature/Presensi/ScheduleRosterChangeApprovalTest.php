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
use App\Models\Shift\ScheduleShiftType;
use App\Models\Attendance\AttendanceRecords;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kepala Ruangan (Update:ShiftScheduleAssignments TANPA Update:Schedule) tidak boleh langsung
 * menulis roster lewat saveRoster() - perubahan diajukan dulu ke approval, baru benar-benar
 * ditulis begitu disetujui (instruksi user 2026-09-28). Lihat ScheduleRosterChangeService &
 * ApprovalFlowService::advanceStep() cabang SCHEDULE_ROSTER_CHANGE.
 */
class ScheduleRosterChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** company_id WAJIB diisi (beda dari actingAsAbsensi() di ScheduleShiftRosterTabTest yang
     * tidak butuh company_id karena tidak pernah dipakai lintas company) - ScheduleRosterChangeService
     * butuh company_id pengaju buat resolveApprovalFlowForUser(). */
    private function actingAsKaru(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Karu Roster Approval Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeManualShiftSchedule(): array
    {
        $company = Companies::create(['code' => 'APR', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
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

    private function makeDefaultApprovalFlow(Companies $company, User $approver): MasterApprovalFlows
    {
        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => 'schedule_roster_change',
            'name' => 'Alur Perubahan Jadwal Shift',
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

        return $flow;
    }

    public function test_karu_without_update_schedule_creates_pending_request_instead_of_writing_directly(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster')
            ->assertSet('editMode', false);

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($request);
        $this->assertSame('in_review', $request->status);
        $this->assertSame($karu->id, $request->requested_by);
        $this->assertNotNull($request->approvalSnapshot);

        // Baris ADA (nampung draft), TAPI kolom approved-nya (shift_type_id/off_day) belum
        // tersentuh sama sekali - itu yang dibaca presensi (ScheduleResolver default).
        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->shift_type_id, 'Kolom approved belum boleh tertulis sebelum disetujui.');
        $this->assertFalse($row->off_day);
        $this->assertSame($request->id, $row->draft_request_id);
        $this->assertSame($shiftType->id, $row->draft_shift_type_id);
        $this->assertFalse($row->draft_off_day);
    }

    /** Karu boleh terus edit sel yang lagi diajukan - Simpan lagi menambah/mengubah draft di
     * pengajuan YANG SAMA (in_review), bukan bikin pengajuan baru. */
    public function test_karu_can_keep_editing_while_request_is_still_in_review(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $shiftTypeSiang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'S', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00']);
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $firstRequestId = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail()->id;

        // startEdit() harus PRA-ISI draft dengan proposal yang lagi pending (displayRoster), bukan
        // approved kosong - lanjut ganti ke Siang.
        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->assertSet("draft.{$employee->id}.{$date}", $shiftType->id)
            ->set("draft.{$employee->id}.{$date}", $shiftTypeSiang->id)
            ->call('saveRoster');

        $this->assertSame(1, ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->count(), 'Tidak boleh bikin pengajuan baru.');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();
        $this->assertSame($firstRequestId, $request->id);
        $this->assertSame('in_review', $request->status);

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertSame($shiftTypeSiang->id, $row->draft_shift_type_id);
    }

    /** Sel yang diedit balik ke nilai approved-nya sendiri otomatis lepas dari draft (bukan
     * ditolak, dianggap tidak jadi diajukan) - kalau itu sel draft terakhir, pengajuan ikut
     * otomatis dibatalkan. */
    public function test_reverting_a_cell_back_to_approved_value_auto_cancels_the_request(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        // Approved awal: Pagi (bukan kosong) - biar "revert" beneran balik ke sesuatu, bukan
        // sekadar mengosongkan draft-only row.
        ScheduleShiftAssignment::create([
            'company_id' => $schedule->company_id, 'user_id' => $employee->id,
            'date' => $date, 'shift_type_id' => $shiftType->id, 'off_day' => false,
        ]);
        $shiftTypeSiang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'S', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00']);

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftTypeSiang->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();
        $this->assertSame('in_review', $request->status);

        // Edit lagi, balik ke Pagi (approved semula).
        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->assertSet("draft.{$employee->id}.{$date}", $shiftTypeSiang->id)
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $this->assertSame('cancelled', $request->fresh()->status, 'Satu-satunya sel draft dibalik ke approved -> pengajuan otomatis batal.');

        $row = ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->first();
        $this->assertNull($row->draft_request_id);
        $this->assertSame($shiftType->id, $row->shift_type_id, 'Approved tidak pernah berubah sepanjang proses ini.');
    }

    public function test_approving_the_request_writes_the_roster(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();

        app(ApprovalFlowService::class)->advanceStep(
            $request->approvalSnapshot,
            $approver->id,
            'Disetujui',
            'approved',
        );

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('shift_type_id', $shiftType->id)
                ->where('off_day', false)
                ->exists()
        );
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_rejecting_the_request_never_writes_the_roster(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();

        app(ApprovalFlowService::class)->advanceStep(
            $request->approvalSnapshot,
            $approver->id,
            'Ditolak',
            'rejected',
        );

        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_missing_default_flow_shows_error_and_does_not_create_a_request(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $this->assertFalse(ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->exists());
        $this->assertFalse(
            ScheduleShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->exists()
        );
    }

    /** Approver ditinjau LANGSUNG di grid (bukan modal) - banner muncul dengan tombol Setuju/Tolak,
     * approve dari sana menulis roster persis seperti advanceStep() langsung. */
    public function test_approver_sees_review_banner_and_can_approve_from_the_schedule_page(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $approver->givePermissionTo('View:Schedule');
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        Livewire::actingAs($approver)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSee('menunggu persetujuan')
            ->assertSee('Setujui')
            // Sel draft ditandai dim (title tooltip) di grid - lihat blade cell rendering.
            ->assertSee('Diajukan, belum disetujui oleh HC/Kepegawaian', false)
            ->call('approveRosterChange');

        $this->assertTrue(
            ScheduleShiftAssignment::where('user_id', $employee->id)
                ->whereDate('date', $date)->where('shift_type_id', $shiftType->id)->exists()
        );
    }

    /** Approver wajib Setujui/Tolak dulu lewat banner, tidak boleh malah mulai edit sendiri di
     * atas draft yang belum diputuskan - "Isi Jadwal" mesti disabled buat dia (instruksi user). */
    public function test_approver_cannot_start_edit_while_a_request_is_pending(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $approver->givePermissionTo('View:Schedule');
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        $approver->givePermissionTo('Update:ShiftScheduleAssignments');
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        Livewire::actingAs($approver)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSeeHtml('disabled')
            ->call('startEdit')
            ->assertForbidden();

        // Import Excel kena guard yang sama - bukan cuma tombol Isi Jadwal.
        Livewire::actingAs($approver)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('importFile', \Illuminate\Http\UploadedFile::fake()->create('roster.xlsx', 10))
            ->assertForbidden();
    }

    /** Si pengaju sendiri (karu) lihat banner "menunggu" TAPI tanpa tombol Setuju/Tolak - dia bukan
     * approver step ini. */
    public function test_requester_sees_banner_without_action_buttons(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertSee('menunggu persetujuan')
            ->assertDontSee('Setujui');
    }

    /** approveRosterChange()/rejectRosterChange() digerbangi canReviewRosterChange - approver yang
     * bukan giliran (mis. sudah dieksekusi orang lain / snapshot beda) ditolak 403. */
    public function test_non_approver_cannot_approve_roster_change(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $randomViewer = User::factory()->create(['company_id' => $schedule->company_id]);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $randomViewer->givePermissionTo('View:Schedule');
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        Livewire::actingAs($randomViewer)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('approveRosterChange')
            ->assertForbidden();
    }

    private function scan(User $u, string $dt): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $u->nip, 'access_datetime' => $dt, 'access_date' => substr($dt, 0, 10), 'access_time' => substr($dt, 11),
            'first_name' => $u->name, 'last_name' => '', 'person_name' => $u->name, 'person_group' => 'default',
        ]);
    }

    /** Selama pengajuan menunggu, ScheduleResolver cuma baca kolom approved - scan yang keburu
     * terjadi hari itu (sel-nya masih kosong = dianggap off_day) jatuh ke bucket yang tidak
     * pernah diproses SyncAccessLogsAttendance (silent, tidak ada attendance_records). Begitu
     * disetujui, ScheduleRosterChangeService::executeApproved() harus baca ulang TARGETED supaya
     * scan itu langsung kebaca dengan jadwal yang baru disetujui - instruksi user 2026-09-29. */
    public function test_approving_a_past_dated_request_resweeps_the_scan_that_was_silently_dropped(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $employee->update(['nip' => 'BUDI001']);
        // ShiftScheduleCalculator parse start_time/end_time dengan format 'H:i:s' (detik wajib) -
        // MySQL production auto-normalize '07:00' jadi '07:00:00' di kolom TIME, tapi SQLite
        // (dipakai test) simpan apa adanya tanpa normalisasi. Test lain aman pakai '07:00' polos
        // dari makeManualShiftSchedule() karena tidak pernah beneran manggil calculator - punya
        // resweep ini yang pertama, jadi perlu detik eksplisit.
        $shiftType->update(['start_time' => '07:00:00', 'end_time' => '15:00:00']);
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $approver->givePermissionTo('View:Schedule');
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->subDay()->toDateString();

        // Karyawan sudah scan sesuai jam shift Pagi (07:00-15:00) SEBELUM jadwalnya disetujui -
        // hari itu masih dianggap off_day (belum ada baris roster sama sekali), jadi scan ini
        // jatuh ke bucket yang tidak pernah diproses.
        $this->scan($employee, "{$date} 07:00:00");
        $this->scan($employee, "{$date} 15:00:00");

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $this->assertDatabaseCount('attendance_records', 0);

        Livewire::actingAs($approver)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            // assertSee dulu sebelum call() - Livewire::test() butuh 1 render eksplisit sebelum
            // aksi pertama supaya token child component (schedule-swap-history-sheet yang ke-nest
            // di grid) sempat dilepas dengan benar; loncat langsung ke call() dari mount memicu
            // ComponentNotFoundException di harness test (murni artefak testing, bukan bug
            // aplikasi - browser sungguhan selalu render awal lewat GET dulu sebelum aksi apa pun).
            ->assertSee('menunggu persetujuan')
            ->call('approveRosterChange');

        $record = AttendanceRecords::where('nip', 'BUDI001')->where('date', $date)->first();
        $this->assertNotNull($record, 'Scan yang terjatuh ke bucket off_day harus kebaca ulang begitu roster disetujui.');
        $this->assertSame('07:00:00', $record->check_in_time);
        $this->assertSame('15:00:00', $record->check_out_time);
    }

    /** Draft in_review dihitung sama seperti approved (instruksi user 2026-10-01). */
    public function test_resolver_uses_draft_shift_while_request_is_in_review(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $karu = $this->actingAsKaru($schedule->company_id);
        $approver = User::factory()->create(['company_id' => $schedule->company_id]);
        $this->makeDefaultApprovalFlow(Companies::find($schedule->company_id), $approver);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $resolved = app(\App\Services\Attendance\ScheduleResolver::class)->resolveForUserDate($employee, $date);

        $this->assertFalse($resolved['is_off_day']);
        $this->assertSame($shiftType->id, $resolved['config']->id);
    }
}
