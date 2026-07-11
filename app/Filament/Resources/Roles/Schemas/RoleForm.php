<?php

namespace App\Filament\Resources\Roles\Schemas;

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

                        $groups = Permission::query()
                            ->orderBy('id')
                            ->get()
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

                        $groupSections = [];

                        foreach ($containerOrder as $containerName) {
                            if (empty($containerSubSections[$containerName])) {
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

                        array_push($groupSections, ...$ungroupedSections);

                        return Section::make('Hak Akses')
                            ->description('Form ini digunakan untuk mengatur hak akses (permissions) yang dimiliki oleh role ini.')
                            ->columnSpanFull()
                            ->schema($groupSections);
                    })(),
            ]);
    }
}
