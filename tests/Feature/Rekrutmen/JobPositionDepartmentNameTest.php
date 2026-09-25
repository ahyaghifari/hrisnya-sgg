<?php

namespace Tests\Feature\Rekrutmen;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keputusan user 2026-09-24: yang dipakai langsung nama DEPARTEMEN jabatannya, bukan divisinya.
 * Nilainya mengisi job_openings.division_name di DB recruitment = token {divisi} pada naskah
 * Persetujuan Onboarding, jadi harus ketemu berapa pun dalamnya jabatan itu duduk.
 *
 * Struktur di master: SUB_DIVISION -> DIVISION -> DEPARTMENT (54 divisi semuanya berinduk
 * departemen, 20 sub divisi semuanya berinduk divisi).
 */
class JobPositionDepartmentNameTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
    }

    private function org(string $name, string $level, ?string $parentId = null): OrgStructure
    {
        return OrgStructure::create([
            'company_id' => $this->company->id, 'name' => $name, 'level' => $level, 'parent_id' => $parentId,
        ]);
    }

    private function position(string $name, ?string $orgId): JobPositions
    {
        return JobPositions::create([
            'company_id' => $this->company->id, 'name' => $name, 'org_structure_id' => $orgId, 'status' => 'approved',
        ]);
    }

    /** Dari sub divisi, naik dua tingkat: Programmer -> IT -> HCGA. */
    public function test_a_sub_division_climbs_all_the_way_to_its_department(): void
    {
        $department = $this->org('HCGA', 'DEPARTMENT');
        $division = $this->org('IT', 'DIVISION', $department->id);
        $position = $this->position('IT Programmer Staff', $this->org('Programmer', 'SUB_DIVISION', $division->id)->id);

        $this->assertSame('HCGA', $position->department_name);
    }

    /** Dari divisi, naik satu tingkat - bukan nama divisinya yang dipakai. */
    public function test_a_division_resolves_to_its_department_not_itself(): void
    {
        $department = $this->org('HCGA', 'DEPARTMENT');
        $position = $this->position('Manager IT', $this->org('IT', 'DIVISION', $department->id)->id);

        $this->assertSame('HCGA', $position->department_name);
        $this->assertSame('IT', $position->division?->name, 'Divisinya tetap kebaca, cuma tidak dipakai.');
    }

    /** Pimpinan puncak departemen duduk langsung di departemennya. */
    public function test_a_position_sitting_directly_on_a_department_uses_that_department(): void
    {
        $position = $this->position('Finance Manager', $this->org('Finance', 'DEPARTMENT')->id);

        $this->assertSame('Finance', $position->department_name);
    }

    /** Rantai putus sebelum ketemu departemen: pakai struktur terakhir yang kebaca, jangan kosong. */
    public function test_a_broken_chain_falls_back_to_the_deepest_readable_structure(): void
    {
        $position = $this->position('Perawat', $this->org('Keperawatan', 'DIVISION')->id);

        $this->assertSame('Keperawatan', $position->department_name);
    }

    /**
     * Di atas semua departemen (mis. Presiden Director) tidak ada struktur yang bisa dipakai. Praktis
     * tidak pernah direkrut lewat portal, tapi kalau toh ada, tokennya jatuh ke tanda hubung.
     */
    public function test_a_position_without_any_org_structure_stays_null(): void
    {
        $this->assertNull($this->position('Presiden Director', null)->department_name);
    }
}
