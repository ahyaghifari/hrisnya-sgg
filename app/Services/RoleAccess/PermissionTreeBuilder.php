<?php

namespace App\Services\RoleAccess;

use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use UnitEnum;

/**
 * Bangun tree hak akses (Container -> Subject/Modul -> Permission leaf) buat halaman portal
 * /hak-akses. Sumber data grouping SAMA PERSIS dengan RoleForm.php (admin panel
 * /admin/roles) - portalGroups()/containerMerge()/actionLabels()/subjectContainerOverrides()
 * dipanggil dari sana, bukan didefinisikan ulang, supaya susunan container/label tidak
 * pernah menyimpang antara admin panel dan portal.
 *
 * Beda dari RoleForm: node leaf di sini LANGSUNG nama permission (mis. "View:Payroll"),
 * bukan field-key gabungan per subject - jadi tidak perlu tabel fieldKeyFor() buat prefill,
 * checked state cukup irisan nama permission role dengan leaf id di tree.
 */
class PermissionTreeBuilder
{
    /**
     * @return array<int, array{id: string, label: string, children: array}>
     */
    public static function build(): array
    {
        $actionLabels = RoleForm::actionLabels();
        $actionOrder = array_keys($actionLabels);
        $containerMerge = RoleForm::containerMerge();
        $mergedContainerByCoarse = array_flip($containerMerge);
        $portalGroups = RoleForm::portalGroups();

        $portalPermissionNames = collect($portalGroups)
            ->flatMap(fn (array $children, string $coarse) => [$coarse, ...$children])
            ->all();
        $baseEmployeePermissions = RoleForm::baseEmployeePermissions();

        // Subject (nama model) -> label & container, dari Resource yang terdaftar di panel admin.
        $subjectMeta = [];
        foreach (Filament::getPanel('admin')->getResources() as $resourceFqcn) {
            if (! method_exists($resourceFqcn, 'getModel')) {
                continue;
            }

            $subject = class_basename($resourceFqcn::getModel());
            $group = $resourceFqcn::getNavigationGroup();
            $container = $group instanceof UnitEnum
                ? (method_exists($group, 'getLabel') ? $group->getLabel() : $group->name)
                : $group;

            $alreadyHasContainer = ! empty($subjectMeta[$subject]['container'] ?? null);
            if (! $alreadyHasContainer) {
                $subjectMeta[$subject] = [
                    'label' => Str::headline($resourceFqcn::getModelLabel()),
                    'container' => $container,
                ];
            }
        }

        $subjectMeta = RoleForm::applySubjectOverrides($subjectMeta);

        $labelFor = function (string $name) use ($actionLabels): string {
            if ($custom = \App\Enum\CustomPermissionEnum::tryFrom($name)) {
                return $custom->label();
            }

            [$action, $subject] = array_pad(explode(':', $name, 2), 2, null);

            if ($subject === null) {
                return Str::headline($name);
            }

            return ($actionLabels[$action] ?? $action) . ' ' . Str::headline($subject);
        };

        $allPermissions = Permission::query()->orderBy('id')->get();

        $customSubjectOverrides = RoleForm::customPermissionSubjectOverrides();

        $groups = $allPermissions
            ->reject(fn ($permission) => in_array($permission->name, $portalPermissionNames, true))
            ->reject(fn ($permission) => in_array($permission->name, $baseEmployeePermissions, true))
            ->groupBy(fn ($permission) => $customSubjectOverrides[$permission->name] ?? explode(':', $permission->name)[1] ?? 'Lainnya');

        $containerOrder = [
            'Kepegawaian', 'Penilaian Karyawan', 'Manajemen Organisasi',
            'Manajemen Pengguna', 'Keuangan', 'LMS', 'Master',
        ];

        // container => list mid-node subject (dipakai buat container biasa & mid-node
        // yang ikut ditempel ke root portal kalau container-nya di-merge).
        $containerSubjectNodes = [];
        $ungroupedNodes = [];

        foreach ($groups as $subject => $permissions) {
            $meta = $subjectMeta[$subject] ?? null;
            $container = $meta['container'] ?? null;
            $label = $meta['label'] ?? Str::headline($subject);

            if ($container && ! in_array($container, $containerOrder, true)) {
                $containerOrder[] = $container;
            }

            $sortedPermissions = collect($permissions)->sortBy(function ($permission) use ($actionOrder) {
                $action = explode(':', $permission->name)[0] ?? '';
                $index = array_search($action, $actionOrder, true);
                return $index !== false ? $index : 999;
            });

            $leaves = $sortedPermissions->map(fn ($permission) => [
                'id' => $permission->name,
                'label' => $labelFor($permission->name),
            ])->values()->all();

            if ($leaves === []) {
                continue;
            }

            $node = [
                'id' => 'subject_' . Str::slug($subject),
                'label' => $label,
                'children' => $leaves,
            ];

            if ($container) {
                $containerSubjectNodes[$container][] = $node;
                continue;
            }

            $ungroupedNodes[] = $node;
        }

        // Root portal SISTEM_* — coarse + EDIT_* custom jadi 1 mid-node "Akses Portal", plus
        // seluruh subject-node container yang di-merge (Keuangan, Kepegawaian, dst) ikut jadi
        // children root yang sama.
        $portalRoots = [];
        foreach ($portalGroups as $coarseName => $childNames) {
            $mergedContainer = $mergedContainerByCoarse[$coarseName] ?? null;
            $mergedSubjectNodes = $mergedContainer ? ($containerSubjectNodes[$mergedContainer] ?? []) : [];

            $groupPermissions = $allPermissions->whereIn('name', [$coarseName, ...$childNames]);

            if ($groupPermissions->isEmpty() && $mergedSubjectNodes === []) {
                continue;
            }

            $gateLeaves = $groupPermissions
                ->sortBy(fn ($permission) => $permission->name === $coarseName ? 0 : 1)
                ->map(fn ($permission) => ['id' => $permission->name, 'label' => $labelFor($permission->name)])
                ->values()
                ->all();

            $children = [];
            if ($gateLeaves !== []) {
                $children[] = [
                    'id' => 'gate_' . Str::slug($coarseName),
                    'label' => 'Akses Portal',
                    'children' => $gateLeaves,
                ];
            }
            array_push($children, ...$mergedSubjectNodes);

            $portalRoots[] = [
                'id' => 'root_' . Str::slug($coarseName),
                'label' => $labelFor($coarseName),
                'children' => $children,
            ];
        }

        $roots = [];
        foreach ($containerOrder as $containerName) {
            if (empty($containerSubjectNodes[$containerName])) {
                continue;
            }

            // Container yang sudah dilebur ke root portal SISTEM_* di atas tidak dirender
            // lagi berdiri sendiri di sini.
            if (array_key_exists($containerName, $containerMerge)) {
                continue;
            }

            $roots[] = [
                'id' => 'root_' . Str::slug($containerName),
                'label' => $containerName,
                'children' => $containerSubjectNodes[$containerName],
            ];
        }

        // Root "Sistem Dasar Karyawan" — 4 permission independen (bukan coarse-gate+children
        // kayak portalGroups), ditaruh paling depan karena ini yang paling sering dipakai
        // (menu dasar nav baris 1 semua karyawan).
        $baseEmployeeLeaves = $allPermissions
            ->whereIn('name', $baseEmployeePermissions)
            ->map(fn ($permission) => ['id' => $permission->name, 'label' => $labelFor($permission->name)])
            ->values()
            ->all();

        $baseEmployeeRoot = $baseEmployeeLeaves === [] ? [] : [[
            'id' => 'root_sistem-dasar-karyawan',
            'label' => 'Sistem Dasar Karyawan',
            'children' => [[
                'id' => 'gate_sistem-dasar-karyawan',
                'label' => 'Akses Menu',
                'children' => $baseEmployeeLeaves,
            ]],
        ]];

        array_push($roots, ...$baseEmployeeRoot, ...$portalRoots, ...$ungroupedNodes);

        return $roots;
    }

    /** Semua leaf id (nama permission) dalam 1 node, dipakai buat hitung checked/indeterminate. */
    public static function leafIds(array $node): array
    {
        if (! isset($node['children']) || $node['children'] === []) {
            return [$node['id']];
        }

        $ids = [];
        foreach ($node['children'] as $child) {
            array_push($ids, ...self::leafIds($child));
        }

        return $ids;
    }

    /** Semua nama permission (leaf) di seluruh tree — dipakai validasi saat save (tolak nama
     * yang tidak ada di tree, cegah role dikasih permission random lewat request tampering). */
    public static function allLeafNames(array $roots): array
    {
        $ids = [];
        foreach ($roots as $root) {
            array_push($ids, ...self::leafIds($root));
        }

        return array_values(array_unique($ids));
    }
}
