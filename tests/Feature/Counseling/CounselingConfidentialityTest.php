<?php

namespace Tests\Feature\Counseling;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Counseling\CounselingPage;
use App\Models\Counseling\Counseling;
use App\Models\Master\Companies;
use App\Models\User;
use App\Notifications\CounselingReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Redesign 2026-08-17: karyawan menunjuk sendiri Konselor + pilih is_secret saat submit Konsul.
 * Identity match (user_id/counselor_id) SELALU boleh baca isi & buka detail. Selain itu, siapa
 * pun yang punya akses modul (SISTEM_PEMBINAAN_KARYAWAN) boleh BUKA record (list/detail selalu
 * tampil), tapi isi issue/response/solution cuma kebaca kalau is_secret=false — kalau tidak,
 * di-redact. Shield permission ViewAny/Update:Counseling SENDIRIAN (tanpa SISTEM_COACHING_
 * COUNSELING) TIDAK cukup buat buka apa pun (beda modul gate).
 */
class CounselingConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    private function makeModuleAccessUser(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Akses Modul Coaching Counseling', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeShieldOnlyPermissionUser(Companies $company): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:Counseling', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Counseling', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Punya Shield Permission Counseling Saja', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:Counseling', 'Update:Counseling']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_employee_can_pick_own_counselor_when_submitting(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->call('openConsultModal')
            ->set('issue', 'Ada masalah dengan rekan kerja.')
            ->set('counselorId', $counselor->id)
            ->call('submitConsult');

        $record = Counseling::where('user_id', $employee->id)->where('counselor_id', $counselor->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('Ada masalah dengan rekan kerja.', $record->issue);
        $this->assertTrue($record->is_secret);

        // issue dienkripsi APP_KEY - baris mentah di DB TIDAK boleh plaintext.
        $raw = \Illuminate\Support\Facades\DB::table('counselings')->where('id', $record->id)->value('issue');
        $this->assertNotSame('Ada masalah dengan rekan kerja.', $raw);
    }

    public function test_employee_can_submit_non_secret_consult(): void
    {
        $company = Companies::create(['code' => 'TST2', 'name' => 'Test Company 2', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->call('openConsultModal')
            ->set('issue', 'Keluhan biasa saja.')
            ->set('counselorId', $counselor->id)
            ->set('isSecret', false)
            ->call('submitConsult');

        $record = Counseling::where('user_id', $employee->id)->first();
        $this->assertFalse($record->is_secret);
    }

    public function test_counselor_gets_notified_when_consulted(): void
    {
        Notification::fake();
        $company = Companies::create(['code' => 'TST3', 'name' => 'Test Company 3', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->set('issue', 'Ada masalah dengan rekan kerja.')
            ->set('counselorId', $counselor->id)
            ->call('submitConsult');

        Notification::assertSentTo($counselor, CounselingReceived::class);
    }

    public function test_employee_cannot_submit_consult_to_self_even_via_tampered_request(): void
    {
        $company = Companies::create(['code' => 'TST4', 'name' => 'Test Company 4', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->set('issue', 'Coba konsul ke diri sendiri.')
            ->set('counselorId', $employee->id)
            ->call('submitConsult')
            ->assertHasErrors(['counselorId']);

        $this->assertSame(0, Counseling::where('user_id', $employee->id)->where('counselor_id', $employee->id)->count());
    }

    public function test_assigned_counselor_can_view_and_respond(): void
    {
        $company = Companies::create(['code' => 'TST5', 'name' => 'Test Company 5', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Keluhan rahasia.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($counselor)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertSet('response', '')
            ->set('response', 'Sudah ditindaklanjuti.')
            ->set('solution', 'Mediasi dengan rekan kerja.')
            ->call('submitResponse')
            // Regresi: getCanRespondProperty() sempat ke-memoize (true) sebelum status berubah,
            // jadi form "Kirim Balasan" masih nongol di render abis submit walau status sudah
            // DONE di DB - pastikan form itu hilang & digantikan tampilan "Balasan".
            ->assertDontSee('Kirim Balasan')
            ->assertSee('Sudah ditindaklanjuti.');

        $fresh = $record->fresh();
        $this->assertSame('Sudah ditindaklanjuti.', $fresh->response);
        $this->assertSame('done', $fresh->status);
    }

    public function test_shield_permission_alone_without_module_access_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST6', 'name' => 'Test Company 6', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $bystander = $this->makeShieldOnlyPermissionUser($company);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Keluhan rahasia.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($bystander)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertForbidden();
    }

    public function test_module_access_user_can_open_secret_record_but_content_is_redacted(): void
    {
        $company = Companies::create(['code' => 'TST7', 'name' => 'Test Company 7', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $moduleUser = $this->makeModuleAccessUser($company);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Isi keluhan rahasia banget.',
            'status' => 'pending',
            'is_secret' => true,
        ]);

        Livewire::actingAs($moduleUser)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertOk()
            ->assertSet('isContentVisible', false)
            ->assertDontSee('Isi keluhan rahasia banget.')
            ->assertSet('response', ''); // tidak ke-prefill dari record punya orang lain
    }

    public function test_module_access_user_can_read_non_secret_record_content(): void
    {
        $company = Companies::create(['code' => 'TST8', 'name' => 'Test Company 8', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $moduleUser = $this->makeModuleAccessUser($company);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Isi keluhan yang tidak rahasia.',
            'status' => 'pending',
            'is_secret' => false,
        ]);

        Livewire::actingAs($moduleUser)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertOk()
            ->assertSet('isContentVisible', true)
            ->assertSee('Isi keluhan yang tidak rahasia.');
    }

    public function test_manage_page_lists_company_wide_with_content_redaction(): void
    {
        $company = Companies::create(['code' => 'TST9', 'name' => 'Test Company 9', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = $this->makeModuleAccessUser($company);
        $otherCounselor = User::factory()->create(['company_id' => $company->id]);

        $ownRecord = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Punya konselor ini.',
            'status' => 'pending',
        ]);
        $othersSecretRecord = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $otherCounselor->id,
            'issue' => 'Punya konselor lain, rahasia.',
            'status' => 'pending',
            'is_secret' => true,
        ]);

        $counselings = Livewire::actingAs($counselor)
            ->test(CounselingManagePage::class)
            ->instance()
            ->counselings;

        // Company-wide sekarang - dua-duanya muncul, bukan cuma punya konselor sendiri.
        $this->assertCount(2, $counselings);
        $ids = $counselings->pluck('id')->all();
        $this->assertContains($ownRecord->id, $ids);
        $this->assertContains($othersSecretRecord->id, $ids);
    }

    public function test_manage_page_requires_module_permission(): void
    {
        $company = Companies::create(['code' => 'TST10', 'name' => 'Test Company 10', 'location' => 'Test', 'is_active' => true]);
        $userWithoutAccess = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($userWithoutAccess)
            ->test(CounselingManagePage::class)
            ->assertForbidden();
    }

    public function test_inbox_button_shown_only_for_non_supervisor_counselor_with_pending_consult(): void
    {
        $company = Companies::create(['code' => 'TST11', 'name' => 'Test Company 11', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselorWithoutSubordinates = User::factory()->create(['company_id' => $company->id]);

        Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselorWithoutSubordinates->id,
            'issue' => 'Keluhan.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($counselorWithoutSubordinates)
            ->test(CounselingPage::class)
            ->assertSee('Konsul ke Saya');

        // Karyawan yang belum pernah ditunjuk jadi konselor sama sekali tidak lihat tombolnya.
        $neverCounselor = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($neverCounselor)
            ->test(CounselingPage::class)
            ->assertDontSee('Konsul ke Saya');
    }

    public function test_submitter_can_view_own_consult_detail_read_only(): void
    {
        $company = Companies::create(['code' => 'TST12', 'name' => 'Test Company 12', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Keluhan saya sendiri.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($employee)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertSee('Keluhan saya sendiri.')
            ->assertSet('isCounselor', false)
            ->assertSet('canRespond', false);
    }

    /**
     * Regresi: pagination di halaman ini pakai wire:navigate (full page-visit), yang bikin
     * komponen di-mount ULANG dari URL — tanpa #[Url] filter `status` hilang begitu pindah
     * halaman. Test GET langsung (bukan Livewire::test) biar simulasi mount dari URL yang
     * sebenarnya kejadian saat wire:navigate.
     */
    public function test_status_filter_is_read_from_url_on_page_load(): void
    {
        $company = Companies::create(['code' => 'TST13', 'name' => 'Test Company 13', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = $this->makeModuleAccessUser($company);

        for ($i = 1; $i <= 15; $i++) {
            Counseling::create([
                'user_id' => $employee->id,
                'counselor_id' => $counselor->id,
                'issue' => "Keluhan selesai {$i}",
                'status' => 'done',
                'is_secret' => false,
            ]);
        }
        Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Keluhan pending',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($counselor)->get(route('counseling.kelola', ['status' => 'done', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('Keluhan selesai 11');
        $response->assertDontSee('Keluhan pending');
    }

    public function test_inbox_button_hidden_when_all_assigned_consults_already_done(): void
    {
        $company = Companies::create(['code' => 'TST14', 'name' => 'Test Company 14', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);

        Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Sudah selesai ditanggapi.',
            'status' => 'done',
        ]);

        Livewire::actingAs($counselor)
            ->test(CounselingPage::class)
            ->assertDontSee('Konsul ke Saya');
    }
}
