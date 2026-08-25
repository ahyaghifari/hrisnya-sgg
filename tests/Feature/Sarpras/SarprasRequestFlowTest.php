<?php

namespace Tests\Feature\Sarpras;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\PendingApprovalsCard;
use App\Livewire\Sarpras\SarprasRequestDetailPage;
use App\Livewire\Sarpras\SubmitSarprasRequestPage;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Enum\OrgStructurLevel;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Sarpras\SarprasRequest;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Simulasi end-to-end lewat komponen Livewire beneran (bukan cuma service layer, yang sudah
 * diverifikasi terpisah lewat script manual) - submit -> teruskan (Kabid) -> putuskan item
 * campuran (Direktur) -> selesaikan (Unit). Porting skenario yang sama dari script verifikasi
 * sesi service layer, kali ini lewat UI.
 */
class SarprasRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $unit;
    private User $kabid;
    private User $direktur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'SPR', 'name' => 'RS Sarpras Test', 'location' => 'Test', 'is_active' => true]);

        $this->unit = User::factory()->create(['company_id' => $this->company->id]);
        $this->kabid = User::factory()->create(['company_id' => $this->company->id]);
        $this->direktur = User::factory()->create(['company_id' => $this->company->id]);

        // `unit` di sarpras_requests sekarang diambil otomatis dari Divisi (OrgStructure level
        // 2) pemohon, bukan input bebas - butuh struktur organisasi beneran buat test submit.
        $department = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Pelayanan Medis', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $division = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'IGD', 'parent_id' => $department->id, 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        UserProfiles::create(['user_id' => $this->unit->id, 'org_structure_id' => $division->id]);

        Permission::firstOrCreate(['name' => CustomPermissionEnum::AJUKAN_SARANA_PRASARANA->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SARANA_PRASARANA->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Unit Sarpras Test', 'guard_name' => 'web']);
        $role->givePermissionTo([
            CustomPermissionEnum::AJUKAN_SARANA_PRASARANA->value,
            CustomPermissionEnum::SISTEM_SARANA_PRASARANA->value,
        ]);
        $this->unit->assignRole($role);

        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => ApprovalModuleEnum::SARPRAS_REQUEST->value,
            'name' => 'Alur Sarpras Test',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->kabid->id,
            'approval_mode' => 'any',
            'is_final' => false,
        ]);

        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 2,
            'approver_type' => 'user',
            'approver_ref_id' => $this->direktur->id,
            'approval_mode' => 'any',
            'is_final' => true,
        ]);
    }

    public function test_full_lifecycle_through_livewire_components(): void
    {
        Livewire::actingAs($this->unit)
            ->test(SubmitSarprasRequestPage::class)
            ->set('jenis', 'pengadaan')
            ->set('prioritas', 'urgent')
            ->set('perihal', 'Tes perihal')
            ->set('uraian', 'Tes uraian')
            ->set('items', [
                ['nama' => 'Bed pasien', 'spesifikasi' => null, 'jumlah' => 4, 'satuan' => 'unit', 'harga_satuan' => null],
                ['nama' => 'AC ruangan', 'spesifikasi' => null, 'jumlah' => 1, 'satuan' => 'unit', 'harga_satuan' => null],
            ])
            ->call('submit')
            ->assertRedirect();

        $request = SarprasRequest::firstOrFail();
        $this->assertSame('diajukan', $request->status);
        $this->assertCount(1, $request->signatures);

        Livewire::actingAs($this->kabid)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->assertSet('canTeruskan', true)
            ->set('catatanKabid', 'diteruskan')
            ->call('teruskan');

        $request->refresh();
        $this->assertSame('menunggu_direktur', $request->status);
        $this->assertCount(2, $request->signatures);

        $items = $request->items()->orderBy('id')->get();

        Livewire::actingAs($this->direktur)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->assertSet('canPutuskan', true)
            ->set("itemDecisions.{$items[0]->id}.keputusan", 'disetujui')
            ->set("itemDecisions.{$items[0]->id}.jumlah_disetujui", 2)
            ->call('putuskanItem', $items[0]->id);

        $request->refresh();
        $this->assertSame('diputuskan_sebagian', $request->status);

        Livewire::actingAs($this->direktur)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->set("itemDecisions.{$items[1]->id}.keputusan", 'ditolak')
            ->call('putuskanItem', $items[1]->id);

        $request->refresh();
        $this->assertSame('disetujui_sebagian', $request->status);
        $this->assertSame('approved', $request->approvalSnapshot->fresh()->status);
        $this->assertCount(3, $request->signatures);

        Livewire::actingAs($this->unit)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->assertSet('canSelesaikan', true)
            ->call('selesaikan');

        $this->assertSame('selesai', $request->fresh()->status);
    }

    public function test_stranger_cannot_open_detail_page(): void
    {
        $request = $this->makeSubmittedRequest();
        $stranger = User::factory()->create(['company_id' => $this->company->id]);

        Livewire::actingAs($stranger)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->assertForbidden();
    }

    public function test_item_locked_after_decided(): void
    {
        $request = $this->makeSubmittedRequest();

        Livewire::actingAs($this->kabid)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->call('teruskan');

        $item = $request->items()->first();

        Livewire::actingAs($this->direktur)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request->fresh()])
            ->set("itemDecisions.{$item->id}.keputusan", 'disetujui')
            ->set("itemDecisions.{$item->id}.jumlah_disetujui", $item->jumlah)
            ->call('putuskanItem', $item->id)
            ->set("itemDecisions.{$item->id}.keputusan", 'ditolak')
            ->call('putuskanItem', $item->id);

        $this->assertSame('disetujui', $item->fresh()->status, 'item yang sudah diputuskan harus tetap terkunci pada keputusan pertama');
    }

    public function test_route_accessible_via_http_with_permission(): void
    {
        $this->actingAs($this->unit)
            ->get(route('sarana-prasarana.ajukan'))
            ->assertOk();

        $this->actingAs($this->unit)
            ->get(route('sarana-prasarana'))
            ->assertOk();
    }

    public function test_pending_request_shows_up_in_dashboard_widget(): void
    {
        $request = $this->makeSubmittedRequest();

        // Sebelum diteruskan: baru Kabid yang lihat di antreannya (step 1), Direktur belum -
        // engine cuma nunjuk 1 activity pending, ke approver step SEKARANG.
        Livewire::actingAs($this->kabid)
            ->test(PendingApprovalsCard::class)
            ->assertSee('Sarana & Prasarana')
            ->assertSee($request->perihal);

        Livewire::actingAs($this->direktur)
            ->test(PendingApprovalsCard::class)
            ->assertDontSee($request->perihal);

        Livewire::actingAs($this->kabid)
            ->test(SarprasRequestDetailPage::class, ['sarprasRequest' => $request])
            ->call('teruskan');

        // Setelah diteruskan: kebalikannya - pindah ke antrean Direktur, hilang dari Kabid.
        Livewire::actingAs($this->direktur)
            ->test(PendingApprovalsCard::class)
            ->assertSee('Sarana & Prasarana')
            ->assertSee($request->perihal);

        Livewire::actingAs($this->kabid)
            ->test(PendingApprovalsCard::class)
            ->assertDontSee($request->perihal);
    }

    public function test_submit_rejected_without_division(): void
    {
        $noDivision = User::factory()->create(['company_id' => $this->company->id]);
        $noDivision->assignRole(Role::where('name', 'Unit Sarpras Test')->first());

        Livewire::actingAs($noDivision)
            ->test(SubmitSarprasRequestPage::class)
            ->assertSet('unitDisplay', null)
            ->set('perihal', 'Tes tanpa divisi')
            ->set('uraian', 'Tes uraian')
            ->set('items', [['nama' => 'Bed pasien', 'spesifikasi' => null, 'jumlah' => 1, 'satuan' => 'unit', 'harga_satuan' => null]])
            ->call('submit');

        $this->assertSame(0, SarprasRequest::count(), 'submit tanpa divisi tidak boleh membuat baris apa pun');
    }

    private function makeSubmittedRequest(): SarprasRequest
    {
        Livewire::actingAs($this->unit)
            ->test(SubmitSarprasRequestPage::class)
            ->set('perihal', 'Tes perihal')
            ->set('uraian', 'Tes uraian')
            ->set('items', [['nama' => 'Bed pasien', 'spesifikasi' => null, 'jumlah' => 2, 'satuan' => 'unit', 'harga_satuan' => null]])
            ->call('submit');

        return SarprasRequest::firstOrFail();
    }
}
