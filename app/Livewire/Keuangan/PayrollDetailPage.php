<?php

namespace App\Livewire\Keuangan;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Jobs\SendPayrollSlipJob;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Setting;
use App\Notifications\PayrollPaid;
use App\Services\Payroll\PayrollService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Detail Payroll'])]
class PayrollDetailPage extends Component
{
    public Payroll $payroll;

    public bool $showManualLineModal = false;
    public ?string $manualLineComponent = null; // 'bon' - satu-satunya manual line yang tersisa (Insentif import sudah dihapus)
    public ?string $editingManualLineId = null;
    public ?string $manualLineAmount = null;
    public ?string $manualLineNote = null;

    public function mount(Payroll $payroll): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->payroll = $payroll->load(['user', 'payrollPeriod.company', 'items']);
    }

    /** @return array{earnings: array, deductions: array} */
    public function getComponentRowsProperty(): array
    {
        return app(PayrollService::class)->componentRows($this->payroll);
    }

    /**
     * Metode Izin Seharian "gaji_pokok" diam-diam jadi Rp0 kalau karyawan itu punya izin tapi
     * TIDAK punya PayrollItem Gaji Pokok (pangkat/kelas kosong, atau tidak terdaftar sebagai
     * penerima Gaji Pokok) - lihat IzinSeharianGajiPokokCalculator::rateHarian(). Bukan bug
     * (tidak ada dasar hitung ya tidak dipotong), tapi berbahaya kalau senyap: finance bisa
     * kelewat karyawan yang izin berkali-kali tapi tidak pernah kepotong. Munculin sebagai
     * warning eksplisit di halaman detail, BUKAN di slip PDF (per keputusan user).
     */
    public function getIzinSeharianGajiPokokWarningProperty(): ?string
    {
        $companyId = $this->payroll->payrollPeriod->company_id;
        $metode = Setting::resolve('izin_seharian_metode', $companyId, default: 'keseluruhan');

        if ($metode !== 'gaji_pokok') {
            return null;
        }

        $period = $this->payroll->payrollPeriod;

        $izinCount = AttendanceRequests::query()
            ->where('user_id', $this->payroll->user_id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date])
                    ->where('category', PermissionTypeCategoryEnum::IZIN_SEHARIAN->value);
            })
            ->count();

        if ($izinCount <= 0) {
            return null;
        }

        $hasGajiPokok = $this->payroll->items()
            ->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)
            ->exists();

        if ($hasGajiPokok) {
            return null;
        }

        return "Karyawan ini punya {$izinCount} kejadian Izin Seharian di periode ini, tapi TIDAK ada potongan - metode Izin Seharian company ini \"Gaji Pokok\", dan karyawan ini belum punya baris Gaji Pokok (cek Pangkat/Kelas di profil, atau status penerima Gaji Pokok di halaman Penerima Komponen Gaji).";
    }

    /**
     * Rincian perhitungan Gaji Pokok (base + kenaikan kelas + prorate bulan pertama kalau kena) -
     * GajiPokokCalculator sudah nulis ini ke PayrollItem->note, tapi PayrollService::componentRows()
     * sengaja TIDAK membacanya (note itu juga dipakai slip PDF, dan detail prorate ini per
     * keputusan user cukup di halaman sistem, tidak usah di PDF). Jadi dibaca langsung di sini,
     * ditampilkan cuma di halaman detail, bukan lewat componentRows().
     */
    public function getGajiPokokNoteProperty(): ?string
    {
        return $this->payroll->items()
            ->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)
            ->value('note');
    }

    /**
     * Tombol "+ Tambah" di baris Bon cuma tampil kalau LOLOS DUA gate — sama persis yang dicek
     * PayrollService::runComponent() sebelum Calculator jalan: (1) komponen Bon aktif di
     * Komponen Gaji untuk company ini, DAN (2) tipe karyawan pemilik payroll ini terdaftar
     * sebagai penerima Bon di halaman "Penerima Komponen Gaji". Kalau cuma cek nomor (1),
     * admin bisa nambah baris manual buat karyawan yang tipe-nya tidak terdaftar sebagai
     * penerima — baris itu tersimpan tapi tidak akan PERNAH muncul di payroll walau di-generate
     * ulang berkali-kali (BonCalculator tidak akan dipanggil sama sekali untuk karyawan itu).
     */
    public function getBonAktifProperty(): bool
    {
        return $this->componentEligibleForThisEmployee(PayrollComponentEnum::BON->value);
    }

    /** "Simple" = 1 baris nominal per periode (default, seperti sebelumnya). "Detail" = boleh
     *  banyak baris rincian potongan - lihat guard di saveManualLine(). */
    public function getBonMetodeProperty(): string
    {
        return (string) Setting::resolve('bon_metode', $this->payroll->payrollPeriod->company_id, default: 'simple');
    }

    private function componentEligibleForThisEmployee(string $component): bool
    {
        $companyId = $this->payroll->payrollPeriod->company_id;

        $active = CompanyPayrollComponent::query()
            ->where('company_id', $companyId)
            ->where('component', $component)
            ->where('is_active', true)
            ->exists();

        if (! $active) {
            return false;
        }

        return PayrollComponentRecipient::isEligible($companyId, $component, $this->payroll->user->profile?->employee_type_id);
    }

    /** Baris Bon manual punya karyawan ini di periode ini — ditampilkan dengan aksi ubah/hapus. */
    public function getBonManualLinesProperty()
    {
        return $this->manualLinesFor(PayrollComponentEnum::BON->value);
    }

    private function manualLinesFor(string $component)
    {
        return PayrollManualLine::query()
            ->where('payroll_period_id', $this->payroll->payroll_period_id)
            ->where('user_id', $this->payroll->user_id)
            ->where('component', $component)
            ->latest()
            ->get();
    }

    public function getCanEditPayrollProperty(): bool
    {
        return Auth::user()->can('update', $this->payroll);
    }

    public function markAsPaid(): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);

        $this->payroll->update(['is_paid' => true]);
        $this->payroll->refresh();

        $this->payroll->user?->notify(new PayrollPaid($this->payroll));

        Notification::make()
            ->title('Payroll ditandai sudah dibayar')
            ->success()
            ->send();
    }

    public function downloadSlip()
    {
        return app(PayrollService::class)->download($this->payroll);
    }

    /** Cuma boleh dikirim setelah ditandai dibayar — sama gate-nya dengan tombol di halaman list. */
    public function sendSlipEmail(): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);
        abort_unless($this->payroll->is_paid, 403, 'Payroll harus ditandai sudah dibayar dulu sebelum slip bisa dikirim.');

        if (! $this->payroll->user->email) {
            Notification::make()
                ->title('Karyawan ini belum punya email')
                ->danger()
                ->send();

            return;
        }

        SendPayrollSlipJob::dispatch($this->payroll->id);

        Notification::make()
            ->title('Slip gaji sedang dikirim')
            ->body('Diproses via queue — email akan terkirim dalam beberapa saat.')
            ->success()
            ->send();
    }

    public function openManualLineModal(string $component, ?string $lineId = null): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);

        $this->manualLineComponent = $component;
        $this->editingManualLineId = $lineId;

        if ($lineId) {
            $line = PayrollManualLine::where('id', $lineId)
                ->where('user_id', $this->payroll->user_id)
                ->where('payroll_period_id', $this->payroll->payroll_period_id)
                ->firstOrFail();
            $this->manualLineAmount = (string) $line->amount;
            $this->manualLineNote = $line->note;
        } else {
            $this->manualLineAmount = null;
            $this->manualLineNote = null;
        }

        $this->resetErrorBag();
        $this->showManualLineModal = true;
    }

    public function closeManualLineModal(): void
    {
        $this->showManualLineModal = false;
    }

    public function saveManualLine(): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa diubah.');
        abort_unless($this->componentEligibleForThisEmployee($this->manualLineComponent), 403, 'Tipe karyawan ini belum terdaftar sebagai penerima komponen ini di halaman Penerima Komponen Gaji.');

        $this->validate([
            'manualLineAmount' => 'required|numeric|min:0.01',
        ], [], [
            'manualLineAmount' => 'nominal',
        ]);

        $label = 'Bon';

        if ($this->editingManualLineId) {
            PayrollManualLine::where('id', $this->editingManualLineId)
                ->where('user_id', $this->payroll->user_id)
                ->where('payroll_period_id', $this->payroll->payroll_period_id)
                ->firstOrFail()
                ->update([
                    'amount' => $this->manualLineAmount,
                    'note' => $this->manualLineNote,
                ]);
        } else {
            // Bon mode "Detail" boleh banyak baris (rincian potongan satu-satu), mode "Simple"
            // tetap dibatasi 1 baris per periode seperti sebelumnya. Guard ini murni di kode -
            // constraint unique DB sudah dilonggarkan (migration 2026_08_01_160000_*) supaya
            // Bon Detail bisa insert banyak baris.
            if ($this->bonMetode !== 'detail') {
                abort_if($this->manualLinesFor($this->manualLineComponent)->isNotEmpty(), 403, "Karyawan ini sudah punya baris {$label} untuk periode ini — ubah baris yang ada, bukan menambah baru.");
            }

            PayrollManualLine::create([
                'payroll_period_id' => $this->payroll->payroll_period_id,
                'user_id' => $this->payroll->user_id,
                'component' => $this->manualLineComponent,
                'amount' => $this->manualLineAmount,
                'note' => $this->manualLineNote,
                'created_by' => Auth::id(),
            ]);
        }

        $this->showManualLineModal = false;

        Notification::make()
            ->title($this->editingManualLineId ? "{$label} berhasil diubah" : "{$label} berhasil dicatat")
            ->body('Klik "Regenerate Payroll Ini" supaya nominalnya masuk ke payroll.')
            ->success()
            ->send();
    }

    public function deleteManualLine(string $lineId): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa diubah.');

        PayrollManualLine::where('id', $lineId)
            ->where('user_id', $this->payroll->user_id)
            ->where('payroll_period_id', $this->payroll->payroll_period_id)
            ->delete();

        Notification::make()
            ->title('Berhasil dihapus')
            ->body('Klik "Regenerate Payroll Ini" supaya perubahannya masuk ke payroll.')
            ->success()
            ->send();
    }

    /** Regenerate payroll SATU karyawan ini saja — ID payroll berubah, jadi redirect ke yang baru. */
    public function regeneratePayroll()
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa di-regenerate.');

        $newPayroll = app(PayrollService::class)->regenerateSinglePayroll($this->payroll, Auth::id());

        Notification::make()
            ->title('Payroll berhasil di-regenerate')
            ->success()
            ->send();

        return $this->redirect(route('keuangan.payroll.show', $newPayroll), navigate: true);
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-detail-page');
    }
}
