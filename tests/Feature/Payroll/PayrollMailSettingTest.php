<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Payroll\MailSettings\Pages\ManagePayrollMailSettings;
use App\Jobs\SendPayrollSlipJob;
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
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
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
            'employee_id' => $employee->employee->id, 'payroll_period_id' => $period->id,
            'gross_salary' => 1_000_000, 'net_salary' => 1_000_000, 'is_paid' => true,
        ]);

        (new SendPayrollSlipJob($payroll->id))->handle(app(\App\Services\Payroll\PayrollService::class), app(PayrollMailer::class));

        Mail::assertSent(PayrollSlipMail::class, function (PayrollSlipMail $m) {
            return $m->fromAddress === 'hrd@rs.test' && $m->fromName === 'HRD RS Mail Test';
        });
    }

    public function test_resource_lists_records_for_admin(): void
    {
        $c = $this->company();
        $s = $this->setting($c);
        $this->actingAs($this->admin());

        Livewire::test(ManagePayrollMailSettings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$s]);
    }

    public function test_resource_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => $this->company()->id]));
        Livewire::test(ManagePayrollMailSettings::class)->assertForbidden();
    }

    public function test_resource_send_test_action_records_result(): void
    {
        Mail::fake();
        $c = $this->company();
        $s = $this->setting($c);
        $this->actingAs($this->admin());

        Livewire::test(ManagePayrollMailSettings::class)
            ->callTableAction('sendTest', $s, data: ['recipient' => 'cek@mail.test']);

        $fresh = $s->fresh();
        $this->assertNotNull($fresh->last_tested_at);
        $this->assertNull($fresh->last_test_error); // Mail::fake() -> test() sukses
    }
}
