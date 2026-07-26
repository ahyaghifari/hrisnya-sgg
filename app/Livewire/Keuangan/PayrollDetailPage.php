<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Jobs\SendPayrollSlipJob;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Setting;
use App\Notifications\PayrollPaid;
use App\Services\Payroll\PayrollService;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Detail Payroll'])]
class PayrollDetailPage extends Component
{
    public Payroll $payroll;

    public bool $showManualLineModal = false;
    public ?string $manualLineComponent = null; // 'bon' | 'insentif'
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

    /** Tombol "+ Tambah" di baris Insentif cuma tampil kalau metode company = "import" — kalau
     *  "master", input manualnya lewat halaman Insentif (master data), bukan di sini — DITAMBAH
     *  dua gate yang sama seperti Bon (aktif di Komponen Gaji + terdaftar di Penerima Komponen
     *  Gaji untuk tipe karyawan ini). */
    public function getInsentifMetodeProperty(): string
    {
        return (string) Setting::resolve('insentif_metode', $this->payroll->payrollPeriod->company_id, default: 'master');
    }

    public function getInsentifAktifProperty(): bool
    {
        return $this->insentifMetode === 'import'
            && $this->componentEligibleForThisEmployee(PayrollComponentEnum::INSENTIF->value);
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

    /** Baris Insentif manual (metode import) punya karyawan ini di periode ini. */
    public function getInsentifManualLinesProperty()
    {
        return $this->manualLinesFor(PayrollComponentEnum::INSENTIF->value);
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

        $label = $this->manualLineComponent === PayrollComponentEnum::BON->value ? 'Bon' : 'Insentif';

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
            abort_if($this->manualLinesFor($this->manualLineComponent)->isNotEmpty(), 403, "Karyawan ini sudah punya baris {$label} untuk periode ini — ubah baris yang ada, bukan menambah baru.");

            // Cek isNotEmpty() di atas cuma pencegahan cepat di UI, BUKAN jaminan atomik — dua
            // klik/tab bersamaan bisa dua-duanya lolos sebelum salah satu commit. Constraint
            // unique di DB (migrasi payroll_manual_lines_period_user_component_unique) yang
            // jadi jaminan sesungguhnya; kalau race benar-benar kejadian, insert kedua akan
            // gagal kena constraint itu, ditangkap di sini jadi pesan yang enak dibaca.
            try {
                PayrollManualLine::create([
                    'payroll_period_id' => $this->payroll->payroll_period_id,
                    'user_id' => $this->payroll->user_id,
                    'component' => $this->manualLineComponent,
                    'amount' => $this->manualLineAmount,
                    'note' => $this->manualLineNote,
                    'created_by' => Auth::id(),
                ]);
            } catch (UniqueConstraintViolationException) {
                abort(409, "Karyawan ini sudah punya baris {$label} untuk periode ini — coba muat ulang halaman.");
            }
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
