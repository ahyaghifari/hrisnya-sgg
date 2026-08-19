<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Livewire\EmployeeAttendanceStatistics;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Permission;
use App\Models\User;
use App\Models\User\UserDocuments;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeAttendanceStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function assignJobPosition(User $user, JobPositions $jobPosition): void
    {
        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    // Tab Absensi/Riwayat Pengajuan/Dokumen/Penggajian di Data Diri cuma tampil kalau tipe
    // karyawan user itu SUDAH diisi (apa pun kategorinya) - lihat
    // EmployeeAttendanceStatistics::getShowExtraTabsProperty(). Test yang mengasumsikan tab itu
    // tampil harus assign tipe karyawan eksplisit di sini, user factory mentah tidak punya.
    private function assignEmployeeType(User $user, Companies $company): void
    {
        $employeeType = EmployeeTypes::create([
            'company_id' => $company->id,
            'name' => 'Tetap',
            'category' => EmployeeTypeCategory::TETAP->value,
        ]);

        UserProfiles::updateOrCreate(['user_id' => $user->id], ['employee_type_id' => $employeeType->id]);
    }

    private function createLeaveRequest(User $user, string $status = 'approved'): AttendanceRequests
    {
        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => $status,
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Tahunan',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
        ]);

        return $request;
    }

    public function test_manager_can_view_subordinate_statistics(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'approved');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSet('status', 'approved')
            ->assertSee($staff->name)
            ->assertSee('Cuti Tahunan');
    }

    /**
     * Regresi keamanan: HandlesProfilePhotoUpload::updatedPhoto() harus abort 403 kalau
     * $record bukan diri sendiri, meski komponennya sama dan bisa dibuka manager buat lihat
     * data staf-nya (test_manager_can_view_subordinate_statistics di atas) - kalau ini tidak
     * dicek, manager yang buka Data Diri staf bisa nge-upload foto atas nama staf tsb.
     */
    public function test_manager_cannot_upload_photo_for_subordinate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'))
            ->assertForbidden();
    }

    public function test_owner_can_upload_own_photo_via_data_diri(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'))
            ->assertHasNoErrors();

        $this->assertNotNull($staff->fresh()->profile->fresh()->profile_picture[0] ?? null);
    }

    public function test_status_filter_excludes_non_matching_requests(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'in_review');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSet('stats', ['leave' => 0, 'permission' => 0, 'overtime' => 0]);
    }

    public function test_stranger_cannot_view_unrelated_employee_statistics(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1]);

        $stranger = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($stranger, $staffPosition);

        $other = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($other, $staffPosition);

        $this->actingAs($stranger);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $other])
            ->assertForbidden();
    }

    public function test_documents_tab_lists_only_the_records_own_documents(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $otherStaff = User::factory()->create(['company_id' => $company->id]);

        UserDocuments::create([
            'user_id' => $staff->id,
            'document_type' => 'work_certificate',
            'user_name' => $staff->name,
            'document_number' => 'SKK/001/2026',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $staff->id . '/skk.pdf',
        ]);

        UserDocuments::create([
            'user_id' => $otherStaff->id,
            'document_type' => 'work_certificate',
            'user_name' => $otherStaff->name,
            'document_number' => 'SKK/999/2026',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $otherStaff->id . '/skk.pdf',
        ]);

        $this->assignEmployeeType($staff, $company);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSee('SKK/001/2026')
            ->assertDontSee('SKK/999/2026');
    }

    public function test_export_returns_download_response(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'approved');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('export')
            ->assertFileDownloaded();
    }

    private function makePayroll(User $user, bool $isPaid, array $periodOverrides = []): \App\Models\Payroll\Payroll
    {
        $period = \App\Models\Payroll\PayrollPeriod::create(array_merge([
            'company_id' => $user->company_id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ], $periodOverrides));

        return \App\Models\Payroll\Payroll::create([
            'user_id' => $user->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5000000,
            'total_deductions' => 500000,
            'net_salary' => 4500000,
            'is_paid' => $isPaid,
        ]);
    }

    /**
     * Regresi: Livewire::test(EmployeeAttendanceStatistics::class) TANPA param 'record' balik
     * 403 (quirk khusus test harness Livewire, sudah ditemukan sebelumnya di komponen lain
     * sesi ini) — tapi rute HTTP /profil-saya yang beneran (tanpa segment {record}) harus tetap
     * jalan normal di request asli, karena mount() dipanggil tanpa argumen sama sekali, bukan
     * lewat resolusi implicit-binding yang bikin quirk itu muncul di test harness.
     */
    public function test_profil_saya_route_loads_via_real_http_request(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('profil-saya'))
            ->assertOk()
            ->assertSee('Profil Saya');
    }

    /** Path lama /data-diri (sebelum rename 2026-08-19) tetap bisa dibuka - redirect 301,
     *  bukan 404, biar bookmark/notifikasi lama tidak mati. */
    public function test_legacy_data_diri_path_redirects_to_profil_saya(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get('/data-diri')
            ->assertRedirect('/profil-saya');
    }

    public function test_payroll_tab_shows_only_paid_history_for_self(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $paid = $this->makePayroll($user, true);
        $this->makePayroll($user, false);

        $this->assignEmployeeType($user, $company);

        $this->actingAs($user);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $user])
            ->assertSee('Penggajian')
            ->assertSee('Rp4.500.000')
            ->assertSee(route('profil-saya.penggajian.show', $paid->id), false);
    }

    public function test_payroll_tab_hidden_when_viewing_subordinate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->makePayroll($staff, true);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertDontSee('Penggajian');
    }

    public function test_extra_tabs_hidden_when_employee_type_not_set(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $user])
            ->assertSee('Presensi')
            ->assertDontSee('Presensi & Absensi')
            ->assertDontSee('Riwayat Pengajuan')
            ->assertDontSee('Dokumen')
            ->assertDontSee('Penggajian');
    }

    #[DataProvider('employeeTypeCategoryProvider')]
    public function test_extra_tabs_visible_for_any_employee_type_category(EmployeeTypeCategory $category): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $employeeType = EmployeeTypes::create([
            'company_id' => $company->id,
            'name' => $category->getLabel(),
            'category' => $category->value,
        ]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);

        $this->actingAs($user);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $user])
            ->assertSee('Presensi & Absensi')
            ->assertSee('Riwayat Pengajuan')
            ->assertSee('Dokumen');
    }

    public static function employeeTypeCategoryProvider(): array
    {
        return [
            'tetap' => [EmployeeTypeCategory::TETAP],
            'kontrak' => [EmployeeTypeCategory::KONTRAK],
            'orientasi' => [EmployeeTypeCategory::ORIENTASI],
        ];
    }

    public function test_owner_can_edit_own_profil_but_not_bank_account(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create([
            'user_id' => $staff->id,
            'no_nik' => 'nik-lama',
            'bank_name' => 'Bank Lama',
            'bank_account_number' => '111111',
        ]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('nickname', 'Budi')
            ->set('no_nik', 'nik-baru')
            ->set('phone', '0812xxxx')
            ->call('saveProfil')
            ->assertHasNoErrors();

        $profile = $staff->fresh()->profile->fresh();
        $this->assertSame('Budi', $profile->nickname);
        $this->assertSame('nik-baru', $profile->no_nik);
        $this->assertSame('0812xxxx', $profile->phone);
        // Rekening bank tetap tidak berubah karena tidak dikirim ke saveProfil() sama sekali.
        $this->assertSame('111111', $profile->bank_account_number);
    }

    public function test_manager_cannot_edit_subordinate_profil(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->assertForbidden();
    }

    public function test_owner_can_add_own_attachments_with_free_label(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->set('newDocumentLabel', 'KTP')
            ->set('newDocumentFile', \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'))
            ->call('addProfileDocument')
            ->assertHasNoErrors()
            ->set('newDocumentLabel', 'Ijazah')
            ->set('newDocumentFile', \Illuminate\Http\UploadedFile::fake()->create('ijazah.pdf', 100, 'application/pdf'))
            ->call('addProfileDocument')
            ->assertHasNoErrors();

        $labels = \App\Models\User\UserProfileDocument::where('user_id', $staff->id)->pluck('label');
        $this->assertEqualsCanonicalizing(['KTP', 'Ijazah'], $labels->all());
    }

    public function test_owner_can_delete_own_attachment(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $doc = \App\Models\User\UserProfileDocument::create([
            'user_id' => $staff->id,
            'label' => 'KTP',
            'file_path' => 'profile-documents/' . $staff->id . '/ktp.jpg',
        ]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('deleteProfileDocument', $doc->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($doc);
    }

    public function test_manager_cannot_add_attachment_for_subordinate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->set('newDocumentLabel', 'KTP')
            ->set('newDocumentFile', \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'))
            ->call('addProfileDocument')
            ->assertForbidden();
    }

    /**
     * Beda dari sekadar bisa buka Data Diri bawahan (manager biasa bisa, lihat
     * test_manager_can_view_subordinate_statistics) — kartu "Lampiran" tetap disembunyikan
     * kecuali manager itu punya permission lihat_lampiran_identitas_karyawan.
     */
    public function test_manager_without_permission_cannot_see_subordinate_attachments(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        UserProfileDocument::create([
            'user_id' => $staff->id,
            'label' => 'KTP',
            'file_path' => 'profile-documents/' . $staff->id . '/ktp.jpg',
        ]);

        $this->actingAs($manager);

        $component = Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff]);

        $component->assertDontSee('Lampiran');
        $this->assertFalse($component->get('canViewProfileDocuments'));
    }

    public function test_manager_with_permission_can_see_subordinate_attachments(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value, 'guard_name' => 'web']);
        $manager->givePermissionTo(CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        UserProfileDocument::create([
            'user_id' => $staff->id,
            'label' => 'KTP',
            'file_path' => 'profile-documents/' . $staff->id . '/ktp.jpg',
        ]);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSee('Lampiran');
    }

    public function test_owner_can_always_see_own_attachments_without_permission(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSee('Lampiran');
    }

    /**
     * Email BUKAN kolom di UserProfiles (dia `users.email`, dipakai login) — ganti email harus
     * update $record->email langsung DAN force-logout supaya session lama tidak menggantung
     * terikat ke identitas lama (lihat komentar saveProfil()).
     */
    public function test_owner_changing_email_is_logged_out_and_redirected_to_login(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'email' => 'lama@syifaglobal.com']);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('email', 'baru@syifaglobal.com')
            ->call('saveProfil')
            ->assertRedirect(route('login'));

        $this->assertSame('baru@syifaglobal.com', $staff->fresh()->email);
        $this->assertGuest();
    }

    public function test_owner_saving_profil_without_changing_email_stays_logged_in(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'email' => 'tetap@syifaglobal.com']);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('nickname', 'Budi')
            ->call('saveProfil')
            ->assertHasNoErrors()
            ->assertSet('editingProfil', false);

        $this->assertSame('tetap@syifaglobal.com', $staff->fresh()->email);
        $this->assertAuthenticated();
    }

    public function test_owner_cannot_change_email_to_one_already_taken(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'email' => 'saya@syifaglobal.com']);
        User::factory()->create(['company_id' => $company->id, 'email' => 'sudah-dipakai@syifaglobal.com']);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('email', 'sudah-dipakai@syifaglobal.com')
            ->call('saveProfil')
            ->assertHasErrors(['email']);

        $this->assertSame('saya@syifaglobal.com', $staff->fresh()->email);
    }

    public function test_owner_can_see_own_change_history(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('nickname', 'Budi')
            ->call('saveProfil');

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSee('Riwayat Perubahan')
            ->assertSee('Nama Panggilan')
            ->assertSee('Budi');
    }

    /**
     * Beda dari sekadar bisa buka Data Diri bawahan — atasan biasa (bukan HC, bukan yang bisa
     * lihat lintas company) TIDAK boleh lihat tab "Riwayat Perubahan", meski dia bisa buka tab
     * lain seperti Profil/Presensi bawahannya (lihat canManage() vs getCanViewAuditHistoryProperty()).
     */
    public function test_plain_manager_cannot_see_subordinate_change_history(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertDontSee('Riwayat Perubahan');
    }

    public function test_hc_with_sistem_kepegawaian_can_see_change_history_in_same_company(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $hc = User::factory()->create(['company_id' => $company->id]);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        $hc->givePermissionTo(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value);

        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($hc);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSee('Riwayat Perubahan');
    }
}
