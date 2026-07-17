<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Payroll\Payroll;
use App\Services\Payroll\PayrollService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Detail Payroll'])]
class PayrollDetailPage extends Component
{
    public Payroll $payroll;

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

    public function markAsPaid(): void
    {
        $this->payroll->update(['is_paid' => true]);
        $this->payroll->refresh();

        Notification::make()
            ->title('Payroll ditandai sudah dibayar')
            ->success()
            ->send();
    }

    public function downloadSlip()
    {
        return app(PayrollService::class)->download($this->payroll);
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-detail-page');
    }
}
