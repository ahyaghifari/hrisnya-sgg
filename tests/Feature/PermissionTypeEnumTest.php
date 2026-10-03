<?php

namespace Tests\Feature;

use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Livewire\SubmitPermissionRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Jenis Izin tetap (enum), bukan master per perusahaan - form tetap 2 tingkat: kelompok lalu jenis. */
class PermissionTypeEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_expose_their_own_types_and_flat_groups_have_none(): void
    {
        $this->assertSame(
            [PermissionTypeEnum::TERLAMBAT, PermissionTypeEnum::PULANG_CEPAT, PermissionTypeEnum::MENINGGALKAN_JAM_KERJA],
            PermissionTypeEnum::forGroup(PermissionGroupEnum::MENINGGALKAN_JAM_KERJA)
        );
        $this->assertCount(8, PermissionTypeEnum::forGroup(PermissionGroupEnum::KHUSUS));

        // Tidak Masuk & Sakit: tidak ada dropdown jenis di form (hasSubKategori false).
        $this->assertFalse(PermissionGroupEnum::TIDAK_MASUK_SEHARIAN->hasSubKategori());
        $this->assertFalse(PermissionGroupEnum::SAKIT->hasSubKategori());
    }

    public function test_lookup_helpers_resolve_stored_labels_and_categories(): void
    {
        $this->assertSame(PermissionTypeEnum::TIDAK_MASUK, PermissionTypeEnum::firstForCategory(PermissionTypeCategoryEnum::IZIN_SEHARIAN->value));
        $this->assertSame(PermissionTypeEnum::SAKIT, PermissionTypeEnum::firstForCategory(PermissionTypeCategoryEnum::IZIN_SAKIT->value));
        $this->assertSame(PermissionTypeEnum::MENIKAH_KARYAWAN, PermissionTypeEnum::fromLabel('Izin Menikah (Karyawan)'));
        $this->assertNull(PermissionTypeEnum::fromLabel('Izin Medis')); // data lama/custom
        $this->assertSame(3, PermissionTypeEnum::MENIKAH_KARYAWAN->defaultDays());
        $this->assertNull(PermissionTypeEnum::MUSIBAH->defaultDays());
        $this->assertSame(PermissionTypeCategoryEnum::IZIN_KHUSUS, PermissionTypeEnum::IBADAH_HAJI->category());
        $this->assertCount(14, PermissionTypeEnum::options());
    }

    /** 'id' bukan fillable di Companies (ULID auto-generate) - forceFill buat pakai ID production
     * yang di-hardcode di HasCompanyLabelOverride. */
    private function makeBanjarbaru(): Companies
    {
        $company = new Companies();
        $company->forceFill(['id' => '01k8271xa4nq8cezg3dq9vfr14', 'code' => '0001', 'name' => 'RSU Syifa Medika Banjarbaru', 'location' => 'X', 'is_active' => true]);
        $company->save();

        return $company;
    }

    public function test_type_from_another_group_is_rejected_by_the_form(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        // Jenis Khusus dipilih di kelompok Meninggalkan Jam Kerja -> ditolak.
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::MENIKAH_KARYAWAN->value)
            ->set('date', '2026-08-10')
            ->set('start_time', '08:00')
            ->set('end_time', '09:00')
            ->call('submit')
            ->assertHasErrors(['permission_type']);

        // Kelompok dengan jenis wajib memilih jenis.
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-12')
            ->call('submit')
            ->assertHasErrors(['permission_type']);

        // Kelompok flat tidak butuh jenis.
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10')
            ->call('submit')
            ->assertHasNoErrors(['permission_type']);
    }

    /** Izin Khusus: tanggal selesai tidak diisi otomatis, tapi dibatasi maxDays (Menikah Anak = 2 hari). */
    public function test_izin_khusus_end_date_is_not_prefilled_and_capped_by_max_days(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        $component = Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('date', '2026-08-10')
            ->set('permission_type', PermissionTypeEnum::MENIKAH_ANAK->value)
            ->assertSet('end_date', null)
            ->assertSet('maxEndDate', '2026-08-11');

        $component->set('end_date', '2026-08-12')->call('submit')->assertHasErrors(['end_date']);
    }

    /** RSU Syifa Medika Banjarbaru/Barabai pakai penamaan sendiri buat Tidak Masuk/Sakit
     * (instruksi user 2026-09-30) - company lain tetap label default. `category` yang tersimpan
     * (dibaca payroll) TIDAK berubah, cuma teks label. */
    public function test_two_companies_use_overridden_labels_for_tidak_masuk_and_sakit(): void
    {
        $banjarbaru = $this->makeBanjarbaru();
        $other = Companies::create(['code' => 'TST', 'name' => 'Other Company', 'location' => 'X', 'is_active' => true]);

        $this->assertSame('Izin Tidak Masuk / Sakit tanpa surat', PermissionGroupEnum::TIDAK_MASUK_SEHARIAN->label($banjarbaru->id));
        $this->assertSame('Izin Sakit Bersurat', PermissionGroupEnum::SAKIT->label($banjarbaru->id));
        $this->assertSame('Izin Tidak Masuk', PermissionGroupEnum::TIDAK_MASUK_SEHARIAN->label($other->id));
        $this->assertSame('Izin Sakit', PermissionGroupEnum::SAKIT->label($other->id));
        $this->assertSame('Izin Tidak Masuk', PermissionGroupEnum::TIDAK_MASUK_SEHARIAN->label(null));

        // MENINGGALKAN_JAM_KERJA/KHUSUS tidak ikut berubah, tidak ada override buat itu.
        $this->assertSame('Izin Khusus', PermissionGroupEnum::KHUSUS->label($banjarbaru->id));
    }

    /** Label baru ikut TERSIMPAN di permission_type (bukan cuma tampilan dropdown) - instruksi
     * user, dikonfirmasi aman buat payroll karena category disimpan terpisah dari label. */
    public function test_submitted_flat_permission_stores_overridden_label_for_the_two_companies(): void
    {
        $banjarbaru = $this->makeBanjarbaru();
        $approver = User::factory()->create(['company_id' => $banjarbaru->id]);
        $flow = MasterApprovalFlows::create([
            'company_id' => $banjarbaru->id,
            'module' => 'attendance_request',
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

        $user = User::factory()->create(['company_id' => $banjarbaru->id]);
        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10')
            ->set('purpose', 'demam')
            ->call('submit');

        $this->assertDatabaseHas('attendance_req_permission_details', [
            'permission_type' => 'Izin Sakit Bersurat',
            'category' => PermissionTypeCategoryEnum::IZIN_SAKIT->value,
        ]);
    }

    /** Izin Keluarga Inti Sakit cuma boleh dipilih 2 company override - company lain tidak lihat
     * opsinya sama sekali DAN ditolak di server kalau di-craft manual (instruksi user 2026-09-30). */
    public function test_keluarga_inti_sakit_is_restricted_to_the_two_companies(): void
    {
        $banjarbaru = $this->makeBanjarbaru();
        $other = Companies::create(['code' => 'TST', 'name' => 'Other Company', 'location' => 'X', 'is_active' => true]);

        $this->assertContains(PermissionTypeEnum::KELUARGA_INTI_SAKIT, PermissionTypeEnum::forGroup(PermissionGroupEnum::KHUSUS, $banjarbaru->id));
        $this->assertNotContains(PermissionTypeEnum::KELUARGA_INTI_SAKIT, PermissionTypeEnum::forGroup(PermissionGroupEnum::KHUSUS, $other->id));
        $this->assertNotContains(PermissionTypeEnum::KELUARGA_INTI_SAKIT, PermissionTypeEnum::forGroup(PermissionGroupEnum::KHUSUS));

        $otherUser = User::factory()->create(['company_id' => $other->id]);
        $this->actingAs($otherUser);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('permission_type', PermissionTypeEnum::KELUARGA_INTI_SAKIT->value)
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-12')
            ->call('submit')
            ->assertHasErrors(['permission_type']);
    }
}
