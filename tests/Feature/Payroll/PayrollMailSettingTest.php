<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Jobs\SendPayrollSlipJob;
use App\Livewire\Keuangan\PayrollMailSettingsPage;
use App\Mail\PayrollSlipMail;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollMailSetting;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollMailSettingTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Companies
    {
        return Companies::create(['code' => 'MST', 'name' => 'RS Mail Test', 'location' => 'X', 'is_active' => true]);
    }

    private function setting(Companies $c, array $overrides = []): PayrollMailSetting
    {
        return PayrollMailSetting::create(array_merge([
            'company_id' => $c->id,
            'host' => 'smtp.rs.test',
            'port' => 587,
            'username' => 'hrd@rs.test',
            'password' => 'secret-app-pw',
            'encryption' => 'tls',
            'from_address' => 'hrd@rs.test',
            'from_name' => 'HRD RS Mail Test',
            'is_active' => true,
        ], $overrides));
    }

    private function admin(): User
    {
        foreach ([CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value, CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Payroll Global Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value, CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value]);
        $u = User::factory()->create(['company_id' => null]);
        $u->assignRole($role);

        return $u;
    }

    public function test_password_stored_encrypted_not_plaintext(): void
    {
        $c = $this->company();
        $s = $this->setting($c);

        $raw = DB::table('payroll_mail_settings')->where('id', $s->id)->value('password');
        $this->assertNotSame('secret-app-pw', $raw);
        $this->assertSame('secret-app-pw', $s->fresh()->password); // cast decrypts
    }

    public function test_mailer_falls_back_to_default_when_no_setting(): void
    {
        Mail::fake();
        $c = $this->company();

        app(PayrollMailer::class)->send(
            app(PayrollMailer::class)->settingFor($c->id), // null
            'karyawan@mail.test',
            new class extends \Illuminate\Mail\Mailable {
                public function build()
                {
                    return $this->subject('x')->html('x');
                }
            },
        );

        Mail::assertSent(fn (\Illuminate\Mail\Mailable $m) => true);
    }

    public function test_send_job_uses_company_smtp_and_from(): void
    {
        Mail::fake();

        $c = $this->company();
        $this->setting($c);

        $employee = User::factory()->create(['company_id' => $c->id, 'nip' => '20260009999']);
        UserProfiles::create(['user_id' => $employee->id, 'date_of_birth' => '1994-03-04', 'email_pribadi' => 'emp@mail.test']);
        $period = PayrollPeriod::create(['company_id' => $c->id, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30']);
        $payroll = Payroll::create([
            'user_id' => $employee->id, 'payroll_period_id' => $period->id,
            'gross_salary' => 1_000_000, 'net_salary' => 1_000_000, 'is_paid' => true,
        ]);

        (new SendPayrollSlipJob($payroll->id))->handle(app(\App\Services\Payroll\PayrollService::class), app(PayrollMailer::class));

        Mail::assertSent(PayrollSlipMail::class, function (PayrollSlipMail $m) {
            return $m->fromAddress === 'hrd@rs.test' && $m->fromName === 'HRD RS Mail Test';
        });
    }

    public function test_page_saves_setting(): void
    {
        $c = $this->company();
        $this->actingAs($this->admin());

        Livewire::test(PayrollMailSettingsPage::class)
            ->set('companyId', $c->id)
            ->set('host', 'smtp.baru.test')
            ->set('port', 465)
            ->set('username', 'a@baru.test')
            ->set('password', 'pw12345')
            ->set('encryption', 'ssl')
            ->set('fromAddress', 'a@baru.test')
            ->set('fromName', 'HRD Baru')
            ->call('save')
            ->assertHasNoErrors();

        $s = PayrollMailSetting::where('company_id', $c->id)->firstOrFail();
        $this->assertSame('smtp.baru.test', $s->host);
        $this->assertSame(465, $s->port);
        $this->assertSame('pw12345', $s->password);
    }

    public function test_page_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(PayrollMailSettingsPage::class)->assertForbidden();
    }
}
