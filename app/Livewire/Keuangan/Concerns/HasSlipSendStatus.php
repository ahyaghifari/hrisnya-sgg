<?php

namespace App\Livewire\Keuangan\Concerns;

use App\Jobs\SendPayrollSlipJob;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filter status kirim slip gaji (Terkirim / Gagal / Belum) + tombol kirim ulang massal yang
 * gagal. Dipakai bareng PayrollsPage & ManualPayrollImportPage. Komponen pemakai wajib
 * menyediakan baseSlipQuery() (Payroll::query() yang sudah di-scope company/periode) dan
 * property computed canUpdatePayroll.
 */
trait HasSlipSendStatus
{
    /** '' = semua | 'sent' | 'failed' | 'unsent' (sudah dibayar tapi belum pernah dikirim) */
    public string $slipStatus = '';

    abstract protected function baseSlipQuery(): Builder;

    protected function applySlipStatusFilter(Builder $query): Builder
    {
        return match ($this->slipStatus) {
            'sent' => $query->whereNotNull('slip_sent_at'),
            'failed' => $query->whereNotNull('slip_send_failed_at'),
            'unsent' => $query->whereNull('slip_sent_at')->whereNull('slip_send_failed_at')->where('is_paid', true),
            default => $query,
        };
    }

    /** @return array{sent:int, failed:int, unsent:int} */
    public function getSlipStatsProperty(): array
    {
        return [
            'sent' => (clone $this->baseSlipQuery())->whereNotNull('slip_sent_at')->count(),
            'failed' => (clone $this->baseSlipQuery())->whereNotNull('slip_send_failed_at')->count(),
            'unsent' => (clone $this->baseSlipQuery())
                ->whereNull('slip_sent_at')->whereNull('slip_send_failed_at')->where('is_paid', true)->count(),
        ];
    }

    /** Kirim ulang SEMUA slip yang gagal (slip_send_failed_at terisi) & sudah dibayar, di scope ini. */
    public function resendFailedSlips(): void
    {
        abort_unless($this->canUpdatePayroll, 403);

        $ids = $this->baseSlipQuery()
            ->whereNotNull('slip_send_failed_at')
            ->where('is_paid', true)
            ->pluck('id');

        foreach ($ids as $id) {
            SendPayrollSlipJob::dispatch($id);
        }

        Notification::make()
            ->title("{$ids->count()} slip yang gagal sedang dikirim ulang")
            ->body('Diproses via queue — status "Gagal Kirim" hilang otomatis begitu berhasil.')
            ->success()
            ->send();
    }
}
