<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\UserKoperasi;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard ringkas modul Penggajian — MVP dari data yang SUDAH ada & jalan (PayrollPeriod/
 * Payroll/PayrollItem, Insentif, Koperasi). Sengaja TIDAK meniru mentah-mentah demo lama
 * (planning/web-attendance/demo/dashboard-finance.html) — pipeline approval 5-langkah/Jasa
 * Dokter terpisah/H2H bank di situ tidak ada padanan datanya sama sekali di sistem ini
 * (Jasa Dokter sudah diunifikasi ke siklus payroll biasa, tidak ada approval berjenjang buat
 * payroll). Pola tile+modal sama persis KepegawaianDashboardPage — reuse, bukan reinvent.
 */
#[Layout('components.layout', ['title' => 'Dashboard Penggajian'])]
class PayrollDashboardPage extends Component
{
    public ?string $companyId = null;
    public ?string $activeModal = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_DASHBOARD_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function openModal(string $modal): void
    {
        $this->activeModal = $modal;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    /** Periode gaji terbaru company terpilih — anchor seluruh angka di halaman ini. */
    public function getLatestPeriodProperty(): ?PayrollPeriod
    {
        if (! $this->companyId) {
            return null;
        }

        return PayrollPeriod::acrossCompanies()->where('company_id', $this->companyId)
            ->orderByDesc('start_date')
            ->first();
    }

    protected function periodPayrolls()
    {
        $period = $this->latestPeriod;

        return $period ? Payroll::where('payroll_period_id', $period->id) : Payroll::query()->whereRaw('1 = 0');
    }

    /** `net_salary` dienkripsi (PayrollEncrypted) - SUM SQL gak bisa jalan, jumlah di PHP. */
    public function getTotalNetPayrollProperty(): float
    {
        return (float) $this->periodPayrolls()->get()->sum('net_salary');
    }

    public function getPayrollCountProperty(): int
    {
        return $this->periodPayrolls()->count();
    }

    public function getPaidPercentProperty(): int
    {
        $total = $this->payrollCount;

        if ($total === 0) {
            return 0;
        }

        $paid = $this->periodPayrolls()->where('is_paid', true)->count();

        return (int) round($paid / $total * 100);
    }

    public function getUnpaidCountProperty(): int
    {
        return $this->periodPayrolls()->where('is_paid', false)->count();
    }

    /** Buat modal "Belum Dibayar" — maks. 20, urut net terbesar (paling berdampak duluan).
     * `net_salary` dienkripsi - sort/limit di PHP setelah get(), bukan SQL. */
    public function getUnpaidPayrollsProperty()
    {
        return $this->periodPayrolls()
            ->where('is_paid', false)
            ->with('user')
            ->get()
            ->sortByDesc('net_salary')
            ->take(20)
            ->values();
    }

    /** Top 8 komponen (earning+deduction dicampur, diurutkan nilai absolut) periode terpilih —
     * satu-satunya sumber "komposisi payroll" yang datanya benar-benar ada (PayrollItem nyata,
     * bukan proyeksi/mock). `amount` dienkripsi - groupBy/sum/sort semua di PHP, bukan SQL. */
    public function getComponentBreakdownProperty()
    {
        $period = $this->latestPeriod;

        if (! $period) {
            return collect();
        }

        $rows = PayrollItem::query()
            ->whereIn('payroll_id', Payroll::where('payroll_period_id', $period->id)->pluck('id'))
            ->get(['source_type', 'type', 'amount'])
            ->groupBy(fn ($item) => $item->source_type . '|' . $item->type)
            ->map(fn ($group, $groupKey) => (object) [
                'source_type' => explode('|', $groupKey, 2)[0],
                'type' => explode('|', $groupKey, 2)[1],
                'total' => $group->sum('amount'),
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values();

        $max = $rows->max('total') ?: 1;

        return $rows->map(function ($row) use ($max) {
            $component = PayrollComponentEnum::tryFrom($row->source_type);

            return [
                'label' => $component?->label() ?? $row->source_type,
                'type' => $row->type,
                'total' => (float) $row->total,
                'percent' => (int) round($row->total / $max * 100),
            ];
        });
    }

    /**
     * Tabel `incentives` cuma sumber data buat metode 'master' - company metode 'auto' dihitung
     * LIVE per user saat payroll di-generate (selisih Tunjangan Jabatan lama vs baru, tidak ada
     * baris master sama sekali), jadi card "Insentif Aktif" TIDAK PERNAH bisa merepresentasikan
     * company metode itu lewat query ini. Dulu diam-diam nunjukkan 0 orang/Rp0 - sekarang
     * di-flag eksplisit lewat getInsentifMetodeProperty(), blade nampilin pesan bukan angka
     * yang menyesatkan.
     */
    public function getInsentifMetodeProperty(): string
    {
        return (string) Setting::resolve('insentif_metode', $this->companyId, default: 'master');
    }

    protected function scopedIncentives()
    {
        return Incentive::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->where('is_active', true);
    }

    public function getActiveIncentiveCountProperty(): int
    {
        return $this->scopedIncentives()->count();
    }

    /** `amount` dienkripsi - SUM SQL gak bisa jalan, jumlah di PHP. */
    public function getActiveIncentiveTotalProperty(): float
    {
        return (float) $this->scopedIncentives()->get()->sum('amount');
    }

    /** Buat modal "Insentif Aktif". `amount` dienkripsi - sort/limit di PHP setelah get(). */
    public function getActiveIncentivesListProperty()
    {
        return $this->scopedIncentives()->with('user')->get()->sortByDesc('amount')->take(20)->values();
    }

    protected function scopedKoperasi()
    {
        return UserKoperasi::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->where('is_active', true);
    }

    public function getActiveKoperasiCountProperty(): int
    {
        return $this->scopedKoperasi()->count();
    }

    /** `amount` dienkripsi - SUM SQL gak bisa jalan, jumlah di PHP. */
    public function getActiveKoperasiTotalProperty(): float
    {
        return (float) $this->scopedKoperasi()->get()->sum('amount');
    }

    /** Buat modal "Anggota Koperasi Aktif". `amount` dienkripsi - sort/limit di PHP setelah get(). */
    public function getActiveKoperasiListProperty()
    {
        return $this->scopedKoperasi()->with('user')->get()->sortByDesc('amount')->take(20)->values();
    }

    /** Panel & modal "Periode Terbaru" — riwayat singkat, link ke halaman Periode Gaji buat detail penuh. */
    public function getRecentPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::acrossCompanies()->where('company_id', $this->companyId)
            ->withCount('payrolls')
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-dashboard-page');
    }
}
