<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollMailSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim email slip gaji lewat akun SMTP perusahaan yang bersangkutan (payroll_mail_settings).
 * Kalau perusahaan tak punya setting aktif -> pakai mailer default `config('mail')`.
 *
 * Cara kerja: daftarkan config mailer sementara `mail.mailers.payroll_<companyId>` di runtime,
 * lalu `Mail::mailer(<name>)`. Aman dipanggil berkali-kali (idempoten, re-set config).
 */
class PayrollMailer
{
    /** Setting SMTP aktif untuk 1 perusahaan, atau null (berarti pakai mailer default). */
    public function settingFor(string $companyId): ?PayrollMailSetting
    {
        return PayrollMailSetting::where('company_id', $companyId)->where('is_active', true)->first();
    }

    /** Kirim $mailable ke $toEmail. $setting null -> mailer default. */
    public function send(?PayrollMailSetting $setting, string $toEmail, Mailable $mailable): void
    {
        if (! $setting) {
            Mail::to($toEmail)->send($mailable);

            return;
        }

        Mail::mailer($this->registerMailer($setting))->to($toEmail)->send($mailable);
    }

    /**
     * Kirim email tes sederhana lewat setting ini. Tidak menyimpan apa pun — pemanggil yang
     * mencatat last_tested_at / last_test_error.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(PayrollMailSetting $setting, string $toEmail): array
    {
        try {
            Mail::mailer($this->registerMailer($setting))->raw(
                "Tes koneksi email slip gaji untuk {$setting->company?->name}.\n\nKalau email ini sampai, konfigurasi SMTP sudah benar.",
                fn ($m) => $m->to($toEmail)
                    ->from($setting->from_address, $setting->from_name)
                    ->subject('Tes Email Slip Gaji — ' . ($setting->company?->name ?? 'Perusahaan')),
            );

            return ['ok' => true, 'message' => "Email tes terkirim ke {$toEmail}."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function registerMailer(PayrollMailSetting $setting): string
    {
        $name = 'payroll_' . $setting->company_id;

        config(["mail.mailers.{$name}" => $setting->mailerConfig()]);

        return $name;
    }
}
