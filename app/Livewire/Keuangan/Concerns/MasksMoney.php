<?php

namespace App\Livewire\Keuangan\Concerns;

use Livewire\Attributes\Session;

/**
 * Moda "Sembunyikan Angka" di halaman Payroll. Saat aktif, nominal TIDAK ikut dirender ke
 * HTML sama sekali (diganti string bertitik) — bukan cuma di-blur CSS — jadi tidak bisa
 * diintip lewat inspect. State disimpan di session dengan key yang sama antara PayrollsPage &
 * PayrollDetailPage, jadi toggle di satu halaman kebawa ke halaman lain. Default: tersembunyi.
 */
trait MasksMoney
{
    #[Session(key: 'payroll.hideMoney')]
    public bool $hideMoney = true;

    public function toggleMoney(): void
    {
        $this->hideMoney = ! $this->hideMoney;
    }

    /** "Rp1.234.567", atau string mask kalau moda sembunyi aktif, atau $fallback kalau null. */
    public function rp(?float $amount, string $fallback = '-'): string
    {
        if ($this->hideMoney) {
            return "Rp\u{00A0}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}";
        }

        return $amount === null ? $fallback : 'Rp' . number_format($amount, 0, ',', '.');
    }
}
