<?php

namespace App\Services\Payroll;

use App\Enum\Salary\SalaryComponentNameEnum;
use App\Models\DoctorService\DoctorFeeItem;
use App\Models\Payroll\DoctorPayroll;
use App\Models\Payroll\DoctorPayrollItem;
use App\Models\Payroll\DoctorPayrollPeriod;
use App\Models\Payroll\MasterSalaryComponents;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoctorPayrollService
{
    public function generatePayroll(DoctorPayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {

            if ($period->processed_at) {
                return;
            }

            $feeItemsQuery = DoctorFeeItem::query()
                ->where('is_paid', false)
                ->whereHas('submission', function ($q) use ($period) {
                    $q->whereBetween('date', [$period->start_date, $period->end_date]);

                    if ($period->company_id) {
                        $q->where('company_id', $period->company_id);
                    }
                });

            $feeItems = $feeItemsQuery->get();

            if ($feeItems->isEmpty()) {
                $period->update([
                    'processed_at' => now(),
                ]);
                return;
            }

            $groupedByDoctor = $feeItems->groupBy('user_id');

            foreach ($groupedByDoctor as $doctorId => $items) {

                $payroll = DoctorPayroll::query()
                    ->firstOrCreate(
                        [
                            'doctor_id' => $doctorId,
                            'doctor_payroll_period_id' => $period->id,
                        ],
                        [
                            'total_fee' => 0,
                            'is_paid'   => false,
                        ]
                    );

                $totalFee = 0;

                foreach ($items as $item) {
                    $exists = DoctorPayrollItem::query()
                        ->where('doctor_payroll_id', $payroll->id)
                        ->where('doctor_fee_item_id', $item->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DoctorPayrollItem::create([
                        'doctor_payroll_id'   => $payroll->id,
                        'doctor_fee_item_id'  => $item->id,
                        'service_name'        => $item->service_name,
                        'amount'              => $item->amount,
                    ]);

                    $totalFee += (float) $item->amount;

                    $item->update([
                        'is_paid' => true,
                    ]);
                }

                $taxComponent = MasterSalaryComponents::where(
                    'name',
                    SalaryComponentNameEnum::DOCTOR_TAX->value
                )->first();

                $grossFee = $totalFee;

                $taxPercentage = (float) ($taxComponent->default_value ?? 0);
                $taxAmount = $grossFee * ($taxPercentage / 100);
                $netFee = $grossFee - $taxAmount;

                $payroll->update([
                    'gross_salary'      => $grossFee,
                    'tax_percentage' => $taxPercentage,
                    'tax_amount'     => $taxAmount,
                    'net_salary'        => $netFee,
                ]);
            }

            $period->update([
                'processed_at' => now(),
            ]);
        });
    }

    public function download(DoctorPayroll $payroll): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.payroll.doctor-payroll', [
            'payroll' => $payroll->load(['doctor', 'period', 'items']),
        ]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->stream();
            },
            'slip-gaji-' . str_replace(' ', '-', strtolower($payroll->doctor->name)) . '.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}
