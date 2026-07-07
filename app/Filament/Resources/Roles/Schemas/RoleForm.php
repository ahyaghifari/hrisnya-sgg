<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

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

                    // Dynamically generate a CheckboxList section per permission group.
                    // Show permission names as "Akses Role", "Buat Role", etc. and sort by action order.
                    (function () {
                        $actionLabels = [
                            'view' => 'Akses Menu',
                            'create' => 'Buat Data',
                            'edit' => 'Edit Data',
                            'delete' => 'Hapus Data',
                        ];

                        $actionOrder = ['view', 'create', 'edit', 'delete'];

                        $groups = Permission::query()
                            ->orderBy('id')
                            ->get()
                            ->groupBy(function ($permission) {
                                $parts = explode('_', $permission->name);

                                // Group berdasarkan semua kata setelah action
                                return count($parts) > 1
                                    ? implode('_', array_slice($parts, 1))
                                    : 'general';
                            });

                        // Container di form ini SENGAJA disamakan dengan grup navigasi Filament
                        // (lihat app/Filament/Resources/**, navigationGroup masing-masing) —
                        // supaya admin yang atur akses role tidak perlu mikir ulang pengelompokan
                        // yang beda dari yang dia lihat sehari-hari di menu sidebar.
                        $navGroupMap = [
                            // Master Data
                            'master_data_perusahaan' => 'Master Data',
                            'master_data_departemen' => 'Master Data',
                            'master_data_divisi' => 'Master Data',
                            'master_data_sub_divisi' => 'Master Data',
                            'master_data_jabatan' => 'Master Data',
                            'master_data_pangkat' => 'Master Data',
                            'master_data_golongan' => 'Master Data',
                            'master_data_kelas' => 'Master Data',
                            'master_data_jenis_karyawan' => 'Master Data',
                            'master_data_agama' => 'Master Data',
                            'master_data_bank' => 'Master Data',
                            'master_data_jenis_cuti' => 'Master Data',
                            'master_data_jenis_perizinan' => 'Master Data',
                            'master_data_jenis_lembur' => 'Master Data',
                            'master_data_layanan_penugasan' => 'Master Data',

                            // Kepegawaian
                            'struktur_organisasi' => 'Kepegawaian',
                            'karyawan' => 'Kepegawaian',
                            'dokumen_karyawan' => 'Kepegawaian',
                            'manajemen_lowongan_kerja' => 'Kepegawaian',
                            'penilaian_karyawan' => 'Kepegawaian',
                            'master_data_pola_shift' => 'Kepegawaian',
                            'template_shift' => 'Kepegawaian',
                            'coaching' => 'Kepegawaian',
                            'counseling' => 'Kepegawaian',

                            // Manajemen Organisasi
                            'data_absensi' => 'Manajemen Organisasi',
                            'report_data_absensi' => 'Manajemen Organisasi',
                            'pengajuan_absensi' => 'Manajemen Organisasi',
                            'penugasan_individu' => 'Manajemen Organisasi',
                            'man_power_planning' => 'Manajemen Organisasi',
                            'perubahan_status_karyawan' => 'Manajemen Organisasi',
                            'manajemen_persetujuan' => 'Manajemen Organisasi',

                            // Jadwal Shift Karyawan
                            'jadwal_shift' => 'Jadwal Shift Karyawan',
                            'manajemen_tukar_shift' => 'Jadwal Shift Karyawan',

                            // Manajemen Pengguna
                            'data_pribadi' => 'Manajemen Pengguna',
                            'role' => 'Manajemen Pengguna',
                            'permission' => 'Manajemen Pengguna',

                            // Keuangan
                            'data_overtime_cost' => 'Keuangan',
                            'pengajuan_jasa_dokter' => 'Keuangan',
                            'gaji_trainner' => 'Keuangan',
                            'master_data_komponen_gaji' => 'Keuangan',
                            'payroll' => 'Keuangan',
                            'doctor_payroll' => 'Keuangan',
                            'program_incentive' => 'Keuangan',

                            // LMS
                            'pengajuan_pelatihan' => 'LMS',
                            'lms' => 'LMS',
                            'bank_soal' => 'LMS',
                            'quiz' => 'LMS',
                            'rencana_pelatihan' => 'LMS',
                            'jabatan_dan_kompetensi' => 'LMS',
                        ];

                        // Urutan container ditampilkan
                        $containerOrder = [
                            'Master Data', 'Kepegawaian', 'Manajemen Organisasi',
                            'Jadwal Shift Karyawan', 'Manajemen Pengguna', 'Keuangan', 'LMS',
                        ];

                        $containerSubSections = [];
                        $containerFieldKeys = []; // [container => [field_key => [semua nama permission di field itu]]]
                        $ungroupedSections = [];

                        foreach ($groups as $groupName => $permissions) {
                            $container = $navGroupMap[$groupName] ?? null;

                            // Kalau ternaung container & namanya diawali "master_data_", label
                            // sub-item cukup nama resource-nya saja ("master_data_perusahaan" ->
                            // "Perusahaan") — tidak perlu diulang jadi "Master Data Perusahaan"
                            // karena sudah jelas dari nama container-nya.
                            $rawLabel = $container && str_starts_with($groupName, 'master_data_')
                                ? substr($groupName, strlen('master_data_'))
                                : $groupName;
                            $label = ucwords(str_replace(['-', '_'], ' ', $rawLabel));

                            // Sort permissions by action order
                            $sortedPermissions = collect($permissions)->sortBy(function ($permission) use ($actionOrder) {
                                $parts = explode('_', $permission->name);
                                $action = $parts[0] ?? '';
                                $index = array_search($action, $actionOrder);
                                return $index !== false ? $index : 999;
                            });

                            // Build options with custom wording
                            $options = [];
                            foreach ($sortedPermissions as $permission) {
                                $parts = explode('_', $permission->name);
                                $action = $parts[0] ?? '';
                                $actionLabel = $actionLabels[$action] ?? ucfirst($action);

                                $options[$permission->name] = "{$actionLabel} {$label}";
                            }

                            $fieldKey = $groupName . '_has_permissions';

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

                            // Fallback: permission yang belum dipetakan ke grup navigasi manapun
                            // (mis. permission baru yang lupa didaftarkan di $navGroupMap di atas)
                            // tetap tampil sebagai section berdiri sendiri, bukan hilang begitu saja.
                            $description = "Atur hak akses terkait {$label}.";
                            if ($groupName === 'pengajuan_absensi') {
                                $description = "Atur hak akses terkait pengajuan dan approval absensi (izin, cuti, lembur).";
                            }

                            $ungroupedSections[] = Section::make('Akses ' . $label)
                                ->description($description)
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
