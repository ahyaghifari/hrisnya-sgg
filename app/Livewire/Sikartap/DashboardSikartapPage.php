<?php

namespace App\Livewire\Sikartap;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Exports\Sikartap\SikartapPengajuanExport;
use App\Models\Master\Companies;
use App\Models\Sikartap\Sikartap;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Sikartap\SikartapPeriodeHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Dashboard Sikartap — satu-satunya landing page nav Sikartap (2026-08-25/26, instruksi user:
 * dropdown dibubarkan, halaman Pengajuan terpisah dilebur ke sini juga — "halaman pengajuan
 * itu harusnya ngga ada, disatukan aja ke dashboard"). Jadwal/Antrian MCU/Ide Inovasi/
 * Pengaturan tetap halaman terpisah, diakses lewat quick-link di halaman ini.
 *
 * Gate masuk = SISTEM_SIKARTAP (gerbang modul) - BUKAN salah satu dari permission widget,
 * supaya halaman ini tetap bisa dibuka (buat akses quick-link) walau role belum dikasih
 * permission widget manapun, cuma widget-nya yang kosong.
 *
 * Widget-nya sendiri tetap permission masing-masing (LIHAT_DASHBOARD_SIKARTAP_*, instruksi
 * user: "masing2 widget ini punya permission") - KECUALI widget tabel pengajuan, yang reuse
 * 'ViewAny:Sikartap' langsung (permission itu SUDAH pas buat visibility tabel ini, bikin
 * permission baru cuma bikin 2 permission ngontrol data yang sama - lihat canTabelPengajuan).
 */
#[Layout('components.layout', ['title' => 'Dashboard Sikartap'])]
class DashboardSikartapPage extends Component
{
    use WithPagination;

    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** 'berjalan' (≤3 bulan) | 'lama' (>3 bulan) | 'semua' — lihat SikartapPeriodeHelper. */
    #[Url]
    public string $periode = 'berjalan';

    /** Sub-tab batch, cuma relevan kalau periode='lama'. null = semua batch. */
    #[Url]
    public ?int $batch = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_SIKARTAP->value), 403);

        $this->companyId = $user->canFilterByCompany() ? null : $user->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function selectPeriode(string $periode): void
    {
        $this->periode = $periode;
        $this->batch = null;
        $this->resetPage();
    }

    public function selectBatch(?int $batch): void
    {
        $this->batch = $batch;
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanFilterByCompanyProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCanStatistikProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_STATISTIK->value);
    }

    public function getCanButuhTindakanProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_BUTUH_TINDAKAN->value);
    }

    public function getCanAntrianTahapProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_ANTRIAN_TAHAP->value);
    }

    /** Lihat detail penuh (semua kolom + tombol mata) - beda dari sekadar bisa input MCU. */
    public function getCanViewSikartapProperty(): bool
    {
        return Auth::user()->can('ViewAny:Sikartap');
    }

    public function getCanInputMcuProperty(): bool
    {
        return Auth::user()->canInputSikartapMcu() || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value);
    }

    /** Tabel muncul buat siapa saja yang punya salah satu - ViewAny:Sikartap (lihat penuh) ATAU
     * INPUT_MCU_* (staff MCU, kolomnya diperkecil - lihat canViewSikartap/canInputMcu di blade,
     * instruksi user 2026-08-26: "kalo dia hanya mcu brrti cuma 3 kolom"). */
    public function getCanTabelPengajuanProperty(): bool
    {
        return $this->canViewSikartap || $this->canInputMcu;
    }

    /** Quick-link permanen ke halaman existing (Jadwal/Ide Inovasi/Pengaturan) — dulu ada di
     * dropdown nav, sekarang nempel di dashboard karena dropdown-nya dibubarkan (instruksi user
     * 2026-08-25: "sikartap bukan dropdown lagi ... cukup 1 halaman dashboard"). Antrian MCU
     * TIDAK ada lagi di sini (2026-08-26) - sudah dilebur jadi widget tabel pengajuan di
     * halaman ini sendiri (lihat mcuActionsFor()), bukan halaman terpisah lagi.
     *
     * @return array<int, array{label:string,icon:string,url:string}>
     */
    public function getQuickLinksProperty(): array
    {
        $user = Auth::user();
        $links = [];

        if ($user->can('ViewAny:Sikartap')) {
            $links[] = ['label' => 'Jadwal', 'icon' => 'fa-calendar-days', 'url' => route('sikartap.jadwal')];
        }

        $links[] = ['label' => 'Ide Inovasi', 'icon' => 'fa-lightbulb', 'url' => route('sikartap.ide-inovasi.atasan.queue')];

        if ($user->can(CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value)) {
            $links[] = ['label' => 'Pengaturan', 'icon' => 'fa-sliders', 'url' => route('sikartap.pengaturan')];
        }

        return $links;
    }

    private function scopedQuery()
    {
        $query = Sikartap::query()->with(['user.company', 'ideInovasi']);

        if ($this->companyId) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId));
        } elseif (! Auth::user()->canFilterByCompany()) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', Auth::user()->company_id));
        }

        return $query;
    }

    /** @return array{total:int,pending:int,approved:int,rejected:int} */
    public function getStatistikProperty(): array
    {
        if (! $this->canStatistik) {
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        }

        $all = $this->scopedQuery()->get(['id', 'status_keputusan_direktur', 'is_diskualifikasi']);

        $approved = $all->where('status_keputusan_direktur', 'approved')->count();
        $rejected = $all->filter(fn (Sikartap $s) => $s->status_keputusan_direktur === 'rejected' || $s->is_diskualifikasi)->count();

        return [
            'total' => $all->count(),
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $all->count() - $approved - $rejected,
        ];
    }

    /** Ringkasan jumlah pengajuan yang nyangkut di tiap tahap — overview lintas company/unit,
     * BUKAN daftar personal (beda dari widget Butuh Tindakan). */
    public function getAntrianTahapProperty(): array
    {
        if (! $this->canAntrianTahap) {
            return [];
        }

        $all = $this->scopedQuery()->get();

        return [
            'tes' => $all->filter(fn (Sikartap $s) => $s->status_berkas === 'approved' && $s->status_tes === 'pending')->count(),
            'wawancara' => $all->filter(fn (Sikartap $s) => $s->status_tes === 'lulus' && $s->status_wawancara === 'pending')->count(),
            'ide_inovasi' => $all->filter(fn (Sikartap $s) => $s->status_wawancara === 'disarankan' && $s->requiresIdeInovasi() && ($s->ideInovasi?->status_atasan ?? 'pending') === 'pending')->count(),
            'mcu' => $all->filter(fn (Sikartap $s) => $s->status_wawancara === 'disarankan' && $s->requiresMcu() && $s->ideInovasiApproved() && $s->status_mcu === 'pending')->count(),
            'keputusan_direktur' => $all->filter(fn (Sikartap $s) => $s->isEligibleForDirectorDecision() && $s->status_keputusan_direktur === 'pending')->count(),
        ];
    }

    /** Daftar personal "butuh tindakan Anda" — tiap baris dicek gate aslinya masing-masing
     * (Update:Sikartap, INPUT_MCU_* per role staff, canReviewIdeInovasiAsAtasan,
     * ApprovalPermissionService), pola sama PendingApprovalsCard::buildRequests() tapi khusus
     * modul Sikartap. Link "Lihat semua" ke widget tabel pengajuan di halaman ini sendiri
     * (anchor #tabel-pengajuan) atau halaman terkait lain (Antrian MCU/Ide Inovasi/dst).
     */
    public function getButuhTindakanProperty(): array
    {
        if (! $this->canButuhTindakan) {
            return [];
        }

        $user = Auth::user();
        $items = [];

        if ($user->can('Update:Sikartap')) {
            $items['berkas'] = [
                'label' => 'Verifikasi Berkas',
                'icon' => 'fa-file-signature',
                'count' => $this->scopedQuery()->where('status_berkas', 'pending')->count(),
                'url' => '#tabel-pengajuan',
            ];
        }

        // Antrian MCU dipecah per role (staff lab cuma peduli "belum upload hasil lab", bukan
        // total MCU pending semua) - pola sama dashboard_staff_lab.php/dashboard_dokter.php
        // legacy yang masing-masing punya stat "Belum Upload/Belum Diperiksa" sendiri.
        $mcuRoles = [
            'mcu_perawat' => [CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT, 'mcuNurseAssessment', 'Asesmen Perawat', 'fa-user-nurse'],
            'mcu_lab' => [CustomPermissionEnum::INPUT_MCU_LABORATORIUM, 'mcuLabResult', 'Hasil Laboratorium', 'fa-flask-vial'],
            'mcu_radiologi' => [CustomPermissionEnum::INPUT_MCU_RADIOLOGI, 'mcuRadiologyResult', 'Hasil Radiologi', 'fa-x-ray'],
            'mcu_dokter' => [CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER, 'mcuDoctorExamination', 'Pemeriksaan Dokter', 'fa-user-doctor'],
        ];

        $mcuEligible = null;

        foreach ($mcuRoles as $key => [$permission, $relation, $label, $icon]) {
            if (! $user->can($permission->value)) {
                continue;
            }

            $mcuEligible ??= $this->scopedQuery()
                ->with(['mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination'])
                ->get()
                ->filter(fn (Sikartap $s) => $s->status_wawancara === 'disarankan' && $s->requiresMcu() && $s->ideInovasiApproved() && $s->status_mcu === 'pending');

            $count = $mcuEligible->filter(fn (Sikartap $s) => $s->{$relation} === null)->count();

            if ($count > 0) {
                $items[$key] = [
                    'label' => 'MCU - ' . $label,
                    'icon' => $icon,
                    'count' => $count,
                    'url' => '#tabel-pengajuan',
                ];
            }
        }

        $ideInovasiCount = $this->scopedQuery()->get()
            ->filter(fn (Sikartap $s) => ($s->ideInovasi?->status_atasan ?? null) === 'pending' && $s->canReviewIdeInovasiAsAtasan($user))
            ->count();

        if ($ideInovasiCount > 0) {
            $items['ide_inovasi'] = [
                'label' => 'Nilai Ide Inovasi',
                'icon' => 'fa-lightbulb',
                'count' => $ideInovasiCount,
                'url' => route('sikartap.ide-inovasi.atasan.queue'),
            ];
        }

        if ($user->can('View:Sikartap')) {
            $direkturCount = app(ApprovalPermissionService::class)
                ->pendingApprovalsFor($user, ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value)
                ->count();

            if ($direkturCount > 0) {
                $items['keputusan_direktur'] = [
                    'label' => 'Keputusan Direktur',
                    'icon' => 'fa-gavel',
                    'count' => $direkturCount,
                    'url' => route('sikartap.direktur.bulk'),
                ];
            }
        }

        return $items;
    }

    // ── Widget tabel pengajuan (dilebur dari PengajuanListPage, instruksi user 2026-08-26) ──

    /** company+search saja, TANPA periode/batch — dipakai baseQueryTabel() dan buat hitung
     * pill jumlah tiap tab periode (harus konsisten sama filter company/search aktif). */
    private function scopedQueryTabel()
    {
        $query = Sikartap::query()->with(['user.company', 'ideInovasi', 'mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination']);

        if (Auth::user()->canFilterByCompany()) {
            if ($this->companyId) {
                $query->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId));
            }
        } else {
            $query->whereHas('user', fn ($q) => $q->where('company_id', Auth::user()->company_id));
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%"));
        }

        return $query;
    }

    private function baseQueryTabel()
    {
        $query = $this->scopedQueryTabel();

        // Staff MCU-only tidak lihat tab periode sama sekali (UI-nya disembunyikan, kolomnya
        // cuma 3) - jangan diam-diam filter ke periode berjalan, tampilkan semua yang bisa dia
        // aksi tanpa syarat periode, sama seperti McuQueuePage lama.
        if (! $this->canViewSikartap) {
            return $query;
        }

        if ($this->periode !== 'semua') {
            $batas = SikartapPeriodeHelper::batasPeriode();
            $query->when(
                $this->periode === 'lama',
                fn ($q) => $q->where('created_at', '<', $batas),
                fn ($q) => $q->where('created_at', '>=', $batas),
            );
        }

        if ($this->periode === 'lama' && $this->batch !== null) {
            $query->whereBetween('created_at', [
                SikartapPeriodeHelper::batasBatch($this->batch + 1),
                SikartapPeriodeHelper::batasBatch($this->batch),
            ]);
        }

        return $query;
    }

    /** @return array{berjalan:int,lama:int,semua:int} */
    public function getPeriodeCountsProperty(): array
    {
        $batas = SikartapPeriodeHelper::batasPeriode();
        $semua = $this->scopedQueryTabel()->count();
        $lama = $this->scopedQueryTabel()->where('created_at', '<', $batas)->count();

        return ['berjalan' => $semua - $lama, 'lama' => $lama, 'semua' => $semua];
    }

    /** Daftar batch di dalam periode "lama" beserta jumlahnya - dihitung cuma kalau tab "lama"
     * sedang aktif (hemat query pas tab lain aktif). */
    public function getBatchesProperty(): array
    {
        if ($this->periode !== 'lama') {
            return [];
        }

        $batas = SikartapPeriodeHelper::batasPeriode();
        $tanggalLama = $this->scopedQueryTabel()
            ->where('created_at', '<', $batas)
            ->pluck('created_at');

        $counts = $tanggalLama
            ->map(fn ($tanggal) => SikartapPeriodeHelper::nomorBatch($tanggal))
            ->countBy()
            ->sortKeys();

        return $counts->map(fn ($count, $n) => [
            'n' => $n,
            'count' => $count,
            'label' => SikartapPeriodeHelper::labelRentangBatch($n),
        ])->values()->all();
    }

    public function getPengajuanProperty()
    {
        $appends = [
            'companyId' => $this->companyId,
            'search' => $this->search,
            'periode' => $this->periode,
            'batch' => $this->batch,
        ];

        if ($this->canViewSikartap) {
            return $this->baseQueryTabel()->latest()->paginate(10)->appends($appends);
        }

        // Staff MCU-only cuma peduli baris yang beneran bisa dia aksi (sama semantik
        // McuQueuePage lama) - butuh dihitung di PHP (requiresMcu()/ideInovasiApproved() baca
        // Setting per company, bukan kolom), jadi paginasi manual bukan lewat SQL LIMIT/OFFSET.
        $eligible = $this->baseQueryTabel()->latest()->get()
            ->filter(fn (Sikartap $s) => count($this->mcuActionsFor($s)) > 0)
            ->values();

        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage('page');

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $eligible->forPage($page, 10)->values(),
            $eligible->count(),
            10,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $appends],
        );
    }

    /** Tombol Aksi MCU per baris (icon-only, instruksi user 2026-08-26) - cuma muncul kalau
     * baris itu memang lagi nunggu MCU (status_wawancara disarankan, wajib MCU, ide inovasi
     * approved, status_mcu pending) DAN staff-nya belum isi bagian yang jadi tanggung jawabnya.
     * 1 baris bisa punya lebih dari 1 tombol kalau staff punya lebih dari 1 permission INPUT_MCU_*.
     *
     * @return array<int, array{url:string,icon:string,title:string}>
     */
    public function mcuActionsFor(Sikartap $item): array
    {
        if (! $this->canInputMcu) {
            return [];
        }

        $eligible = $item->status_wawancara === 'disarankan' && $item->requiresMcu() && $item->ideInovasiApproved() && $item->status_mcu === 'pending';

        if (! $eligible) {
            return [];
        }

        $user = Auth::user();
        $mcuRoles = [
            [CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT, 'mcuNurseAssessment', 'sikartap.mcu.perawat', 'Asesmen Perawat', 'fa-user-nurse'],
            [CustomPermissionEnum::INPUT_MCU_LABORATORIUM, 'mcuLabResult', 'sikartap.mcu.laboratorium', 'Hasil Laboratorium', 'fa-flask-vial'],
            [CustomPermissionEnum::INPUT_MCU_RADIOLOGI, 'mcuRadiologyResult', 'sikartap.mcu.radiologi', 'Hasil Radiologi', 'fa-x-ray'],
            [CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER, 'mcuDoctorExamination', 'sikartap.mcu.dokter', 'Pemeriksaan Dokter', 'fa-user-doctor'],
        ];

        $canEdit = $user->can(CustomPermissionEnum::EDIT_INPUT_MCU->value);
        $actions = [];

        foreach ($mcuRoles as [$permission, $relation, $routeName, $title, $icon]) {
            $sudahDiisi = $item->{$relation} !== null;

            if ($sudahDiisi ? $canEdit : $user->can($permission->value)) {
                $actions[] = [
                    'url' => route($routeName, $item),
                    'icon' => $sudahDiisi ? 'fa-pen' : $icon,
                    'title' => ($sudahDiisi ? 'Edit ' : '') . $title,
                ];
            }
        }

        return $actions;
    }

    /** Export Excel data pengajuan sesuai filter aktif saat tombol diklik (company/search/
     * periode/batch) - reuse baseQueryTabel() sama pola KaryawanPage::export(). Cuma buat
     * user dengan ViewAny:Sikartap - staff MCU-only tidak butuh export data lintas tahap. */
    public function export()
    {
        abort_unless($this->canViewSikartap, 403);

        $pengajuan = $this->baseQueryTabel()->latest()->get();

        return Excel::download(
            new SikartapPengajuanExport($pengajuan),
            'sikartap-pengajuan-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.sikartap.dashboard-sikartap-page');
    }
}
