<?php

namespace App\Http\Controllers\Public;

use App\Enum\OrgStructurLevel;
use App\Http\Controllers\Controller;
use App\Models\Master\OrgStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman + API publik (tanpa login) daftar Departemen/Divisi/Sub Divisi + kode integrasinya.
 * Dipakai sistem lain (bukan HRIS) buat cocokkan unit organisasi mereka ke unit yang sama di
 * sini — lihat packages/syifa/keycloak-sso/README.md. Read-only, tanpa data sensitif
 * (cuma nama+kode+hierarki), jadi aman publik.
 */
class OrgUnitsController extends Controller
{
    public function index(): View
    {
        return view('public.org-units', ['companies' => $this->tree()]);
    }

    public function json(): JsonResponse
    {
        return response()->json(
            $this->tree()->map(fn (array $c) => [
                'company' => $c['company'],
                'departments' => $c['departments']->map($this->toArray(...))->all(),
            ])->values()
        );
    }

    /** @return Collection<int, array{company: string, departments: Collection}> */
    private function tree(): Collection
    {
        $departments = OrgStructure::query()
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->with(['company:id,name', 'divisions.subdivisions'])
            ->orderBy('name')
            ->get();

        return $departments
            ->groupBy('company_id')
            ->map(fn (Collection $rows) => [
                'company' => $rows->first()->company?->name ?? '—',
                'departments' => $rows,
            ])
            ->sortBy('company')
            ->values();
    }

    private function toArray(OrgStructure $node): array
    {
        return [
            'name' => $node->name,
            'code' => $node->code,
            'children' => $node->divisions->map(fn (OrgStructure $div) => [
                'name' => $div->name,
                'code' => $div->code,
                'children' => $div->subdivisions->map(fn (OrgStructure $sub) => [
                    'name' => $sub->name,
                    'code' => $sub->code,
                ])->all(),
            ])->all(),
        ];
    }
}
