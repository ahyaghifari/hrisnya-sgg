<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Livewire\EmployeeStatistics;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Permission;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeStatisticsTest extends TestCase
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
    // EmployeeStatistics::getShowExtraTabsProperty(). Test yang mengasumsikan tab itu
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'))
            ->assertForbidden();
    }

    public function test_owner_can_upload_own_photo_via_data_diri(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'))
            ->assertHasNoErrors();

        $this->assertNotNull($staff->fresh()->profilePhotoPath(), 'Foto tersimpan di users.profile_picture.');
    }

    /** Riwayat Pengajuan tab Kehadiran hanya menampilkan yang approved - filter status sudah dihapus dari UI. */
    public function test_only_approved_requests_are_listed_and_status_filter_is_gone(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);
        $this->assignEmployeeType($staff, $company);

        $this->createLeaveRequest($staff, 'approved');
        $this->createLeaveRequest($staff, 'in_review');
        $this->createLeaveRequest($staff, 'rejected');

        $this->actingAs($manager);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSet('stats', ['leave' => 1, 'permission' => 0, 'overtime' => 0])
            ->assertDontSee('Status Pengajuan')
            ->assertDontSee('Export Excel');
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSet('stats', ['leave' => 0, 'permission' => 0, 'overtime' => 0]);
    }

    /** Kartu "Poin Anda" hanya tampil kalau Merit Point diaktifkan di Pengaturan Pembinaan (default: mati). */
    public function test_merit_point_card_hidden_unless_enabled_in_pembinaan_settings(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        // Belum pernah diatur = mati: kartu, panel riwayat, dan aksinya tertutup.
        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertDontSee('Poin Anda')
            ->call('openMeritHistoryModal')
            ->assertForbidden();

        // Diaktifkan untuk company ini: kartu tampil dan panel riwayat bisa dibuka.
        \App\Models\Setting::create([
            'company_id' => $company->id,
            'module' => 'kepegawaian',
            'key' => \App\Enum\Kepegawaian\KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
            'type' => 'boolean',
            'value' => 'true',
            'label' => 'Aktifkan Merit Point',
        ]);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('Poin Anda')
            ->call('openMeritHistoryModal')
            ->assertSet('showMeritHistoryModal', true);
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

        Livewire::test(EmployeeStatistics::class, ['record' => $other])
            ->assertForbidden();
    }

    private function makePayroll(User $user, bool $isPaid, array $periodOverrides = []): \App\Models\Payroll\Payroll
    {
        $period = \App\Models\Payroll\PayrollPeriod::create(array_merge([
            'company_id' => $user->company_id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ], $periodOverrides));

        return \App\Models\Payroll\Payroll::create([
            'user_id' => $user->id, 'employee_id' => $user->employee->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5000000,
            'total_deductions' => 500000,
            'net_salary' => 4500000,
            'is_paid' => $isPaid,
        ]);
    }

    /**
     * Regresi: Livewire::test(EmployeeStatistics::class) TANPA param 'record' balik
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

        Livewire::test(EmployeeStatistics::class, ['record' => $user])
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertDontSee('Penggajian');
    }

    public function test_extra_tabs_hidden_when_employee_type_not_set(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(EmployeeStatistics::class, ['record' => $user])
            ->assertSee('Presensi')
            ->assertDontSee('Presensi & Absensi')
            ->assertDontSee('Riwayat Pengajuan')
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

        Livewire::test(EmployeeStatistics::class, ['record' => $user])
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->assertForbidden();
    }

    public function test_owner_can_add_own_attachments_with_fixed_types(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('ktpFile', \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'))
            ->assertHasNoErrors()
            ->set('kkFile', \Illuminate\Http\UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf'))
            ->assertHasNoErrors();

        $labels = \App\Models\User\UserProfileDocument::where('user_id', $staff->id)->pluck('label');
        $this->assertEqualsCanonicalizing(['KTP', 'KK'], $labels->all());
    }

    /** Upload ulang tipe yang sama MENGGANTI baris lama, bukan menambah baris baru (instruksi user 2026-10-05). */
    public function test_reuploading_same_type_replaces_old_row_and_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        $component = Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('ktpFile', \Illuminate\Http\UploadedFile::fake()->image('ktp-lama.jpg'))
            ->assertHasNoErrors();

        $oldPath = \App\Models\User\UserProfileDocument::where('user_id', $staff->id)->where('label', 'KTP')->firstOrFail()->file_path;

        $component
            ->set('ktpFile', \Illuminate\Http\UploadedFile::fake()->image('ktp-baru.jpg'))
            ->assertHasNoErrors();

        $rows = \App\Models\User\UserProfileDocument::where('user_id', $staff->id)->where('label', 'KTP')->get();
        $this->assertCount(1, $rows);
        $this->assertNotSame($oldPath, $rows->first()->file_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($oldPath);
    }

    /** STR/SIP/RKK cuma buat nakes (JobPositions::is_nakes) - ditolak di server, bukan cuma disembunyikan di UI. */
    public function test_non_nakes_cannot_add_str_attachment(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'is_nakes' => false]);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('strFile', \Illuminate\Http\UploadedFile::fake()->create('str.pdf', 100, 'application/pdf'))
            ->assertForbidden();
    }

    public function test_nakes_can_add_str_attachment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $nakesPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'is_nakes' => true]);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $nakesPosition);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('strFile', \Illuminate\Http\UploadedFile::fake()->create('str.pdf', 100, 'application/pdf'))
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_profile_documents', ['user_id' => $staff->id, 'label' => 'STR']);
    }

    public function test_uploaded_attachment_is_encrypted_at_rest_and_served_decrypted(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('ktpFile', \Illuminate\Http\UploadedFile::fake()->createWithContent('ktp.pdf', '%PDF-1.4 rahasia'))
            ->assertHasNoErrors();

        $doc = UserProfileDocument::where('user_id', $staff->id)->firstOrFail();

        // Di disk: ciphertext, bukan isi asli.
        $onDisk = \Illuminate\Support\Facades\Storage::disk('local')->get($doc->file_path);
        $this->assertStringNotContainsString('rahasia', $onDisk);
        $this->assertSame('%PDF-1.4 rahasia', \Illuminate\Support\Facades\Crypt::decryptString($onDisk));

        // Lewat route: pemilik dapat isi terdekripsi; orang lain 403.
        $this->get(route('profil-saya.lampiran.show', $doc))
            ->assertOk()
            ->assertSee('%PDF-1.4 rahasia');

        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        $this->get(route('profil-saya.lampiran.show', $doc))->assertForbidden();
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->set('ktpFile', \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'))
            ->assertForbidden();
    }

    /**
     * Beda dari sekadar bisa buka Data Diri staff (manager biasa bisa, lihat
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

        $component = Livewire::test(EmployeeStatistics::class, ['record' => $staff]);

        $component->assertDontSee('Dokumen');
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('Dokumen');
    }

    public function test_owner_can_always_see_own_attachments_without_permission(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('Dokumen');
    }

    /**
     * Email AKUN (`users.email`, dipakai login) tidak boleh diubah dari self-service Data Diri
     * (instruksi user 2026-10-04): tidak ada inputnya, dan properti `email` tidak ada sama sekali
     * di komponen sehingga request yang di-craft pun tidak bisa mengubahnya.
     */
    public function test_owner_cannot_change_account_email_from_data_diri(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'email' => 'lama@syifaglobal.com']);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        $component = Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->assertDontSeeHtml('wire:model="email"');

        $this->assertArrayNotHasKey('email', $component->instance()->all());

        $component->set('nickname', 'Budi')->call('saveProfil')->assertHasNoErrors();

        $this->assertSame('lama@syifaglobal.com', $staff->fresh()->email);
        $this->assertAuthenticated();
    }

    public function test_owner_saving_profil_without_changing_email_stays_logged_in(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'email' => 'tetap@syifaglobal.com']);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('nickname', 'Budi')
            ->call('saveProfil')
            ->assertHasNoErrors()
            ->assertSet('editingProfil', false);

        $this->assertSame('tetap@syifaglobal.com', $staff->fresh()->email);
        $this->assertAuthenticated();
    }

    public function test_owner_can_see_own_change_history(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->call('startEditProfil')
            ->set('nickname', 'Budi')
            ->call('saveProfil');

        // Riwayat perubahan data = panel geser dari tombol di tab Data Diri (bukan tab lagi, 2026-09-26).
        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('Riwayat Perubahan')
            ->assertDontSee('Nama Panggilan')
            ->call('openAuditHistoryPanel')
            ->assertSee('Riwayat Perubahan Data')
            ->assertSee('Nama Panggilan')
            ->assertSee('Budi')
            ->call('closeAuditHistoryPanel')
            ->assertDontSee('Riwayat Perubahan Data');
    }

    /**
     * Beda dari sekadar bisa buka Data Diri staff — atasan biasa (bukan HC, bukan yang bisa
     * lihat lintas company) TIDAK boleh lihat tab "Riwayat Perubahan", meski dia bisa buka tab
     * lain seperti Profil/Presensi staff-nya (lihat canManage() vs getCanViewAuditHistoryProperty()).
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertDontSee('Riwayat Perubahan')
            ->call('openAuditHistoryPanel')
            ->assertForbidden();
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('Riwayat Perubahan')
            ->call('openAuditHistoryPanel')
            ->assertSee('Riwayat Perubahan Data');
    }

    // ── Tab Pembinaan (Coaching saja — Counseling tidak ditampilkan) ──

    private function grantPembinaan(User $user): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'guard_name' => 'web']);
        $user->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);
    }

    public function test_owner_does_not_see_pembinaan_tab_on_own_profile(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $coach = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        \App\Models\Coaching\Coaching::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'coach_by' => $coach->id,
            'title' => 'Coaching Kedisiplinan',
            'status' => 'in_progress',
        ]);

        $this->actingAs($staff);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertDontSee('ico/pembinaan.png')
            ->assertDontSee('Coaching Kedisiplinan');
    }

    public function test_manager_without_pembinaan_permission_sees_tab_for_subordinate(): void
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

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('ico/pembinaan.png');
    }

    public function test_pembinaan_permission_holder_sees_tab_with_coaching_and_no_counseling(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        // Pemegang SISTEM_PEMBINAAN_KARYAWAN yang juga bisa buka profil (di sini: atasan
        // langsung). canManage() tidak dilebarkan buat pembinaan-only — itu keputusan terpisah.
        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);
        $this->grantPembinaan($manager);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);
        $coach = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id]);

        \App\Models\Coaching\Coaching::create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'coach_by' => $coach->id,
            'title' => 'Coaching Target Kerja',
            'status' => 'completed',
        ]);

        // Counseling ADA di DB tapi TAB TIDAK menampilkannya (instruksi user).
        \App\Models\Counseling\Counseling::create([
            'user_id' => $staff->id,
            'counselor_id' => $counselor->id,
            'issue' => 'RAHASIA keluhan pribadi',
            'status' => 'pending',
            'is_secret' => true,
        ]);

        $this->actingAs($manager);

        Livewire::test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSee('ico/pembinaan.png')
            ->assertSee('Coaching Target Kerja')
            ->assertDontSee('Counseling')
            ->assertDontSee('RAHASIA keluhan pribadi');
    }

    // Hitung ulang presensi di tab Kehadiran: pemilik akun atau pemegang izin lihat rekap
    // presensi (view_report_data_absensi) di company yang sama - atasan biasa tidak (2026-10-02).
    public function test_resync_button_is_open_to_rekap_viewers_and_owner_only(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        Livewire::actingAs($manager)->test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSet('canResync', false);

        Livewire::actingAs($staff)->test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSet('canResync', true);

        Permission::firstOrCreate(['name' => CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value, 'guard_name' => 'web']);
        $manager->givePermissionTo(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value);

        Livewire::actingAs($manager->fresh())->test(EmployeeStatistics::class, ['record' => $staff])
            ->assertSet('canResync', true)
            ->call('syncMyPeriod')
            ->assertHasNoErrors();
    }
}
