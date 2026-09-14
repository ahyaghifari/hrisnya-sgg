<?php

namespace Tests\Feature\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\Schedule;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ShiftSwapRequestService/ApprovalFlowService dipindah dari model roster LAMA
 * (ShiftScheduleAssignments/shift_schedule_assignments, sudah tidak ada penulis aktif) ke BARU
 * (ScheduleShiftAssignment/schedule_shift_assignments, sumber roster yang dipakai perhitungan
 * absensi sekarang) - 2026-08-14. Test ini memverifikasi jalur store() + eksekusi swap saat
 * approval final disetujui benar-benar berjalan di skema baru (bukan cuma lolos syntax).
 */
class ShiftSwapRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;
    private ScheduleShiftType $malam;
    private User $approver;
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create([
            'code' => 'SWP',
            'name' => 'RS Simulasi',
            'location' => 'Banjarbaru',
            'is_active' => true,
        ]);

        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Jadwal Perawat',
            'schedule_type' => 'shift',
        ]);

        $this->pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'shift_type' => ShiftType::PAGI->value,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_overnight' => false,
        ]);

        $this->siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'shift_type' => ShiftType::SIANG->value,
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
            'is_overnight' => false,
        ]);

        $this->malam = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'MALAM',
            'name' => 'Malam',
            'shift_type' => ShiftType::MALAM->value,
            'start_time' => '23:00:00',
            'end_time' => '07:00:00',
            'is_overnight' => true,
        ]);

        $this->approver = User::factory()->create(['company_id' => $this->company->id]);

        // Kepala Ruangan jadwal - sekarang WAJIB ada, kalau null store() diblokir
        // (ShiftSwapRequestService::createApprovalSnapshot()). User terpisah biar jadi step
        // approval sungguhan di antara rekan & master flow.
        $this->head = User::factory()->create(['company_id' => $this->company->id]);
        $schedule->update(['head_user_id' => $this->head->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $this->company->id,
                'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                'name' => 'Alur Tukar Shift Test',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    private function assignShift(User $user, ScheduleShiftType $shiftType, string $date): ScheduleShiftAssignment
    {
        return ScheduleShiftAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_type_id' => $shiftType->id,
            'date' => $date,
            'off_day' => false,
        ]);
    }

    /** Baris Libur BENERAN (off_day=true, ada di tabel) - beda dari N/A/kosong (tidak ada baris
     * sama sekali), lihat ShiftSwapRequestService::assertReciprocalOffEligible(). */
    private function assignOffDay(User $user, string $date): ScheduleShiftAssignment
    {
        return ScheduleShiftAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_type_id' => null,
            'date' => $date,
            'off_day' => true,
        ]);
    }

    /** Urutan approval: rekan (target_id) -> Kepala Ruangan jadwal ($this->head) -> approver master
     * flow. Jadi 1 pengajuan butuh 3x advanceStep buat benar-benar selesai. */
    private function approve(ShiftSwapRequests $swapRequest): void
    {
        $swapRequest->load('approvalSnapshot.activity');

        app(ApprovalFlowService::class)->advanceStep(
            $swapRequest->approvalSnapshot,
            $swapRequest->target_id,
            'Disetujui rekan lewat automated test',
            'approved',
        );

        $swapRequest->refresh()->load('approvalSnapshot.activity');

        app(ApprovalFlowService::class)->advanceStep(
            $swapRequest->approvalSnapshot,
            $this->head->id,
            'Disetujui kepala ruangan lewat automated test',
            'approved',
        );

        $swapRequest->refresh()->load('approvalSnapshot.activity');

        app(ApprovalFlowService::class)->advanceStep(
            $swapRequest->approvalSnapshot,
            $this->approver->id,
            'Disetujui lewat automated test',
            'approved',
        );
    }

    private function setAttendanceSetting(string $key, string $value): void
    {
        $case = \App\Enum\Attendance\AttendanceSettingEnum::from($key);

        \App\Models\Setting::create([
            'company_id' => $this->company->id,
            'module' => 'attendance',
            'key' => $key,
            'type' => 'integer',
            'value' => $value,
            'label' => $case->label(),
            'description' => $case->helperText(),
        ]);
    }

    public function test_min_lead_days_setting_blocks_requests_that_are_too_soon(): void
    {
        $this->setAttendanceSetting(\App\Enum\Attendance\AttendanceSettingEnum::TUKAR_SHIFT_MIN_LEAD_DAYS->value, '3');

        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $tooSoon = now()->addDay()->toDateString();
        $scheduleA = $this->assignShift($perawatA, $this->pagi, $tooSoon);
        $scheduleB = $this->assignShift($perawatB, $this->siang, $tooSoon);

        Auth::login($perawatA);
        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_max_per_bulan_setting_blocks_when_quota_reached(): void
    {
        $this->setAttendanceSetting(\App\Enum\Attendance\AttendanceSettingEnum::TUKAR_SHIFT_MAX_PER_BULAN->value, '1');

        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $date = now()->addDays(10)->toDateString();
        $scheduleA = $this->assignShift($perawatA, $this->pagi, $date);
        $scheduleB = $this->assignShift($perawatB, $this->siang, $date);

        ShiftSwapRequests::create([
            'company_id' => $this->company->id,
            'requester_id' => $perawatA->id,
            'target_id' => $perawatB->id,
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
            'req_date' => $date,
            'target_date' => $date,
            'status' => 'in_review',
        ]);

        Auth::login($perawatA);
        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_kepala_ruangan_jadwal_disisipkan_sebagai_approver_kedua(): void
    {
        $head = User::factory()->create(['company_id' => $this->company->id]);
        Schedule::whereKey($this->pagi->schedule_id)->update(['head_user_id' => $head->id]);

        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->siang, '2026-08-10');

        Auth::login($perawatA);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $steps = collect($swap->approvalSnapshot->steps_snapshot);
        $this->assertSame($perawatB->id, $steps->firstWhere('order', 1)['approver_ref_id'], 'Step 1 = rekan.');
        $this->assertSame($head->id, $steps->firstWhere('order', 2)['approver_ref_id'], 'Step 2 = kepala ruangan jadwal.');
        $this->assertSame($this->approver->id, $steps->firstWhere('order', 3)['approver_ref_id'], 'Step 3 = master flow.');

        // rekan -> kepala ruangan -> approver master flow
        app(ApprovalFlowService::class)->advanceStep($swap->approvalSnapshot, $perawatB->id, 'ok rekan', 'approved');
        $swap->refresh()->load('approvalSnapshot.activity');
        app(ApprovalFlowService::class)->advanceStep($swap->approvalSnapshot, $head->id, 'ok karu', 'approved');
        $swap->refresh()->load('approvalSnapshot.activity');
        app(ApprovalFlowService::class)->advanceStep($swap->approvalSnapshot, $this->approver->id, 'ok atasan', 'approved');

        $this->assertSame('approved', $swap->fresh()->status);
        $this->assertSame($this->siang->id, $scheduleA->fresh()->shift_type_id);
    }

    public function test_kepala_ruangan_dilewati_kalau_dia_rekan_atau_pemohon(): void
    {
        // Kepala Ruangan jadwal = rekan (target) sendiri -> tidak disisipkan step kedua, langsung
        // rekan lalu master flow (rekan tidak approve dua kali).
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);
        Schedule::whereKey($this->pagi->schedule_id)->update(['head_user_id' => $perawatB->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->siang, '2026-08-10');

        Auth::login($perawatA);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $steps = collect($swap->approvalSnapshot->steps_snapshot);
        $this->assertCount(2, $steps, 'Cuma rekan + 1 step master flow.');
        $this->assertSame($perawatB->id, $steps->firstWhere('order', 1)['approver_ref_id']);
        $this->assertSame($this->approver->id, $steps->firstWhere('order', 2)['approver_ref_id']);
    }

    public function test_pengajuan_diblokir_kalau_jadwal_belum_ada_kepala_ruangan(): void
    {
        Schedule::whereKey($this->pagi->schedule_id)->update(['head_user_id' => null]);

        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);
        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->siang, '2026-08-10');

        Auth::login($perawatA);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kepala Ruangan');

        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_pengajuan_diblokir_kalau_slot_rekan_masih_in_review_di_pengajuan_lain(): void
    {
        $a = User::factory()->create(['company_id' => $this->company->id]);
        $b = User::factory()->create(['company_id' => $this->company->id]);
        $c = User::factory()->create(['company_id' => $this->company->id]);

        $bShift = $this->assignShift($b, $this->pagi, '2026-08-12');
        $cShift = $this->assignShift($c, $this->siang, '2026-08-12');
        $aShift = $this->assignShift($a, $this->pagi, '2026-08-15');

        // B ajukan tukar shift dengan jadwal C (status in_review).
        Auth::login($b);
        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $bShift->id,
            'target_schedule_id' => $cShift->id,
        ]);
        Auth::logout();

        // A coba ajukan tukar shift dengan jadwal C yang sama -> diblokir.
        Auth::login($a);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sedang dalam proses pengajuan tukar shift lain');

        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $aShift->id,
            'target_schedule_id' => $cShift->id,
        ]);
    }

    public function test_slot_bebas_lagi_setelah_pengajuan_lain_selesai(): void
    {
        $a = User::factory()->create(['company_id' => $this->company->id]);
        $b = User::factory()->create(['company_id' => $this->company->id]);
        $c = User::factory()->create(['company_id' => $this->company->id]);

        $bShift = $this->assignShift($b, $this->pagi, '2026-08-12');
        $cShift = $this->assignShift($c, $this->siang, '2026-08-12');
        $aShift = $this->assignShift($a, $this->pagi, '2026-08-15');

        Auth::login($b);
        $bcSwap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $bShift->id,
            'target_schedule_id' => $cShift->id,
        ]);
        Auth::logout();

        // C tolak di step-nya -> pengajuan B<->C selesai (rejected), slot C bebas lagi.
        app(ApprovalFlowService::class)->advanceStep($bcSwap->approvalSnapshot, $c->id, 'tolak', 'rejected');

        Auth::login($a);
        $swap = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $aShift->id,
            'target_schedule_id' => $cShift->id,
        ]);

        $this->assertSame('in_review', $swap->status);
    }

    public function test_same_date_swap_exchanges_shift_type_after_approval(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->siang, '2026-08-10');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->assertSame('in_review', $swapRequest->status);
        $this->assertSame($perawatB->id, $swapRequest->target_id);

        $this->approve($swapRequest);

        $this->assertSame('approved', $swapRequest->fresh()->status);
        // Tanggal sama -> yang ditukar shift_type_id-nya, bukan user_id (hindari bentrok unique
        // constraint (user_id, date)).
        $this->assertSame($this->siang->id, $scheduleA->fresh()->shift_type_id);
        $this->assertSame($this->pagi->id, $scheduleB->fresh()->shift_type_id);
        $this->assertSame($perawatA->id, $scheduleA->fresh()->user_id, 'Baris tetap milik pemiliknya, cuma shift-nya yang berubah.');
        $this->assertSame($perawatB->id, $scheduleB->fresh()->user_id);
    }

    public function test_different_date_swap_exchanges_owner_after_approval(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->approve($swapRequest);

        // Tanggal beda -> yang ditukar pemiliknya (user_id), tanggal & shift tetap di baris asal.
        $this->assertSame($perawatB->id, $scheduleA->fresh()->user_id);
        $this->assertSame($perawatA->id, $scheduleB->fresh()->user_id);
        $this->assertSame('2026-08-10', $scheduleA->fresh()->date->toDateString());
        $this->assertSame('2026-08-15', $scheduleB->fresh()->date->toDateString());
    }

    public function test_different_date_swap_conflicting_slot_becomes_unpaid_double_shift(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        // A minta tanggal 10, tukar dengan shift B tanggal 15. Tapi B TERNYATA sudah ada
        // shift Siang sendiri di tanggal 10 (bukan libur) - tidak bisa langsung "pindah" ke
        // baris A (unique user_id+date). Sejak 2026-09-13: swap TETAP dilaksanakan (A benar2
        // lepas), shift Pagi yang dioper ke B dicatat sebagai ExtraShiftAssignment
        // category=double_shift (tanpa jasa) di atas shift Siang B yang sudah ada.
        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');
        $busyB = $this->assignShift($perawatB, $this->siang, '2026-08-10');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->assertNotEmpty(app(ShiftSwapRequestService::class)->conflictWarnings($swapRequest));

        $this->approve($swapRequest);

        // A benar2 lepas dari shift tanggal 10 (baris asli dihapus).
        $this->assertNull(\App\Models\Shift\ScheduleShiftAssignment::find($scheduleA->id));
        // B tetap punya shift Siang aslinya (tidak tersentuh)...
        $this->assertNotNull($busyB->fresh(), 'Shift Siang B yang sudah ada tetap utuh.');
        // ...DITAMBAH shift Pagi yang dioper A, sebagai double-shift tanpa jasa.
        $doubleShift = \App\Models\Shift\ExtraShiftAssignment::where('user_id', $perawatB->id)
            ->whereDate('date', '2026-08-10')
            ->first();
        $this->assertNotNull($doubleShift, 'Shift yang dioper harus tercatat sebagai double-shift buat B.');
        $this->assertSame($this->pagi->id, $doubleShift->shift_type_id);
        $this->assertSame('double_shift', $doubleShift->category);
        $this->assertSame('approved', $doubleShift->status);
        $this->assertSame($swapRequest->id, $doubleShift->origin_shift_swap_request_id, 'Harus tertaut ke pengajuan tukar shift asalnya.');

        // Tidak ada konflik di sisi B (tanggal 15) - A tetap pindah normal ke baris itu.
        $this->assertSame($perawatA->id, $scheduleB->fresh()->user_id);
    }

    public function test_different_date_swap_no_longer_needs_extra_shift_rule_to_proceed(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');
        $this->assignShift($perawatB, $this->siang, '2026-08-10');

        // Sengaja TIDAK bikin ExtraShiftRule Siang->Pagi - dulu (sebelum 2026-09-12) ini bikin
        // submit gagal (throw); ExtraShiftRule sudah tidak relevan buat jalur tukar shift sama
        // sekali sekarang, cuma dipakai jalur Extra Shift manual.
        Auth::login($perawatA);

        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->assertNotNull($swapRequest);
    }

    public function test_reciprocal_off_swap_gives_both_sides_equal_days_off(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        // A kerja tanggal 10, B LIBUR beneran (baris ada, off_day=true) tanggal 10. Kompensasi:
        // A "bayar" pakai tanggal LIBURNYA SENDIRI (15) buat ambil alih shift B di tanggal itu
        // (B kerja beneran di situ) - lihat planning/2026-09-12-tukar-shift-libur-berpasangan.md
        // (instruksi user 2026-09-14: kompensasi harus tanggal Libur pemohon sendiri).
        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $offB = $this->assignOffDay($perawatB, '2026-08-10');
        $offA = $this->assignOffDay($perawatA, '2026-08-15');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_kind' => 'reciprocal_off',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->assertSame('reciprocal_off', $swapRequest->swap_kind);

        $this->approve($swapRequest);

        // B ambil alih shift A tanggal 10 (baris pindah, baris Libur lama B ditimpa/dihapus).
        $this->assertSame($perawatB->id, $scheduleA->fresh()->user_id);
        $this->assertNull($offB->fresh());

        // A ambil alih shift B tanggal 15 - baris Libur A sendiri di tanggal itu ikut ditimpa
        // (bukan konflik beneran, lihat applySlotResolution() mode 'move_over_off_day').
        $this->assertSame($perawatA->id, $scheduleB->fresh()->user_id);
        $this->assertNull($offA->fresh());

        // Tanggal yang masing-masing LEPAS jadi Libur EKSPLISIT balik (beda dari mode 'schedule'
        // biasa yang dibiarkan N-A) - ini memang "pertukaran libur", jatah libur harus keliatan
        // jelas seimbang di kedua sisi.
        $aOffOn10 = ScheduleShiftAssignment::where('user_id', $perawatA->id)->whereDate('date', '2026-08-10')->first();
        $this->assertNotNull($aOffOn10, 'A harus dapat baris Libur eksplisit di tanggal 10 (yang dilepas).');
        $this->assertTrue($aOffOn10->off_day);

        $bOffOn15 = ScheduleShiftAssignment::where('user_id', $perawatB->id)->whereDate('date', '2026-08-15')->first();
        $this->assertNotNull($bOffOn15, 'B harus dapat baris Libur eksplisit di tanggal 15 (yang dilepas).');
        $this->assertTrue($bOffOn15->off_day);
    }

    public function test_reciprocal_off_swap_rejects_target_without_real_off_day_row(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        // B TIDAK punya baris sama sekali tanggal 10 (N/A/kosong) - BUKAN Libur beneran, harus
        // ditolak (instruksi user: "kalo misalkan n/a atau kosong itu tidak bisa").
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');

        Auth::login($perawatA);
        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_kind' => 'reciprocal_off',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_reciprocal_off_swap_rejects_compensation_date_that_is_also_off_day(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $this->assignOffDay($perawatB, '2026-08-10');
        // Tanggal kompensasi (15) HARUS shift kerja B beneran, bukan libur B juga.
        $offB2 = $this->assignOffDay($perawatB, '2026-08-15');

        Auth::login($perawatA);
        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_kind' => 'reciprocal_off',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $offB2->id,
        ]);
    }

    public function test_reciprocal_off_swap_rejects_when_requester_already_busy_on_compensation_date(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $this->assignOffDay($perawatB, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');
        // A TERNYATA sudah punya shift lain (beneran) di tanggal kompensasi - beda dari mode
        // 'schedule' biasa (warning-only), di sini harus diblok keras.
        $this->assignShift($perawatA, $this->siang, '2026-08-15');

        Auth::login($perawatA);
        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_kind' => 'reciprocal_off',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_reciprocal_off_swap_rejects_compensation_date_where_requester_is_not_off(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $this->assignOffDay($perawatB, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');
        // A TIDAK punya baris apa pun (bukan Libur) tanggal 15 - sekadar "kosong" tidak cukup,
        // kompensasi wajib tanggal Libur A sendiri (instruksi user 2026-09-14).

        Auth::login($perawatA);
        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_kind' => 'reciprocal_off',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_reciprocal_off_swap_final_approval_fails_if_target_no_longer_off_by_then(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $offB = $this->assignOffDay($perawatB, '2026-08-10');
        $this->assignOffDay($perawatA, '2026-08-15');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_kind' => 'reciprocal_off',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        // B keburu di-assign shift beneran tanggal 10 (bukan Libur lagi) sebelum approval final.
        $offB->update(['off_day' => false, 'shift_type_id' => $this->siang->id]);

        $this->expectException(\InvalidArgumentException::class);

        $this->approve($swapRequest);
    }

    public function test_incompatible_shift_types_are_rejected_for_different_date(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        // Pagi <-> Malam tidak boleh ditukar (cuma pagi<->siang atau malam<->malam) - berlaku
        // buat tanggal BEDA.
        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->malam, '2026-08-11');

        Auth::login($perawatA);

        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }

    public function test_incompatible_shift_types_are_allowed_for_same_date(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        // Pagi <-> Malam TETAP boleh kalau tanggalnya SAMA (instruksi user 2026-08-20) - aturan
        // kompatibilitas kategori shift cuma berlaku buat tukar beda tanggal.
        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->malam, '2026-08-10');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->approve($swapRequest);

        $this->assertSame($this->malam->id, $scheduleA->fresh()->shift_type_id);
        $this->assertSame($this->pagi->id, $scheduleB->fresh()->shift_type_id);
    }
}
