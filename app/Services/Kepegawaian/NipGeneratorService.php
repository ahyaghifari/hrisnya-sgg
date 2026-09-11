<?php

namespace App\Services\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * NIP = tanggal bergabung (DDMMYYYY) + urutan 3 digit, wrap 999 -> 001. Urutan GLOBAL, lintas
 * 3 company kode 0000/0001/0002 (Syifa Global Group, RSU Syifa Medika Banjarbaru, RSU Syifa
 * Medika Barabai) — disimpan di settings (key nip_last_sequence, company_id null).
 *
 * Company lain TIDAK dipakaikan skema ini — biarkan perilaku lama (User::creating() fallback
 * Ymd+index+random kalau nip kosong).
 */
class NipGeneratorService
{
    private const TARGET_COMPANY_CODES = ['0000', '0001', '0002'];

    private const SETTING_KEY = 'nip_last_sequence';

    /** Company ini termasuk yang dipakaikan skema NIP baru? */
    public function applies(?string $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        return in_array(Companies::find($companyId)?->code, self::TARGET_COMPANY_CODES, true);
    }

    /** Generate + kunci urutan berikutnya secara atomic. Panggil cuma saat benar-benar disimpan. */
    public function generate(?string $joiningDate): string
    {
        return DB::transaction(function () use ($joiningDate) {
            $setting = Setting::query()
                ->whereNull('company_id')
                ->where('key', self::SETTING_KEY)
                ->lockForUpdate()
                ->first();

            $next = $this->nextSequence((int) ($setting->value ?? 0));

            if ($setting) {
                $setting->update(['value' => (string) $next]);
            } else {
                Setting::create([
                    'company_id' => null,
                    'module' => 'kepegawaian',
                    'key' => self::SETTING_KEY,
                    'type' => 'integer',
                    'value' => (string) $next,
                    'label' => 'Urutan Terakhir NIP',
                    'description' => 'Urutan 3 digit terakhir yang sudah dipakai NIP generator (wrap 999 -> 001). Lintas company 0000/0001/0002.',
                ]);
            }

            return $this->format($joiningDate, $next);
        });
    }

    private function nextSequence(int $last): int
    {
        return $last >= 999 ? 1 : $last + 1;
    }

    private function format(?string $joiningDate, int $sequence): string
    {
        $date = $joiningDate ? Carbon::parse($joiningDate) : now();

        return $date->format('dmY') . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
