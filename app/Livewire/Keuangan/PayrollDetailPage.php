<?php

namespace App\Livewire\Keuangan;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Jobs\SendPayrollSlipJob;
use App\Models\AuditLog;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
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
    /** Nama tampilan baris ini di slip (jadi payroll_items.name) - cuma dipakai & wajib diisi
     * untuk Bon mode Detail, lihat getBonMetodeProperty(). */
    public ?string $manualLineLabel = null;

    // ── Edit langsung per komponen (bebas komponen apa saja, lihat openComponentEditModal()) ──
    public bool $showComponentEditModal = false;
    public ?string $editingComponent = null;
    public ?string $editingComponentLabel = null;
    public ?string $componentEditAmount = null;
    public ?string $componentEditNote = null;

    public bool $showCancelPaidModal = false;
    public ?string $cancelPaidReason = null;

    public bool $showPaymentHistoryModal = false;

    public function mount(Payroll $payroll): void
    {
        abort_unless(Auth::user()->can('view', $payroll), 403);

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

    /** Periode ini bukan lagi periode terbaru company-nya (sudah ada periode yang dimulai setelah
     * periode payroll ini) — regenerate penuh dari sini ditutup, biar tidak menimpa histori pakai
     * config yang sudah berubah sejak saat itu. Koreksi buat periode lampau lewat edit manual per
     * baris di admin panel (`PayrollItemsRelationManager`), bukan hitung ulang total. */
    public function getPeriodHasEndedProperty(): bool
    {
        return PayrollPeriod::query()
            ->where('company_id', $this->payroll->payrollPeriod->company_id)
            ->where('start_date', '>', $this->payroll->payrollPeriod->start_date)
            ->exists();
    }

    /** Sama isinya dengan section "Riwayat Pembayaran" di admin panel (PayrollInfolist) - halaman
     * ini digerbangi permission Shield View:Payroll (lihat PayrollPolicy::view), karyawan lihat
     * slip sendiri lewat route/komponen terpisah (MyPayrollSlipPage), jadi tidak perlu gerbang
     * tambahan supaya "karyawan tidak bisa lihat" - itu sudah otomatis lewat pemisahan halaman. */
    public function getPaymentHistoryProperty()
    {
        return AuditLog::where('key', 'payroll:' . $this->payroll->id)
            ->where('module', 'payroll_payment')
            ->orderByDesc('created_at')
            ->with('user')
            ->get();
    }

    public function openPaymentHistoryModal(): void
    {
        $this->showPaymentHistoryModal = true;
    }

    public function closePaymentHistoryModal(): void
    {
        $this->showPaymentHistoryModal = false;
    }

    /** Gerbang KHUSUS baris penyesuaian manual (Bon) — lebih sempit dari canEditPayroll (dipakai
     * juga oleh markAsPaid/regenerate/dst), supaya bisa dikasih ke lebih sedikit orang. */
    public function getCanEditAdjustmentProperty(): bool
    {
        return $this->canEditPayroll && Auth::user()->can(CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT->value);
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

    public function openCancelPaidModal(): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);

        $this->cancelPaidReason = null;
        $this->resetErrorBag();
        $this->showCancelPaidModal = true;
    }

    public function closeCancelPaidModal(): void
    {
        $this->showCancelPaidModal = false;
    }

    /** Kebalikan markAsPaid — buka lagi gerbang is_paid supaya adjustment/regenerate bisa dipakai
     * lagi (mis. salah tandai, atau ada koreksi nominal setelah terlanjur dibayar). Tidak
     * menyentuh slip_sent_at - riwayat kapan slip terakhir terkirim tetap ada sebagai jejak, dan
     * notifikasi "Gaji Sudah Dibayar" yang sudah terkirim TIDAK ditarik balik. Padanan portal
     * dari aksi "cancelPaid" di admin panel (PayrollsTable) - audit log module sama supaya
     * riwayatnya menyatu di satu tempat terlepas dari lewat mana dibatalkannya. Alasan wajib
     * diisi (sama pola dengan Catatan di saveManualLine/PayrollItemsRelationManager), disimpan
     * di label AuditLog - bukan kolom baru, karena riwayatnya sudah cukup lewat audit_logs. */
    public function unmarkAsPaid(): void
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);

        $this->validate([
            'cancelPaidReason' => 'required|string|max:500',
        ], [], ['cancelPaidReason' => 'alasan pembatalan']);

        $this->payroll->update(['is_paid' => false]);
        $this->payroll->refresh();

        AuditLog::record(
            'payroll_payment',
            $this->payroll->payrollPeriod->company_id,
            'payroll:' . $this->payroll->id,
            $this->payroll->user->name . ' — ' . $this->payroll->payrollPeriod->start_date->format('M Y') . ' (' . $this->cancelPaidReason . ')',
            'Sudah Dibayar',
            'Dibatalkan'
        );

        $this->showCancelPaidModal = false;

        Notification::make()
            ->title('Tanda sudah dibayar dibatalkan')
            ->body('Payroll ini sekarang bisa diubah/di-regenerate lagi.')
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

        // Sama fallback dengan SendPayrollSlipJob - email_pribadi diprioritaskan, baru email akun.
        if (! ($this->payroll->user->profile?->email_pribadi ?: $this->payroll->user->email)) {
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
        abort_unless($this->canEditAdjustment, 403);

        $this->manualLineComponent = $component;
        $this->editingManualLineId = $lineId;

        if ($lineId) {
            $line = PayrollManualLine::where('id', $lineId)
                ->where('user_id', $this->payroll->user_id)
                ->where('payroll_period_id', $this->payroll->payroll_period_id)
                ->firstOrFail();
            $this->manualLineAmount = (string) $line->amount;
            $this->manualLineNote = $line->note;
            $this->manualLineLabel = $line->label;
        } else {
            $this->manualLineAmount = null;
            $this->manualLineNote = null;
            $this->manualLineLabel = null;
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
        abort_unless($this->canEditAdjustment, 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa diubah.');
        abort_if($this->periodHasEnded, 403, 'Periode ini sudah bukan periode terbaru — baris manual di sini cuma masuk ke payroll lewat Regenerate, dan itu sudah ditutup untuk periode lampau. Edit manual per baris lewat admin panel (Daftar Payroll > buka payroll ini > tab Payroll Items).');
        abort_unless($this->componentEligibleForThisEmployee($this->manualLineComponent), 403, 'Tipe karyawan ini belum terdaftar sebagai penerima komponen ini di halaman Penerima Komponen Gaji.');

        // Nama Potongan cuma wajib untuk Bon mode Detail (jadi payroll_items.name, ganti "Bon"
        // generik) - Koperasi & Bon Simple tetap fallback ke label komponen bawaan, tidak perlu
        // diisi (lihat AbstractManualLineCalculator).
        $labelRequired = $this->manualLineComponent === PayrollComponentEnum::BON->value && $this->bonMetode === 'detail';

        $this->validate([
            'manualLineAmount' => 'required|numeric|min:0.01',
            'manualLineNote' => 'required|string|max:500',
            'manualLineLabel' => $labelRequired ? 'required|string|max:150' : 'nullable|string|max:150',
        ], [], [
            'manualLineAmount' => 'nominal',
            'manualLineNote' => 'catatan',
            'manualLineLabel' => 'nama potongan',
        ]);

        $label = 'Bon';

        if ($this->editingManualLineId) {
            $line = PayrollManualLine::where('id', $this->editingManualLineId)
                ->where('user_id', $this->payroll->user_id)
                ->where('payroll_period_id', $this->payroll->payroll_period_id)
                ->firstOrFail();

            $oldAmount = (string) $line->amount;

            $line->update([
                'amount' => $this->manualLineAmount,
                'note' => $this->manualLineNote,
                'label' => $this->manualLineLabel,
            ]);

            $this->recordAdjustmentAudit($line->id, $oldAmount, (string) $this->manualLineAmount);
        } else {
            // Bon mode "Detail" boleh banyak baris (rincian potongan satu-satu), mode "Simple"
            // tetap dibatasi 1 baris per periode seperti sebelumnya. Guard ini murni di kode -
            // constraint unique DB sudah dilonggarkan (migration 2026_08_01_160000_*) supaya
            // Bon Detail bisa insert banyak baris.
            if ($this->bonMetode !== 'detail') {
                abort_if($this->manualLinesFor($this->manualLineComponent)->isNotEmpty(), 403, "Karyawan ini sudah punya baris {$label} untuk periode ini — ubah baris yang ada, bukan menambah baru.");
            }

            $line = PayrollManualLine::create([
                'payroll_period_id' => $this->payroll->payroll_period_id,
                'user_id' => $this->payroll->user_id,
                'component' => $this->manualLineComponent,
                'amount' => $this->manualLineAmount,
                'note' => $this->manualLineNote,
                'label' => $this->manualLineLabel,
                'created_by' => Auth::id(),
            ]);

            $this->recordAdjustmentAudit($line->id, null, (string) $this->manualLineAmount);
        }

        $this->showManualLineModal = false;

        Notification::make()
            ->title($this->editingManualLineId ? "{$label} berhasil diubah" : "{$label} berhasil dicatat")
            ->body('Klik "Regenerate Payroll Ini" supaya nominalnya masuk ke payroll.')
            ->success()
            ->send();
    }

    /**
     * Edit langsung 1 komponen APAPUN (bukan cuma Bon/manual line) - permintaan user, tombol
     * "Ubah" muncul di setiap baris pendapatan/potongan yang punya `component` (source_type)
     * valid. BEDA dari manual line: nulis LANGSUNG ke payroll_items lewat
     * PayrollService::applyManualRevision() (tidak lewat payroll_manual_lines), jadi TIDAK
     * butuh Regenerate buat masuk efeknya - sekaligus TIDAK kena gerbang periodHasEnded (yang
     * sebelumnya cuma relevan buat manual line yang efeknya baru masuk lewat Regenerate).
     */
    public function openComponentEditModal(string $component): void
    {
        abort_unless($this->canEditAdjustment, 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa diubah.');

        $enum = PayrollComponentEnum::tryFrom($component);
        abort_if($enum === null, 404);

        $item = $this->payroll->items()->where('source_type', $component)->first();

        $this->editingComponent = $component;
        $this->editingComponentLabel = $enum->label();
        $this->componentEditAmount = $item ? (string) $item->amount : '0';
        $this->componentEditNote = null;

        $this->resetErrorBag();
        $this->showComponentEditModal = true;
    }

    public function closeComponentEditModal(): void
    {
        $this->showComponentEditModal = false;
    }

    public function saveComponentEdit(): void
    {
        abort_unless($this->canEditAdjustment, 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa diubah.');
        abort_if($this->editingComponent === null, 400);

        $this->validate([
            'componentEditAmount' => 'required|numeric|min:0',
            'componentEditNote' => 'required|string|max:500',
        ], [], [
            'componentEditAmount' => 'nominal',
            'componentEditNote' => 'catatan',
        ]);

        $enum = PayrollComponentEnum::from($this->editingComponent);

        app(PayrollService::class)->applyManualRevision(
            $this->payroll,
            $enum,
            (float) $this->componentEditAmount,
            $this->componentEditNote,
            Auth::id(),
        );

        $this->payroll->refresh();
        $this->showComponentEditModal = false;

        Notification::make()
            ->title("{$enum->label()} berhasil diubah")
            ->success()
            ->send();
    }

    public function deleteManualLine(string $lineId): void
    {
        abort_unless($this->canEditAdjustment, 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa diubah.');
        abort_if($this->periodHasEnded, 403, 'Periode ini sudah bukan periode terbaru — hapus baris manual di sini tidak akan berpengaruh (Regenerate sudah ditutup). Edit manual per baris lewat admin panel (Daftar Payroll > buka payroll ini > tab Payroll Items).');

        $line = PayrollManualLine::where('id', $lineId)
            ->where('user_id', $this->payroll->user_id)
            ->where('payroll_period_id', $this->payroll->payroll_period_id)
            ->firstOrFail();

        $oldAmount = (string) $line->amount;
        $line->delete();

        $this->recordAdjustmentAudit($lineId, $oldAmount, null);

        Notification::make()
            ->title('Berhasil dihapus')
            ->body('Klik "Regenerate Payroll Ini" supaya perubahannya masuk ke payroll.')
            ->success()
            ->send();
    }

    /**
     * Jejak "siapa ubah nominal adjustment manual apa, kapan" — sengaja dicatat eksplisit di
     * sini (bukan lewat trait LogsAuditChanges yang nempel di model), karena PayrollManualLine
     * juga dipakai untuk input rutin Koperasi/Bon impor Excel (BonImport) yang BUKAN
     * "penyesuaian" — kalau trait ditempel ke modelnya, tiap import Excel bulanan ikut kecatat
     * jadi log penuh noise. Cuma jalur manual dari halaman ini (openManualLineModal) yang
     * dianggap adjustment dan dicatat.
     */
    private function recordAdjustmentAudit(string $lineId, ?string $oldAmount, ?string $newAmount): void
    {
        $label = ($this->manualLineLabel ?: 'Bon') . ' — ' . $this->payroll->user->name
            . ($this->manualLineNote ? " ({$this->manualLineNote})" : '');

        AuditLog::record(
            'payroll_adjustment',
            $this->payroll->payrollPeriod->company_id,
            'payroll_manual_line:' . $lineId,
            $label,
            $oldAmount,
            $newAmount,
        );
    }

    /** Regenerate payroll SATU karyawan ini saja — ID payroll berubah, jadi redirect ke yang baru. */
    public function regeneratePayroll()
    {
        abort_unless(Auth::user()->can('update', $this->payroll), 403);
        abort_if($this->payroll->is_paid, 403, 'Payroll yang sudah dibayar tidak bisa di-regenerate.');
        abort_if($this->periodHasEnded, 403, 'Periode ini sudah bukan periode terbaru — regenerate penuh ditutup untuk periode lampau (bisa menghitung ulang pakai config yang sudah berubah sejak saat itu). Koreksi lewat edit manual per baris di admin panel (Daftar Payroll > buka payroll ini > tab Payroll Items).');

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
