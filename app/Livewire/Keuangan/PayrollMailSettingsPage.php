<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollMailSetting;
use App\Services\Payroll\PayrollMailer;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pengaturan akun SMTP per perusahaan KHUSUS pengiriman slip gaji email (payroll_mail_settings).
 * Perusahaan tanpa setting aktif -> SendPayrollSlipJob pakai mailer default config('mail').
 * Gate: EDIT_KOMPONEN_GAJI_GLOBAL (config payroll tingkat infra).
 */
#[Layout('components.layout', ['title' => 'Pengaturan Email Slip Gaji'])]
class PayrollMailSettingsPage extends Component
{
    public ?string $companyId = null;

    public string $host = '';
    public int $port = 587;
    public string $username = '';
    public string $password = '';
    public ?string $encryption = 'tls';
    public string $fromAddress = '';
    public string $fromName = '';
    public bool $isActive = true;

    public ?string $lastTestedAt = null;
    public ?string $lastTestError = null;
    public string $testRecipient = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value), 403);

        $this->testRecipient = (string) Auth::user()->email;
        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadCompanyState();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->loadCompanyState();
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->loadCompanyState();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get(['id', 'code', 'name']);
    }

    private function currentSetting(): ?PayrollMailSetting
    {
        return $this->companyId
            ? PayrollMailSetting::where('company_id', $this->companyId)->first()
            : null;
    }

    private function loadCompanyState(): void
    {
        $s = $this->currentSetting();

        $this->host = $s->host ?? '';
        $this->port = $s->port ?? 587;
        $this->username = $s->username ?? '';
        $this->password = $s->password ?? '';
        $this->encryption = $s?->encryption ?? 'tls';
        $this->fromAddress = $s->from_address ?? '';
        $this->fromName = $s->from_name ?? '';
        $this->isActive = $s?->is_active ?? true;
        $this->lastTestedAt = $s?->last_tested_at?->translatedFormat('d M Y H:i');
        $this->lastTestError = $s->last_test_error ?? null;
    }

    protected function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value), 403);
        abort_unless((bool) $this->companyId, 422);

        $data = $this->validate();

        PayrollMailSetting::updateOrCreate(
            ['company_id' => $this->companyId],
            [
                'host' => $data['host'],
                'port' => $data['port'],
                'username' => $data['username'],
                'password' => $data['password'],
                'encryption' => $data['encryption'] ?: null,
                'from_address' => $data['fromAddress'],
                'from_name' => $data['fromName'],
                'is_active' => $data['isActive'],
            ],
        );

        Notification::make()->title('Pengaturan email disimpan')->success()->send();
    }

    public function sendTest(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value), 403);

        $this->validate(['testRecipient' => ['required', 'email']]);

        $s = $this->currentSetting();
        if (! $s) {
            Notification::make()->title('Simpan pengaturan dulu sebelum tes')->warning()->send();

            return;
        }

        $result = app(PayrollMailer::class)->test($s, $this->testRecipient);

        $s->update([
            'last_tested_at' => now(),
            'last_test_error' => $result['ok'] ? null : \Illuminate\Support\Str::limit($result['message'], 500),
        ]);
        $this->loadCompanyState();

        Notification::make()
            ->title($result['ok'] ? 'Email tes terkirim' : 'Tes gagal')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-mail-settings-page');
    }
}
