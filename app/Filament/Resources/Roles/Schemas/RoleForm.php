<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enum\CustomPermissionEnum;
use App\Enum\WidgetPermissionEnum;
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
     * mis. Schedule). Static supaya bisa dipakai ulang di EditRole::mutateFormDataBeforeFill()
     * — field key checkbox HARUS sama persis antara sini dan sana, kalau tidak checkbox tampil
     * kosong walau role sudah punya permission-nya (data tidak hilang, cuma UI-nya salah).
     *
     * UserKoperasi & BpjsDependent TIDAK di-list manual di sini (beda dari Schedule) — keduanya
     * sekarang punya Filament Resource (navigationGroup 'Keuangan'), jadi otomatis ke-merge ke
     * section "Sistem Keuangan" lewat $containerMerge di bawah, tidak perlu di-hardcode.
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
                CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value,
                CustomPermissionEnum::LIHAT_PENERIMA_KOMPONEN_GAJI->value,
                CustomPermissionEnum::LIHAT_MAPPING_KOMPONEN_GAJI->value,
                CustomPermissionEnum::LIHAT_PAJAK->value,
                CustomPermissionEnum::LIHAT_DATA_REKENING->value,
                CustomPermissionEnum::EDIT_DATA_REKENING->value,
                CustomPermissionEnum::LIHAT_OVERRIDE_KOMPONEN_GAJI->value,
                CustomPermissionEnum::EDIT_OVERRIDE_KOMPONEN_GAJI->value,
                CustomPermissionEnum::LIHAT_KENAIKAN_GAJI_POKOK->value,
                CustomPermissionEnum::EDIT_KENAIKAN_GAJI_POKOK->value,
                CustomPermissionEnum::LIHAT_OMSET->value,
                CustomPermissionEnum::KELOLA_OMSET->value,
                CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value,
                CustomPermissionEnum::KELOLA_INPUT_JASA_UNIT->value,
                CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT->value,
                CustomPermissionEnum::GENERATE_PAYROLL->value,
                CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value,
                CustomPermissionEnum::LIHAT_DASHBOARD_KEUANGAN->value,
            ],
            CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value => [
                CustomPermissionEnum::LIHAT_DASHBOARD_KEPEGAWAIAN->value,
                CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value,
                CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN->value,
                CustomPermissionEnum::LIHAT_JOB_EVALUATION->value,
                CustomPermissionEnum::KELOLA_JOB_EVALUATION->value,
                CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value,
                CustomPermissionEnum::IMPORT_KARYAWAN->value,
                CustomPermissionEnum::EXPORT_DATA_KARYAWAN->value,
                // Portal "Alur Persetujuan" (2026-08-16) - dikelompokkan Kepegawaian atas
                // instruksi user, meski konsepnya lintas-modul.
                CustomPermissionEnum::LIHAT_ALUR_PERSETUJUAN->value,
                CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value,
            ],
            CustomPermissionEnum::SISTEM_ABSENSI->value => [
                CustomPermissionEnum::LIHAT_DASHBOARD_PRESENSI->value,
                CustomPermissionEnum::LIHAT_PENGATURAN_ABSENSI->value,
                CustomPermissionEnum::EDIT_PENGATURAN_ABSENSI->value,
                CustomPermissionEnum::SYNC_DATA_ABSENSI->value,
                CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value,
                // Create/Update/Delete:Schedule TIDAK di-list manual lagi di sini (2026-08-07) -
                // Schedule sekarang punya ScheduleResource beneran (navigationGroup 'Presensi &
                // Absensi'), jadi ke-5 verb-nya (ViewAny/View/Create/Update/Delete) otomatis
                // ke-merge jadi 1 subject node "Schedule" di bawah root Sistem Absensi lewat
                // containerMerge, tidak perlu di-hardcode di sini.
            ],
            CustomPermissionEnum::SISTEM_LMS->value => [
                CustomPermissionEnum::EDIT_DETAIL_KURSUS_LMS->value,
                CustomPermissionEnum::EDIT_HONORARIUM_LMS->value,
                CustomPermissionEnum::LIHAT_HONORARIUM_LMS->value,
                CustomPermissionEnum::EDIT_PENGATURAN_LMS->value,
                CustomPermissionEnum::LIHAT_PENGATURAN_LMS->value,
                CustomPermissionEnum::LIHAT_LAPORAN_PELATIHAN->value,
                // Halaman portal Gaji Pemateri (`/keuangan/gaji-pemateri`) - route-nya di bawah
                // Keuangan tapi datanya rekap honor pemateri LMS (TrainerAssessmentResults),
                // jadi permission-nya dikelompokkan di sini bareng permission LMS lain, bukan
                // di grup SISTEM_KEUANGAN (instruksi user 2026-08-12).
                CustomPermissionEnum::LIHAT_GAJI_PEMATERI->value,
                CustomPermissionEnum::KELOLA_GAJI_PEMATERI->value,
                // Approve enrollment pelatihan LMS - satu-satunya permission approve yang masih
                // dicek lewat literal permission (bukan approval flow engine), lihat
                // CourseUserService::approve/reject. Grup "Approver" dibubarkan 2026-09-11:
                // approve_pengajuan_absensi (izin/cuti/lembur murni approval flow) &
                // approve_manajemen_lowongan_kerja (rekrutmen pindah app) sudah tidak kepakai.
                CustomPermissionEnum::APPROVE_PENGAJUAN_PELATIHAN->value,
            ],
            CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value => [
                CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value,
            ],
            // Tidak ada child custom permission - grup ini cuma pembungkus supaya container
            // admin 'Penilaian Karyawan' (navigationGroup 4 Assessment Resource) ke-merge jadi
            // 1 section sama container portal ini, lihat containerMerge().
            CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value => [],
            // Gerbang dropdown nav "Sikartap" (2026-08-25) - grup pembungkus supaya container
            // admin 'Sikartap' (navigationGroup SikartapResource, subject "Pengajuan Karyawan
            // Tetap") ke-merge jadi 1 section sama container portal ini - pola sama persis
            // SISTEM_PENILAIAN_KARYAWAN di atas. (Input MCU pindah ke SISTEM_MCU, 2026-09-08.)
            CustomPermissionEnum::SISTEM_SIKARTAP->value => [
                CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value,
                CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value,
                CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value,
                CustomPermissionEnum::NILAI_IDE_INOVASI_DIREKTUR->value,
                CustomPermissionEnum::NILAI_IDE_INOVASI_KOMISARIS->value,
            ],
            // Gerbang nav group "Rekrutmen" (modul sendiri 2026-08-28). Tidak ada child custom
            // permission — pembungkus supaya container admin 'Rekrutmen' (navigationGroup
            // JobOpeningResource, subject "Kelola Lowongan") ke-merge jadi 1 section, pola sama
            // SISTEM_SIKARTAP.
            CustomPermissionEnum::SISTEM_REKRUTMEN->value => [
                CustomPermissionEnum::LIHAT_DASHBOARD_REKRUTMEN->value,
            ],
            // Modul MCU berdiri sendiri (2026-09-08) — 5 permission isi/edit hasil MCU pindah
            // ke sini dari sub-grup "Input MCU" milik Rekrutmen. Staf klinis cukup diberi
            // section ini, tak perlu SISTEM_SIKARTAP / SISTEM_REKRUTMEN.
            CustomPermissionEnum::SISTEM_MCU->value => [
                CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value,
                CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value,
                CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value,
                CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value,
                CustomPermissionEnum::EDIT_INPUT_MCU->value,
            ],
        ];
    }

    /**
     * Permission "akses menu dasar" nav baris 1 portal karyawan (Tugas/Pelatihan/Pengajuan
     * Absensi/Pembinaan Saya) — flat, BUKAN pola coarse-gate+children kayak portalGroups()
     * (keempatnya independen, bukan 1 gerbang yang mengunci turunannya). Dikelompokkan jadi
     * 1 root "Sistem Dasar Karyawan" di RoleForm::configure() & PermissionTreeBuilder biar
     * tidak nyasar ke bucket "Lainnya" (permission custom snake_case tanpa ':' otomatis
     * masuk situ kalau tidak di-exclude manual).
     *
     * @return array<int, string>
     */
    public static function baseEmployeePermissions(): array
    {
        return [
            CustomPermissionEnum::TUGAS_SAYA->value,
            CustomPermissionEnum::BUAT_BOARD_TUGAS->value,
            CustomPermissionEnum::PELATIHAN_SAYA->value,
            CustomPermissionEnum::AJUKAN_PELATIHAN->value,
            CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
            CustomPermissionEnum::PEMBINAAN_SAYA->value,
        ];
    }

    /**
     * Override/paksa container untuk subject TERTENTU, dipakai buat 2 kasus:
     * 1) Resource-nya di bawah Filament Cluster (getNavigationGroup() balik null buat
     *    Resource ber-cluster, tidak ke-deteksi otomatis lewat navigationGroup).
     * 2) Subject yang SAMA SEKALI tidak punya Filament Resource (Policy manual/permission
     *    Shield lama tanpa Resource aktif) tapi tetap masuk akal dikelompokkan ke 1
     *    container tertentu — lihat applySubjectOverrides().
     *
     * ShiftScheduleAssignments/ShiftSwapRequests/ShiftTemplates: tidak ada Resource-nya
     * (dicek 2026-08-07), tapi user eksplisit minta dikelompokkan ke "Jadwal Shift" (bukan
     * dihapus kayak MasterShift/ExtraHourAllowanceRules yang beneran orphan). Sejak 2026-09-11
     * dua di antaranya beneran dipakai sebagai gerbang tab di ScheduleDetailPage (menggantikan
     * kelola_jadwal_shift yang dihapus): Update:ShiftTemplates untuk tab "Template Shift"
     * (ScheduleShiftRotationTab), Update:ShiftScheduleAssignments untuk tab "Detail Penjadwalan"
     * (ScheduleShiftRosterTab). ShiftOvertimeCosts SEBALIKNYA
     * dihapus total (2026-08-11) - model/Policy-nya beneran mati (skema Model tidak sinkron
     * lagi sama tabel `shift_overtime_costs` asli, tidak ada UI portal/admin yang mengelolanya
     * sama sekali), digantikan LemburCalculator (base rate per Pangkat, lihat komentarnya).
     *
     * @return array<string, string>
     */
    public static function subjectContainerOverrides(): array
    {
        return [
            'ShiftScheduleAssignments' => 'Jadwal Shift',
            'ShiftSwapRequests' => 'Jadwal Shift',
            'ShiftTemplates' => 'Jadwal Shift',
            // ScheduleShiftTeam (2026-09-12): gerbang tab "Regu" ScheduleDetailPage, tidak
            // punya Filament Resource - sama alasan 3 subject Jadwal Shift di atas.
            'ScheduleShiftTeam' => 'Jadwal Shift',
            // ExtraShiftAssignment: punya ExtraShiftAssignmentResource (navigationGroup 'Presensi
            // & Absensi', posisi menu admin sidebar TIDAK diubah), tapi user minta 7 permission
            // Shield-nya dikelompokkan ke section "Jadwal Shift" di tree /hak-akses & checkbox
            // Role, bukan ikut merge ke "Sistem Absensi". Pola sama Coaching/Counseling di bawah.
            'ExtraShiftAssignment' => 'Jadwal Shift',
            // ExtraShiftRule (2026-08-17): portal-only, tidak ada Filament Resource (lihat
            // ExtraShiftRulePolicy) - tanpa override ini permission-nya jatuh ke ungroupedSections
            // sendirian, bukan ikut merge ke section "Sistem Absensi" seperti diminta user.
            'ExtraShiftRule' => 'Presensi & Absensi',
            // PayrollComponents: Filament Page (bukan Resource), jadi navigationGroup-nya
            // (sudah 'Keuangan') tidak ke-scan lewat getResources() - paksa manual.
            'PayrollComponents' => 'Keuangan',
            // Coaching & Counseling (2026-08-17): CoachingResource/CounselingResource
            // navigationGroup-nya 'Pembinaan' (posisi menu ADMIN SIDEBAR-nya, grup terpisah di bawah
            // Kepegawaian sejak 2026-09-20) - tanpa override ini 7 permission Shield-nya (ViewAny/View/
            // Create/Update/Delete/Restore/ForceDelete) ikut ke-merge ke section "Sistem
            // Kepegawaian" di tree /hak-akses & checkbox Role, terpisah dari
            // COACHING_KARYAWAN_LAINNYA/SISTEM_PEMBINAAN_KARYAWAN yang harusnya satu section.
            // Override ini CUMA mengubah pengelompokan tree/checkbox, tidak menyentuh posisi
            // menu admin sidebar sama sekali.
            'Coaching' => 'Pembinaan Karyawan',
            'Counseling' => 'Pembinaan Karyawan',
            // Semua WidgetPermissionEnum (2026-08-19, instruksi user) numpuk di 1 subject
            // 'DashboardWidget' -> container "Dashboard Widget" - lihat
            // customPermissionSubjectOverrides() di bawah, subject-nya digenerate otomatis dari
            // WidgetPermissionEnum::cases() supaya widget BARU ke depan otomatis masuk grup ini
            // tanpa perlu baris override manual lagi di sini.
            'DashboardWidget' => 'Dashboard Widget',
            // Permission LIHAT_DASHBOARD_SIKARTAP_* (2026-08-25) dipetakan ke subject buatan
            // 'SikartapDashboard' lewat customPermissionSubjectOverrides() di bawah - sub-grup
            // "Dashboard" nempel di root 'Sikartap' yang sama, pola persis 'SikartapMcu'.
            // (_BUTUH_TINDAKAN dibuang 2026-09-11, lihat migration drop_orphan_sikartap_butuh_tindakan.)
            'SikartapDashboard' => 'Sikartap',
            // JobOpenings: subject permission dari JobOpeningsPolicy (Resource admin panel HRIS
            // sudah dihapus — CRUD lowongan pindah ke panel `rekrutmen`). Dipetakan ke container
            // 'Rekrutmen' + label 'Kelola Lowongan' via subjectLabelOverrides().
            'JobOpenings' => 'Rekrutmen',
        ];
    }

    /**
     * Subject yang Fieldset-nya perlu MUNCUL LAGI di section portal container LAIN, di luar
     * container utamanya dari subjectContainerOverrides() — dipakai kalau 1 permission memang
     * relevan buat 2 modul sekaligus (bukan bikin permission baru/duplikat, cuma tampilkan
     * checkbox yang SAMA di 2 tempat biar admin yang buka salah satu section tetap ketemu).
     * Kosong sejak MCU jadi modul sendiri (2026-09-08).
     *
     * @return array<string, array<int, string>>
     */
    public static function additionalMergeContainers(): array
    {
        return [];
    }

    /**
     * Label eksplisit buat subject yang labelnya BUKAN hasil scan Filament Resource (jadi
     * Str::headline() fallback di applySubjectOverrides() bakal salah kapitalisasi, mis.
     * Str::headline('SikartapMcu') = "Sikartap Mcu" bukan "Input MCU" yang diinginkan).
     *
     * @return array<string, string>
     */
    public static function subjectLabelOverrides(): array
    {
        return [
            'SikartapDashboard' => 'Dashboard',
            'JobOpenings' => 'Kelola Lowongan',
            'ScheduleShiftTeam' => 'Tim',
            // Subject :OrgStructure dipakai bareng DepartmentsResource/DivisionsResource/
            // SubDivisionsResource - di tree/checkbox tampil sebagai satu node "Struktur
            // Organisasi", bukan label salah satu resource-nya (Departemen).
            'OrgStructure' => 'Struktur Organisasi',
        ];
    }

    /**
     * TUKAR_SHIFT_SAYA (custom permission flat, tanpa ':') sebelumnya jatuh ke subject literal
     * 'Lainnya' pas grouping (`explode(':', $name)[1] ?? 'Lainnya'` selalu null buat nama tanpa
     * titik dua), jadi nyempil sendirian di section "Akses Lainnya" - instruksi user 2026-08-17:
     * gabung ke section "Jadwal Shift" yang sudah ada (tempat
     * ShiftScheduleAssignments/ShiftSwapRequests/ShiftTemplates lewat subjectContainerOverrides()
     * di atas). Paksa subject-nya ikut 'ShiftSwapRequests' (subject Jadwal Shift yang sudah
     * ada) supaya nempel di node yang sama, bukan bikin node "Jadwal Shift" duplikat.
     *
     * (JADWAL_SHIFT_SAYA dulu di sini juga - dihapus 2026-09-11, permission itu sendiri sudah
     * dibuang, lihat migration drop_jadwal_shift_saya_permission.)
     *
     * @return array<string, string>
     */
    public static function customPermissionSubjectOverrides(): array
    {
        return [
            CustomPermissionEnum::TUKAR_SHIFT_SAYA->value => 'ShiftSwapRequests',
            CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_STATISTIK->value => 'SikartapDashboard',
            CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_ANTRIAN_TAHAP->value => 'SikartapDashboard',
            // Semua permission WidgetPermissionEnum otomatis ikut subject 'DashboardWidget'
            // (container "Dashboard Widget", lihat subjectContainerOverrides()) - generik dari
            // ::cases(), BUKAN didaftar satu-satu, supaya widget Dashboard baru ke depan
            // (instruksi user: bakal terus nambah) langsung ke-grup tanpa sentuh method ini lagi.
            ...collect(WidgetPermissionEnum::cases())->mapWithKeys(fn (WidgetPermissionEnum $p) => [$p->value => 'DashboardWidget'])->all(),
        ];
    }

    /**
     * Terapkan subjectContainerOverrides() ke $subjectMeta — beda dari override inline di
     * dalam loop scan Resource (yang cuma jalan kalau subject-nya PUNYA Resource), method
     * ini juga bikin entry baru buat subject yang sama sekali tidak ada Resource-nya, biar
     * tetap masuk 1 container alih-alih jadi "ungrouped" sendirian.
     *
     * @param array<string, array{label: string, container: ?string}> $subjectMeta
     * @return array<string, array{label: string, container: ?string}>
     */
    public static function applySubjectOverrides(array $subjectMeta): array
    {
        $labelOverrides = self::subjectLabelOverrides();

        foreach (self::subjectContainerOverrides() as $subject => $container) {
            $subjectMeta[$subject]['container'] = $container;
            $subjectMeta[$subject]['label'] = $labelOverrides[$subject]
                ?? ($subjectMeta[$subject]['label'] ?? Str::headline($subject));
        }

        // Label override buat subject yang PUNYA Resource sendiri (tidak lewat container
        // override di atas) — mis. OrgStructure: DepartmentsResource/DivisionsResource/
        // SubDivisionsResource share 1 model, node-nya harus baca "Struktur Organisasi".
        foreach ($labelOverrides as $subject => $label) {
            if (isset($subjectMeta[$subject])) {
                $subjectMeta[$subject]['label'] = $label;
            }
        }

        return $subjectMeta;
    }

    /**
     * Label per verb Shield (ViewAny/View/Create/dst) — dipakai RoleForm sendiri &
     * PermissionTreeBuilder (portal /hak-akses) supaya label leaf permission konsisten
     * di admin panel maupun portal. Cuma verb yang benar-benar di-generate
     * (config/filament-shield.php generator.singular) — DeleteAny/ForceDelete/
     * ForceDeleteAny/RestoreAny/Replicate sengaja tidak dimasukkan, tidak pernah ada di DB.
     *
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'ViewAny' => 'Akses Menu',
            'View' => 'Lihat Detail',
            'Create' => 'Buat Data',
            'Update' => 'Edit Data',
            'Delete' => 'Hapus Data',
            'Restore' => 'Pulihkan Data',
            'Reorder' => 'Urutkan Data',
            // Bukan action standar Shield - custom buat ScheduleShiftTeam (2026-09-14), lihat
            // migration 2026_09_14_000000_seed_schedule_shift_team_assign_shift_types_permission.
            'AssignShiftTypes' => 'Atur Shift Tim',
        ];
    }

    /**
     * Container admin (dari navigationGroup Resource) yang dilebur JADI SATU dengan section
     * portal SISTEM_* senama — lihat pemakaian di configure() untuk penjelasan lengkap.
     * Dipakai juga oleh PermissionTreeBuilder (portal /hak-akses) supaya peleburan container
     * sama persis antara admin panel & portal.
     *
     * @return array<string, string>
     */
    public static function containerMerge(): array
    {
        return [
            'Keuangan' => CustomPermissionEnum::SISTEM_KEUANGAN->value,
            'Kepegawaian' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
            'LMS' => CustomPermissionEnum::SISTEM_LMS->value,
            'Presensi & Absensi' => CustomPermissionEnum::SISTEM_ABSENSI->value,
            'Penilaian Karyawan' => CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value,
            'Pembinaan Karyawan' => CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value,
            'Sikartap' => CustomPermissionEnum::SISTEM_SIKARTAP->value,
            'Rekrutmen' => CustomPermissionEnum::SISTEM_REKRUTMEN->value,
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

        if (in_array($permissionName, self::baseEmployeePermissions(), true)) {
            return 'sistem_dasar_karyawan_has_permissions';
        }

        $subject = self::customPermissionSubjectOverrides()[$permissionName]
            ?? explode(':', $permissionName)[1]
            ?? 'Lainnya';

        // Kalau container subject ini (navigationGroup Resource / subjectContainerOverrides)
        // dilebur ke section portal SISTEM_* lewat containerMerge(), field key-nya IKUT section
        // portal itu — bukan per-subject sendiri. Kalau tidak, checkbox prefill di EditRole
        // kosong walau role sudah punya permission-nya (field key beda antara render & prefill).
        $container = self::containerForSubject($subject);
        if ($container !== null && ($coarse = self::containerMerge()[$container] ?? null)) {
            return 'portal_' . Str::snake($coarse) . '_has_permissions';
        }

        return Str::snake($subject) . '_has_permissions';
    }

    /** Container (navigationGroup) sebuah subject: dari subjectContainerOverrides() dulu, lalu
     * scan Resource panel admin yang memakai model senama. Null kalau tidak ketemu / tak bergrup. */
    private static function containerForSubject(string $subject): ?string
    {
        if (isset(self::subjectContainerOverrides()[$subject])) {
            return self::subjectContainerOverrides()[$subject];
        }

        foreach (Filament::getPanel('admin')->getResources() as $resourceFqcn) {
            if (! method_exists($resourceFqcn, 'getModel') || class_basename($resourceFqcn::getModel()) !== $subject) {
                continue;
            }

            $group = $resourceFqcn::getNavigationGroup();

            if ($group === null) {
                return null;
            }

            return $group instanceof UnitEnum
                ? (method_exists($group, 'getLabel') ? $group->getLabel() : $group->name)
                : $group;
        }

        return null;
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
                        $actionLabels = self::actionLabels();

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

                        $subjectMeta = self::applySubjectOverrides($subjectMeta);

                        $labelFor = function (string $name) use ($actionLabels): string {
                            if ($custom = CustomPermissionEnum::tryFrom($name)) {
                                return $custom->label();
                            }

                            if ($widget = WidgetPermissionEnum::tryFrom($name)) {
                                return $widget->label();
                            }

                            [$action, $subject] = array_pad(explode(':', $name, 2), 2, null);

                            if ($subject === null) {
                                return Str::headline($name);
                            }

                            $subjectLabel = self::subjectLabelOverrides()[$subject] ?? Str::headline($subject);

                            return ($actionLabels[$action] ?? $action) . ' ' . $subjectLabel;
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
                        $containerMerge = self::containerMerge();
                        $mergedContainerByCoarse = array_flip($containerMerge);
                        $baseEmployeePermissions = self::baseEmployeePermissions();

                        $customSubjectOverrides = self::customPermissionSubjectOverrides();

                        $groups = $allPermissions
                            ->reject(fn ($permission) => in_array($permission->name, $portalPermissionNames, true))
                            ->reject(fn ($permission) => in_array($permission->name, $baseEmployeePermissions, true))
                            ->groupBy(fn ($permission) => $customSubjectOverrides[$permission->name] ?? explode(':', $permission->name)[1] ?? 'Lainnya');

                        // Urutan container ditampilkan
                        $containerOrder = [
                            'Kepegawaian', 'Penilaian Karyawan', 'Manajemen Organisasi',
                            'Manajemen Pengguna', 'Keuangan', 'LMS', 'Master',
                        ];

                        $containerSubSections = [];
                        $containerFieldKeys = []; // [container => [field_key => [semua nama permission di field itu]]]
                        // [subject => [label, fieldKey, options]] - dipakai additionalMergeContainers()
                        // buat BIKIN ULANG CheckboxList/Fieldset (bukan reuse object yang sama) di
                        // section tambahan - Filament schema component nyimpen back-reference ke
                        // parent container-nya sendiri, jadi 1 instance object cuma boleh nempel di
                        // 1 tempat; 2 instance beda dengan field key SAMA aman disinkronkan Livewire.
                        $subjectFieldMeta = [];
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

                                $fieldset = Fieldset::make($label)
                                    ->columnSpanFull()
                                    ->schema([$checkboxList]);

                                $containerSubSections[$container][] = $fieldset;
                                $subjectFieldMeta[$subject] = ['label' => $label, 'fieldKey' => $fieldKey, 'options' => $options];

                                continue;
                            }

                            // Fallback: permission yang Resource-nya belum punya navigationGroup
                            // (mis. Resource lepasan cluster yang belum di-set ulang grupnya) tetap
                            // tampil sebagai section berdiri sendiri, bukan hilang begitu saja.
                            $ungroupedSections[] = Section::make('Akses ' . $label)
                                ->description("Atur hak akses terkait {$label}.")
                                ->columnSpanFull()
                                ->collapsible()
                                ->collapsed()
                                ->schema([$checkboxList]);
                        }

                        // Reverse index additionalMergeContainers(): container label -> subject yang
                        // Fieldset-nya perlu nempel LAGI di situ, di luar container utamanya (lihat
                        // docblock method-nya - dipakai buat SikartapMcu yang relevan ke 2 modul).
                        $extraContainerSubjects = [];
                        foreach (self::additionalMergeContainers() as $extraSubject => $extraContainers) {
                            foreach ($extraContainers as $extraContainer) {
                                $extraContainerSubjects[$extraContainer][] = $extraSubject;
                            }
                        }

                        // Section portal SISTEM_* — gabungkan checkbox coarse+EDIT_* custom DENGAN
                        // seluruh Fieldset container admin senama (kalau ada, lihat $containerMerge
                        // di atas), supaya "Sistem Keuangan" jadi SATU section utuh, bukan dua
                        // section terpisah yang isinya tumpang tindih.
                        $portalSections = [];
                        foreach ($portalGroups as $coarseName => $childNames) {
                            $mergedContainer = $mergedContainerByCoarse[$coarseName] ?? null;
                            $mergedFieldKeys = $mergedContainer ? ($containerFieldKeys[$mergedContainer] ?? []) : [];

                            // Subject "numpang" (additionalMergeContainers()) yang Fieldset-nya juga
                            // harus muncul di section ini - field key-nya ikut ditambahkan ke
                            // $mergedFieldKeys supaya toggle "Pilih Semua" section ini ikut menghitung/
                            // mencentang permission itu juga, bukan cuma yang container utamanya.
                            $extraSubjectsHere = $mergedContainer ? ($extraContainerSubjects[$mergedContainer] ?? []) : [];
                            $extraFieldsets = [];
                            foreach ($extraSubjectsHere as $extraSubject) {
                                $meta = $subjectFieldMeta[$extraSubject] ?? null;

                                if (! $meta) {
                                    continue;
                                }

                                $extraFieldsets[] = Fieldset::make($meta['label'])
                                    ->columnSpanFull()
                                    ->schema([
                                        CheckboxList::make($meta['fieldKey'])
                                            ->label('Pilih Hak Akses')
                                            ->bulkToggleable()
                                            ->options($meta['options'])
                                            ->columns(2)
                                            ->disabled(fn ($get) => $get('name') === 'super_admin'),
                                    ]);

                                $mergedFieldKeys[$meta['fieldKey']] = array_keys($meta['options']);
                            }

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
                                    ...$extraFieldsets,
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

                        // Section "Sistem Dasar Karyawan" — akses menu dasar nav baris 1 portal
                        // (Tugas/Pelatihan/Pengajuan Absensi/Pembinaan Saya), 4 permission
                        // independen jadi 1 CheckboxList polos (bukan cascade gate kayak
                        // portalSections). Field key-nya HARUS 'sistem_dasar_karyawan_has_permissions'
                        // - sama persis fieldKeyFor(), kalau beda checkbox prefill EditRole kosong.
                        $baseEmployeeOptions = $allPermissions
                            ->whereIn('name', $baseEmployeePermissions)
                            ->mapWithKeys(fn ($permission) => [$permission->name => $labelFor($permission->name)])
                            ->all();

                        $baseEmployeeSections = $baseEmployeeOptions === [] ? [] : [
                            Section::make('Sistem Dasar Karyawan')
                                ->description('Akses menu dasar nav baris 1 portal karyawan (Tugas, Pelatihan, Pengajuan Absensi, Pembinaan Saya).')
                                ->collapsible()
                                ->collapsed()
                                ->columnSpanFull()
                                ->schema([
                                    CheckboxList::make('sistem_dasar_karyawan_has_permissions')
                                        ->label('Pilih Hak Akses')
                                        ->options($baseEmployeeOptions)
                                        ->columns(2)
                                        ->disabled(fn ($get) => $get('name') === 'super_admin'),
                                ]),
                        ];

                        array_push($groupSections, ...$baseEmployeeSections, ...$portalSections, ...$ungroupedSections);

                        return Section::make('Hak Akses')
                            ->description('Form ini digunakan untuk mengatur hak akses (permissions) yang dimiliki oleh role ini.')
                            ->columnSpanFull()
                            ->schema($groupSections);
                    })(),
            ]);
    }
}
