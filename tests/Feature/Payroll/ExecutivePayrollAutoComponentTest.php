<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TenureAllowance;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eksekutif non-karyawan (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS) biasanya SEMUA pendapatan/
 * potongannya manual lewat halaman Data Eksekutif - KECUALI komponen yang masuk
 * PayrollComponentEnum::executiveAutoComponents() (instruksi user 2026-08-20: "Tunjangan Masa
 * Kerja ngikut yang ada, ngga isi manual lagi"), itu tetap dihitung otomatis kayak karyawan
 * biasa. Komponen LAIN (mis. Gaji Pokok) TETAP manual - membuktikan hardcode-nya cuma
 * berlaku ke daftar itu, bukan buka semua gerbang.
 */
class ExecutivePayrollAutoComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tunjangan_masa_kerja_is_auto_calculated_for_eligible_executive(): void
    {
        $company = Companies::create(['code' => '0000', 'name' => 'Holding Test', 'location' => 'Test', 'is_active' => true]);

        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 0, 'max_years' => 4, 'amount' => 100000]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 5, 'max_years' => null, 'amount' => 300000]);

        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        // 'id' bukan fillable (User::$fillable) - User::create(['id' => ...]) diam-diam
        // mengabaikannya (fill() cuma isi kolom fillable), lalu boot() auto-generate ULID baru.
        // forceFill() bypass guard itu, id ke-set SEBELUM event creating (yang cuma isi kalau
        // masih kosong) sempat jalan.
        $execId = User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS[0];
        $exec = new User();
        $exec->forceFill(['id' => $execId, 'name' => 'Presiden Direktur Uji', 'email' => 'presdir-auto@test.local', 'password' => bcrypt('secret'), 'nip' => '00099', 'company_id' => $company->id, 'is_active' => true]);
        $exec->save();
        UserProfiles::create(['user_id' => $exec->id, 'joining_date' => '2019-01-01']); // 7 tahun per Juli 2026, tanpa employee_type_id/pangkat

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);

        $payroll = Payroll::where('user_id', $exec->id)->where('payroll_period_id', $period->id)->firstOrFail();

        // Tunjangan Masa Kerja tetap otomatis kehitung (masuk ExecutiveAutoComponentEnum) -
        // 'amount' terenkripsi (PayrollEncrypted), cek lewat model bukan assertDatabaseHas mentah.
        $tunjanganItem = $payroll->items()->where('source_type', PayrollComponentEnum::TUNJANGAN_MASA_KERJA->value)->first();
        $this->assertNotNull($tunjanganItem);
        $this->assertSame(300_000.0, (float) $tunjanganItem->amount);
        $this->assertSame(300_000.0, (float) $payroll->fresh()->gross_salary);

        // Gaji Pokok TIDAK ikut auto (bukan di daftar ExecutiveAutoComponentEnum) - tetap
        // manual lewat halaman Data Eksekutif seperti sebelumnya.
        $this->assertNull($payroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->first());
    }
}
