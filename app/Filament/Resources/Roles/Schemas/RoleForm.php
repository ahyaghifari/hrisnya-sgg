<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use UnitEnum;

class RoleForm
{
    /**
     * Gerbang baca SISTEM_* (portal karyawan) dikelompokkan bareng permission "anak"-nya
     * (EDIT_* custom + Shield Verb:Subject buat model yang tidak punya Filament Resource,
     * mis. UserKoperasi). Static supaya bisa dipakai ulang di EditRole::mutateFormDataBeforeFill()
     * — field key checkbox HARUS sama persis antara sini dan sana, kalau tidak checkbox tampil
     * kosong walau role sudah punya permission-nya (data tidak hilang, cuma UI-nya salah).
     *
     * @return array<string, array<int, string>>
     */
    public static function portalGroups(): array
    {
        return [
            CustomPermissionEnum::SISTEM_KEUANGAN->value => [
                CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI->value,
                CustomPermissionEnum::EDIT_PENERIMA_KOMPONEN_GAJI->value,
                CustomPermissionEnum::EDIT_KOMPONEN_GAJI->value,
                CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value,
                CustomPermissionEnum::GENERATE_PAYROLL->value,
                'Create:UserKoperasi',
                'Update:UserKoperasi',
                'Delete:UserKoperasi',
            ],
            CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value => [],
            CustomPermissionEnum::SISTEM_ABSENSI->value => [
                CustomPermissionEnum::EDIT_PENGATURAN_ABSENSI->value,
                CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value,
                'Create:Schedule',
                'Update:Schedule',
                'Delete:Schedule',
            ],
            CustomPermissionEnum::SISTEM_LMS->value => [
                CustomPermissionEnum::EDIT_DETAIL_KURSUS_LMS->value,
                CustomPermissionEnum::EDIT_HONORARIUM_LMS->value,
            ],
            CustomPermissionEnum::SISTEM_TUGAS->value => [
                CustomPermissionEnum::EDIT_PENGATURAN_TUGAS->value,
            ],
            CustomPermissionEnum::SISTEM_REKRUTMEN->value => [],
            CustomPermissionEnum::SISTEM_COACHING_COUNSELING->value => [],
        ];
    }

    /** Field key checkbox untuk 1 nama permission — dipakai RoleForm sendiri & EditRole (prefill). */
    public static function fieldKeyFor(string $permissionName): string
    {
        foreach (self::portalGroups() as $coarseName => $childNames) {
            if ($permissionName === $coarseName || in_array($permissionName, $childNames, true)) {
                return 'portal_' . Str::snake($coarseName) . '_has_permissions';
            }
        }

        $subject = explode(':', $permissionName)[1] ?? 'Lainnya';

        return Str::snake($subject) . '_has_permissions';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Role')
                    ->description('Form ini digunakan untuk mengatur detail role seperti nama dan permissions.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Role')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan nama role')
                            ->disabled(fn ($get) => $get('name') === 'super_admin'),

                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->options(function () {
                                $user = Auth::user();
                                if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
                                    return Companies::where('id', $user->company_id)
                                        ->pluck('name', 'id');
                                }
                                return Companies::pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih perusahaan')
                            ->helperText('Kosongkan jika role ini berlaku untuk global perusahaan (Syifa Global Group)')
                            ->default(Auth::user()->company_id)
                            ->dehydrated(true)
                            ->disabled(fn ($get) => ($get('name') === 'super_admin'))
                            ->required(fn () => Auth::user() && Auth::user()->company_id !== null),
                    ]),

                    // Dynamically generate a CheckboxList section per permission subject.
                    // Filament Shield generate permission berformat "Verb:Subject" (mis.
                    // "View:Employee"), dengan Subject = nama class model Resource-nya (lihat
                    // resolveSubject() di vendor/bezhansalleh/filament-shield/src/FilamentShield.php).
                    // Container/grup di form ini diambil LANGSUNG dari navigationGroup Resource
                    // yang memakai model tsb — bukan tebak-tebakan dari nama permission — supaya
                    // tetap akurat walau Resource baru ditambah/dipindah, tanpa perlu di-maintain manual.
                    (function () {
                        $actionLabels = [
                            'ViewAny' => 'Akses Menu',
                            'View' => 'Lihat Detail',
                            'Create' => 'Buat Data',
                            'Update' => 'Edit Data',
                            'Delete' => 'Hapus Data',
                            'DeleteAny' => 'Hapus Massal',
                            'ForceDelete' => 'Hapus Permanen',
                            'ForceDeleteAny' => 'Hapus Permanen Massal',
                            'Restore' => 'Pulihkan Data',
                            'RestoreAny' => 'Pulihkan Massal',
                            'Replicate' => 'Duplikasi Data',
                            'Reorder' => 'Urutkan Data',
                        ];

                        $actionOrder = array_keys($actionLabels);

                        // Peta subject (nama model) -> label & container, dibangun dari Resource
                        // yang benar-benar terdaftar di panel admin. Kalau satu model dipakai lebih
                        // dari 1 Resource (mis. JobPositions dipakai ManPower & Master), prioritaskan
                        // Resource yang navigationGroup-nya terisi.
                        $subjectMeta = [];
                        foreach (Filament::getPanel('admin')->getResources() as $resourceFqcn) {
                            if (!method_exists($resourceFqcn, 'getModel')) {
                                continue;
                            }

                            $subject = class_basename($resourceFqcn::getModel());
                            $group = $resourceFqcn::getNavigationGroup();
                            $container = $group instanceof UnitEnum
                                ? (method_exists($group, 'getLabel') ? $group->getLabel() : $group->name)
                                : $group;

                            $alreadyHasContainer = !empty($subjectMeta[$subject]['container'] ?? null);
                            if (!$alreadyHasContainer) {
                                $subjectMeta[$subject] = [
                                    'label' => Str::headline($resourceFqcn::getModelLabel()),
                                    'container' => $container,
                                ];
                            }
                        }

                        $labelFor = function (string $name) use ($actionLabels): string {
                            if ($custom = CustomPermissionEnum::tryFrom($name)) {
                                return $custom->label();
                            }

                            [$action, $subject] = array_pad(explode(':', $name, 2), 2, null);

                            if ($subject === null) {
                                return Str::headline($name);
                            }

                            return ($actionLabels[$action] ?? $action) . ' ' . Str::headline($subject);
                        };

                        // Centang gerbang SISTEM_* -> semua permission anaknya ikut tercentang
                        // otomatis (lihat afterStateUpdated di bawah), baru boleh dicentang ulang
                        // satu-satu mana yang mau dicabut.
                        $portalGroups = self::portalGroups();

                        $portalPermissionNames = collect($portalGroups)
                            ->flatMap(fn (array $children, string $coarse) => [$coarse, ...$children])
                            ->all();

                        $allPermissions = Permission::query()->orderBy('id')->get();

                        // Container admin (dari navigationGroup Resource) yang dilebur JADI SATU
                        // dengan section portal SISTEM_* senama — centang "Sistem Keuangan", bukan
                        // cuma EDIT_* custom yang ikut tercentang, tapi semua permission Create/
                        // Update/Delete/dst di grup admin "Keuangan" juga ikut (boleh dicentang
                        // ulang satu-satu). Container yang tidak ada padanan SISTEM_*-nya
                        // (Manajemen Organisasi, Manajemen Pengguna) tetap berdiri sendiri seperti
                        // sebelumnya.
                        $containerMerge = [
                            'Keuangan' => CustomPermissionEnum::SISTEM_KEUANGAN->value,
                            'Kepegawaian' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
                            'LMS' => CustomPermissionEnum::SISTEM_LMS->value,
                            'Presensi & Absensi' => CustomPermissionEnum::SISTEM_ABSENSI->value,
                            'Master' => CustomPermissionEnum::SISTEM_MASTER->value,
                        ];
                        $mergedContainerByCoarse = array_flip($containerMerge);

                        $groups = $allPermissions
                            ->reject(fn ($permission) => in_array($permission->name, $portalPermissionNames, true))
                            ->groupBy(fn ($permission) => explode(':', $permission->name)[1] ?? 'Lainnya');

                        // Urutan container ditampilkan
                        $containerOrder = [
                            'Kepegawaian', 'Manajemen Organisasi',
                            'Manajemen Pengguna', 'Keuangan', 'LMS', 'Master',
                        ];

                        $containerSubSections = [];
                        $containerFieldKeys = []; // [container => [field_key => [semua nama permission di field itu]]]
                        $ungroupedSections = [];

                        foreach ($groups as $subject => $permissions) {
                            $meta = $subjectMeta[$subject] ?? null;
                            $container = $meta['container'] ?? null;
                            $label = $meta['label'] ?? Str::headline($subject);

                            // Container baru (belum ada di urutan manual di atas) tetap ditampilkan,
                            // ditaruh di akhir — bukan hilang begitu saja.
                            if ($container && !in_array($container, $containerOrder, true)) {
                                $containerOrder[] = $container;
                            }

                            // Sort permissions by action order
                            $sortedPermissions = collect($permissions)->sortBy(function ($permission) use ($actionOrder) {
                                $action = explode(':', $permission->name)[0] ?? '';
                                $index = array_search($action, $actionOrder, true);
                                return $index !== false ? $index : 999;
                            });

                            // Build options with custom wording
                            $options = [];
                            foreach ($sortedPermissions as $permission) {
                                $action = explode(':', $permission->name)[0] ?? '';
                                $actionLabel = $actionLabels[$action] ?? $action;

                                $options[$permission->name] = "{$actionLabel} {$label}";
                            }

                            $fieldKey = Str::snake($subject) . '_has_permissions';

                            $checkboxList = CheckboxList::make($fieldKey)
                                ->label('Pilih Hak Akses')
                                ->bulkToggleable()
                                ->options($options)
                                ->columns(2)
                                ->disabled(fn ($get) => $get('name') === 'super_admin');

                            if ($container) {
                                $containerFieldKeys[$container][$fieldKey] = array_keys($options);

                                $containerSubSections[$container][] = Fieldset::make($label)
                                    ->columnSpanFull()
                                    ->schema([$checkboxList]);

                                continue;
                            }

                            // Fallback: permission yang Resource-nya belum punya navigationGroup
                            // (mis. Resource lepasan cluster yang belum di-set ulang grupnya) tetap
                            // tampil sebagai section berdiri sendiri, bukan hilang begitu saja.
                            $ungroupedSections[] = Section::make('Akses ' . $label)
                                ->description("Atur hak akses terkait {$label}.")
                                ->columnSpanFull()
                                ->schema([$checkboxList]);
                        }

                        // Section portal SISTEM_* — gabungkan checkbox coarse+EDIT_* custom DENGAN
                        // seluruh Fieldset container admin senama (kalau ada, lihat $containerMerge
                        // di atas), supaya "Sistem Keuangan" jadi SATU section utuh, bukan dua
                        // section terpisah yang isinya tumpang tindih.
                        $portalSections = [];
                        foreach ($portalGroups as $coarseName => $childNames) {
                            $mergedContainer = $mergedContainerByCoarse[$coarseName] ?? null;
                            $mergedFieldKeys = $mergedContainer ? ($containerFieldKeys[$mergedContainer] ?? []) : [];

                            $groupPermissions = $allPermissions->whereIn('name', [$coarseName, ...$childNames]);

                            if ($groupPermissions->isEmpty() && $mergedFieldKeys === []) {
                                continue;
                            }

                            $options = $groupPermissions
                                ->sortBy(fn ($permission) => $permission->name === $coarseName ? 0 : 1)
                                ->mapWithKeys(fn ($permission) => [$permission->name => $labelFor($permission->name)])
                                ->all();

                            $fieldKey = 'portal_' . Str::snake($coarseName) . '_has_permissions';

                            // Semua field yang ikut ter-cascade/toggle bareng coarse permission ini
                            // — field sendiri (coarse + EDIT_* custom) + tiap subject Shield yang
                            // masuk container admin senama.
                            $allFieldKeys = [$fieldKey => array_keys($options)] + $mergedFieldKeys;

                            $selectAllToggle = Toggle::make('toggle_all_portal_' . Str::snake($coarseName))
                                ->label('Pilih Semua')
                                ->inline(false)
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function ($component, callable $get) use ($allFieldKeys) {
                                    foreach ($allFieldKeys as $key => $allNames) {
                                        $current = $get($key) ?? [];
                                        if (count(array_intersect($allNames, $current)) !== count($allNames)) {
                                            $component->state(false);
                                            return;
                                        }
                                    }
                                    $component->state(! empty($allFieldKeys));
                                })
                                ->afterStateUpdated(function (bool $state, callable $set) use ($allFieldKeys) {
                                    foreach ($allFieldKeys as $key => $allNames) {
                                        $set($key, $state ? $allNames : []);
                                    }
                                })
                                ->disabled(fn ($get) => $get('name') === 'super_admin');

                            $portalCheckboxList = CheckboxList::make($fieldKey)
                                ->label('Pilih Hak Akses')
                                ->options($options)
                                ->columns(2)
                                ->live()
                                ->afterStateUpdated(function (?array $state, ?array $old, callable $set) use ($coarseName, $childNames, $fieldKey, $mergedFieldKeys) {
                                    $state ??= [];
                                    $old ??= [];

                                    if (in_array($coarseName, $state, true) && ! in_array($coarseName, $old, true)) {
                                        $set($fieldKey, array_values(array_unique([...$state, ...$childNames])));

                                        foreach ($mergedFieldKeys as $key => $allNames) {
                                            $set($key, $allNames);
                                        }
                                    }
                                })
                                ->disabled(fn ($get) => $get('name') === 'super_admin');

                            $portalSections[] = Section::make($labelFor($coarseName))
                                ->description('Centang "' . $labelFor($coarseName) . '" untuk buka akses baca halaman portal-nya sekaligus semua hak edit di bawahnya (termasuk menu admin terkait kalau ada) — boleh dicentang ulang satu-satu mana yang tidak perlu.')
                                ->collapsible()
                                ->collapsed()
                                ->columnSpanFull()
                                ->schema([
                                    $selectAllToggle,
                                    $portalCheckboxList,
                                    ...($mergedContainer ? ($containerSubSections[$mergedContainer] ?? []) : []),
                                ]);
                        }

                        $groupSections = [];

                        foreach ($containerOrder as $containerName) {
                            if (empty($containerSubSections[$containerName])) {
                                continue;
                            }

                            // Container yang sudah dilebur ke section portal SISTEM_* di atas
                            // tidak dirender lagi berdiri sendiri di sini.
                            if (array_key_exists($containerName, $containerMerge)) {
                                continue;
                            }

                            $fieldKeys = $containerFieldKeys[$containerName] ?? [];

                            $selectAllToggle = Toggle::make('toggle_all_' . strtolower(str_replace(' ', '_', $containerName)))
                                ->label('Pilih Semua')
                                ->inline(false)
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function ($component, callable $get) use ($fieldKeys) {
                                    foreach ($fieldKeys as $fieldKey => $allNames) {
                                        $current = $get($fieldKey) ?? [];
                                        if (count(array_intersect($allNames, $current)) !== count($allNames)) {
                                            $component->state(false);
                                            return;
                                        }
                                    }
                                    $component->state(! empty($fieldKeys));
                                })
                                ->afterStateUpdated(function (bool $state, callable $set) use ($fieldKeys) {
                                    foreach ($fieldKeys as $fieldKey => $allNames) {
                                        $set($fieldKey, $state ? $allNames : []);
                                    }
                                })
                                ->disabled(fn ($get) => $get('name') === 'super_admin');

                            $groupSections[] = Section::make($containerName)
                                ->description("Atur hak akses untuk seluruh menu di grup {$containerName}.")
                                ->collapsible()
                                ->collapsed()
                                ->columnSpanFull()
                                ->schema([$selectAllToggle, ...$containerSubSections[$containerName]]);
                        }

                        array_push($groupSections, ...$portalSections, ...$ungroupedSections);

                        return Section::make('Hak Akses')
                            ->description('Form ini digunakan untuk mengatur hak akses (permissions) yang dimiliki oleh role ini.')
                            ->columnSpanFull()
                            ->schema($groupSections);
                    })(),
            ]);
    }
}
