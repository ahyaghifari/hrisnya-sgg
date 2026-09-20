<?php

namespace Tests\Feature\Payroll;

use App\Enum\OrgStructurLevel;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\PayrollSlipRekapExport;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Payroll/User/dst di-build manual (setRelation, tanpa save()) - export ini murni transformasi
 * data yang sudah ada, tidak butuh baris sungguhan. RefreshDatabase cuma dipakai buat
 * `company_payroll_components` (sumber toggle aktif/nonaktif per company, lihat buildColumns()).
 *
 * Fokus: kolom komponen yang TIDAK di-toggle aktif harus HILANG dari export (permintaan user),
 * kolom identitas/ringkasan (TOTAL GAJI dst) harus tetap selalu ada, dan jumlah kolom
 * heading vs data harus selalu sama (satu sumber lewat $this->columns, lihat komentar class).
 */
class PayrollSlipRekapExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(string $sourceType, string $type, float $amount, float $quantity = 1): PayrollItem
    {
        $item = new PayrollItem;
        $item->source_type = $sourceType;
        $item->type = $type;
        $item->amount = $amount;
        $item->quantity = $quantity;

        return $item;
    }

    private function makePayroll(array $items): Payroll
    {
        $profile = new UserProfiles;
        $profile->setRelation('jobPosition', null);
        $profile->setRelation('pangkat', null);
        $profile->setRelation('kelas', null);
        $profile->setRelation('employeeType', null);
        $profile->setRelation('orgStructure', null);

        $user = new User;
        $user->name = 'Karyawan Uji';
        $user->nip = '00123';
        $user->setRelation('profile', $profile);

        $period = new PayrollPeriod;
        $period->start_date = now();
        $period->end_date = now();
        $period->payment_date = now();

        $payroll = new Payroll;
        $payroll->gross_salary = collect($items)->where('type', 'earning')->sum(fn (PayrollItem $i) => (float) $i->amount);
        $payroll->total_deductions = collect($items)->where('type', 'deduction')->sum(fn (PayrollItem $i) => (float) $i->amount);
        $payroll->net_salary = $payroll->gross_salary - $payroll->total_deductions;
        $payroll->setRelation('user', $user);
        $payroll->setRelation('payrollPeriod', $period);
        $payroll->setRelation('items', new Collection($items));

        return $payroll;
    }

    public function test_only_active_components_columns_are_included(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);

        // Cuma Gaji Pokok & BPJS Kesehatan yang aktif - Tunjangan Jabatan & Koperasi TIDAK.
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BPJS_KESEHATAN->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::TUNJANGAN_JABATAN->value, 'is_active' => false]);

        $export = new PayrollSlipRekapExport(collect(), $company->id);
        $headings = $export->headings();

        $this->assertContains('Gaji Pokok', $headings);
        // Gaji Dasar/% Kenaikan ikut toggle GAJI_POKOK yang sama (lihat buildColumns()).
        $this->assertContains('Gaji Dasar', $headings);
        $this->assertContains('% Kenaikan', $headings);
        $this->assertContains('POTONGAN BPJS 1%', $headings);
        $this->assertNotContains('Tunjangan Jabatan', $headings);
        $this->assertNotContains('POTONGAN KOPERASI', $headings);

        // Kolom ringkasan/identitas selalu ada, apa pun toggle-nya.
        $this->assertContains('TOTAL GAJI', $headings);
        $this->assertContains('Jumlah Terima', $headings);
        $this->assertContains('NAMA', $headings);
    }

    public function test_heading_and_row_column_counts_match(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        $payroll = $this->makePayroll([
            $this->makeItem('gaji_pokok', 'earning', 5_000_000),
        ]);

        $export = new PayrollSlipRekapExport(collect([$payroll]), $company->id);
        $rows = $export->collection();

        // rows[0] = baris header divisi (fallback "Tanpa Divisi"), rows[1] = baris data.
        $this->assertCount(count($export->headings()), $rows[0]);
        $this->assertCount(count($export->headings()), $rows[1]);
    }

    public function test_non_core_earning_is_bucketed_into_lembur_dan_lain_lain_not_total_gaji(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::LEMBUR->value, 'is_active' => true]);

        $payroll = $this->makePayroll([
            $this->makeItem('gaji_pokok', 'earning', 5_000_000),
            $this->makeItem('lembur', 'earning', 300_000),
        ]);

        $export = new PayrollSlipRekapExport(collect([$payroll]), $company->id);
        $row = $export->collection()->get(1);
        $headings = array_flip($export->headings());

        $this->assertSame(5_000_000.0, $row[$headings['TOTAL GAJI']]);
        $this->assertSame(300_000.0, $row[$headings['LEMBUR']]);
        $this->assertSame(5_300_000.0, $row[$headings['Jumlah Terima']]);
    }

    /** Bikin payroll dengan pangkat (level di-set langsung, tanpa save()) & tanggal masuk tertentu - dipakai test urutan. */
    private function makePayrollWithRank(string $name, ?int $pangkatLevel, ?string $joiningDate): Payroll
    {
        $pangkat = null;
        if ($pangkatLevel !== null) {
            $pangkat = new \App\Models\Master\Pangkats;
            $pangkat->level = $pangkatLevel;
        }

        $profile = new UserProfiles;
        $profile->setRelation('jobPosition', null);
        $profile->setRelation('pangkat', $pangkat);
        $profile->setRelation('kelas', null);
        $profile->setRelation('employeeType', null);
        $profile->setRelation('orgStructure', null);
        $profile->joining_date = $joiningDate;

        $user = new User;
        $user->name = $name;
        $user->setRelation('profile', $profile);

        $payroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_000_000)]);
        $payroll->setRelation('user', $user);

        return $payroll;
    }

    public function test_sorted_by_pangkat_level_desc_then_tenure_desc_then_name(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        // Manager (level 5) menang atas Staff (level 2) walau join lebih baru.
        $manager = $this->makePayrollWithRank('Zainal (Manager)', 5, now()->subMonths(2)->toDateString());
        // 2 Staff level sama (2) - yang bulan kerjanya lebih lama menang.
        $staffLama = $this->makePayrollWithRank('Budi (Staff Lama)', 2, now()->subMonths(24)->toDateString());
        $staffBaru = $this->makePayrollWithRank('Andi (Staff Baru)', 2, now()->subMonths(3)->toDateString());
        // Tanpa pangkat sama sekali - harus paling belakang.
        $tanpaPangkat = $this->makePayrollWithRank('Ali (Tanpa Pangkat)', null, now()->subMonths(100)->toDateString());

        $export = new PayrollSlipRekapExport(collect([$tanpaPangkat, $staffBaru, $manager, $staffLama]), $company->id);
        $headings = array_flip($export->headings());
        $rows = $export->collection()->values();

        // rows[0] = judul divisi, rows[1..4] = data terurut.
        $names = $rows->slice(1)->map(fn ($row) => $row[$headings['NAMA']])->values()->all();

        $this->assertSame([
            'Zainal (Manager)',
            'Budi (Staff Lama)',
            'Andi (Staff Baru)',
            'Ali (Tanpa Pangkat)',
        ], $names);
    }

    /** Bikin payroll dengan divisi tertentu (buat test NO reset per divisi). */
    private function makePayrollInDivision(string $name, string $divisionName): Payroll
    {
        $division = new OrgStructure;
        $division->level = OrgStructurLevel::LEVEL_DIVISION->value;
        $division->name = $divisionName;

        $profile = new UserProfiles;
        $profile->setRelation('jobPosition', null);
        $profile->setRelation('pangkat', null);
        $profile->setRelation('kelas', null);
        $profile->setRelation('employeeType', null);
        $profile->setRelation('jobPosition', (new JobPositions)->setRelation('orgStructure', $division));

        $user = new User;
        $user->name = $name;
        $user->setRelation('profile', $profile);

        $payroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_000_000)]);
        $payroll->setRelation('user', $user);

        return $payroll;
    }

    public function test_no_column_resets_to_1_for_each_division(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        $payrolls = collect([
            $this->makePayrollInDivision('Andi', 'Divisi A'),
            $this->makePayrollInDivision('Budi', 'Divisi A'),
            $this->makePayrollInDivision('Citra', 'Divisi B'),
        ]);

        $export = new PayrollSlipRekapExport($payrolls, $company->id);
        $headings = array_flip($export->headings());
        $rows = $export->collection()->values();

        // rows: [0]=judul Divisi A, [1]=Andi(NO 1), [2]=Budi(NO 2), [3]=judul Divisi B, [4]=Citra(NO 1).
        $this->assertSame(1, $rows[1][$headings['NO']]);
        $this->assertSame(2, $rows[2][$headings['NO']]);
        $this->assertSame(1, $rows[4][$headings['NO']]);
    }

    public function test_bulan_kerja_is_a_whole_number_without_decimals(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        // 45 hari ~ 1,47 bulan - harus digenapkan ke bawah jadi 1, bukan "1,47" atau "1.47".
        $payroll = $this->makePayrollWithRank('Karyawan Uji', null, now()->subDays(45)->toDateString());

        $export = new PayrollSlipRekapExport(collect([$payroll]), $company->id);
        $headings = array_flip($export->headings());
        $row = $export->collection()->get(1);

        $this->assertSame(1, $row[$headings['Bulan Kerja']]);
        $this->assertIsInt($row[$headings['Bulan Kerja']]);
    }

    public function test_payrolls_are_grouped_under_division_header_row(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        $division = new OrgStructure;
        $division->level = OrgStructurLevel::LEVEL_DIVISION->value;
        $division->name = 'Divisi Keuangan';

        $profile = new UserProfiles;
        $profile->setRelation('jobPosition', null);
        $profile->setRelation('pangkat', null);
        $profile->setRelation('kelas', null);
        $profile->setRelation('employeeType', null);
        $profile->setRelation('jobPosition', (new JobPositions)->setRelation('orgStructure', $division));

        $user = new User;
        $user->name = 'Karyawan Divisi';
        $user->setRelation('profile', $profile);

        $payroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_000_000)]);
        $payroll->setRelation('user', $user);

        $export = new PayrollSlipRekapExport(collect([$payroll]), $company->id);
        $rows = $export->collection()->values();

        $this->assertSame('Divisi Keuangan', $rows[0][1]);
    }

    /** Karyawan tanpa divisi (org_structure kosong) - kasus nyatanya Presiden Direktur/Sekretaris
     * Direktur non-karyawan holding (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS) - DIGABUNG jadi 1
     * grup berlabel "Presiden Direktur" (bukan masing-masing pakai jabatannya sendiri, instruksi
     * user 2026-08-20), SELALU di baris paling atas, sebelum divisi asli manapun. */
    public function test_no_division_users_share_one_presiden_direktur_group_and_sort_first(): void
    {
        // Khusus company holding (kode '0000') - lihat test berikutnya buat company biasa.
        $company = Companies::create(['code' => '0000', 'name' => 'Syifa Global Group', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        $division = new OrgStructure;
        $division->level = OrgStructurLevel::LEVEL_DIVISION->value;
        $division->name = 'Divisi Keuangan';

        $divisionProfile = new UserProfiles;
        $divisionProfile->setRelation('jobPosition', null);
        $divisionProfile->setRelation('pangkat', null);
        $divisionProfile->setRelation('kelas', null);
        $divisionProfile->setRelation('employeeType', null);
        $divisionProfile->setRelation('jobPosition', (new JobPositions)->setRelation('orgStructure', $division));

        $divisionUser = new User;
        $divisionUser->name = 'Karyawan Divisi';
        $divisionUser->setRelation('profile', $divisionProfile);

        $divisionPayroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_000_000)]);
        $divisionPayroll->setRelation('user', $divisionUser);

        // 2 akun tanpa divisi, jabatan BEDA-BEDA (Presiden Direktur & Sekretaris Direktur) -
        // harus tetap 1 grup gabungan, bukan 2 grup terpisah.
        $presdirProfile = new UserProfiles;
        $presdirProfile->setRelation('jobPosition', null);
        $presdirProfile->setRelation('pangkat', null);
        $presdirProfile->setRelation('kelas', null);
        $presdirProfile->setRelation('employeeType', null);
        $presdirProfile->setRelation('orgStructure', null);

        $presdirUser = new User;
        $presdirUser->name = 'Presiden Direktur Uji';
        $presdirUser->setRelation('profile', $presdirProfile);

        $presdirPayroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 5_000_000)]);
        $presdirPayroll->setRelation('user', $presdirUser);

        $sekdirProfile = new UserProfiles;
        $sekdirProfile->setRelation('jobPosition', null);
        $sekdirProfile->setRelation('pangkat', null);
        $sekdirProfile->setRelation('kelas', null);
        $sekdirProfile->setRelation('employeeType', null);
        $sekdirProfile->setRelation('orgStructure', null);

        $sekdirUser = new User;
        $sekdirUser->name = 'Sekretaris Direktur Uji';
        $sekdirUser->setRelation('profile', $sekdirProfile);

        $sekdirPayroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 4_000_000)]);
        $sekdirPayroll->setRelation('user', $sekdirUser);

        $export = new PayrollSlipRekapExport(collect([$divisionPayroll, $presdirPayroll, $sekdirPayroll]), $company->id);
        $rows = $export->collection()->values();

        // rows[0] = header grup "Presiden Direktur", rows[1..2] = 2 anggotanya, rows[3] = header "Divisi Keuangan".
        $this->assertSame('Presiden Direktur', $rows[0][1]);
        $this->assertSame('Divisi Keuangan', $rows[3][1]);
    }

    /** Company BIASA (bukan holding) yang kebetulan ada karyawan tanpa divisi TETAP pakai
     * label generik "Tanpa Divisi" dan urutan alfabetis biasa (TIDAK dipaksa ke atas) - salah
     * kalau ikut dilabeli "Presiden Direktur" (instruksi user 2026-08-20: "hanya khusus
     * perusahaan holding"). */
    /** Company BUKAN holding - karyawan tanpa divisi dilabeli "Manajemen" (bukan "Presiden
     * Direktur", itu khusus holding), dan grup ini TETAP ditaruh paling atas juga (instruksi
     * user 2026-08-20: "kalo bukan holding artinya Manajemen saja dan diatas juga"). */
    public function test_no_division_group_labeled_manajemen_and_sorts_first_for_non_holding_company(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        $division = new OrgStructure;
        $division->level = OrgStructurLevel::LEVEL_DIVISION->value;
        $division->name = 'Divisi Keuangan';

        $divisionProfile = new UserProfiles;
        $divisionProfile->setRelation('jobPosition', null);
        $divisionProfile->setRelation('pangkat', null);
        $divisionProfile->setRelation('kelas', null);
        $divisionProfile->setRelation('employeeType', null);
        $divisionProfile->setRelation('jobPosition', (new JobPositions)->setRelation('orgStructure', $division));

        $divisionUser = new User;
        $divisionUser->name = 'Karyawan Divisi';
        $divisionUser->setRelation('profile', $divisionProfile);

        $divisionPayroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_000_000)]);
        $divisionPayroll->setRelation('user', $divisionUser);

        $noDivisionProfile = new UserProfiles;
        $noDivisionProfile->setRelation('jobPosition', null);
        $noDivisionProfile->setRelation('pangkat', null);
        $noDivisionProfile->setRelation('kelas', null);
        $noDivisionProfile->setRelation('employeeType', null);
        $noDivisionProfile->setRelation('orgStructure', null);

        $noDivisionUser = new User;
        $noDivisionUser->name = 'Karyawan Tanpa Divisi';
        $noDivisionUser->setRelation('profile', $noDivisionProfile);

        $noDivisionPayroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 2_000_000)]);
        $noDivisionPayroll->setRelation('user', $noDivisionUser);

        $export = new PayrollSlipRekapExport(collect([$divisionPayroll, $noDivisionPayroll]), $company->id);
        $rows = $export->collection()->values();

        $this->assertSame('Manajemen', $rows[0][1]);
        $this->assertSame('Divisi Keuangan', $rows[2][1]);
    }

    public function test_gaji_pokok_breakdown_is_recalculated_from_master_data(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        \App\Models\Setting::create([
            'key' => 'gaji_pokok_kenaikan_per_kelas',
            'company_id' => $company->id,
            'value' => '8',
            'type' => 'integer',
            'module' => 'payroll',
            'label' => 'Kenaikan Gaji Pokok per Kelas',
        ]);

        $startKelas = \App\Models\Master\Kelas::create(['company_id' => $company->id, 'name' => 'Kelas 1', 'level' => 1]);
        $currentKelas = \App\Models\Master\Kelas::create(['company_id' => $company->id, 'name' => 'Kelas 3', 'level' => 3]);
        $pangkat = \App\Models\Master\Pangkats::create([
            'company_id' => $company->id,
            'name' => 'Staff',
            'start_kelas_id' => $startKelas->id,
            'max_kelas_id' => $currentKelas->id,
        ]);
        \App\Models\Payroll\PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 1_000_000]);

        $profile = new UserProfiles;
        $profile->setRelation('jobPosition', null);
        $profile->setRelation('pangkat', $pangkat->fresh(['startKelas', 'maxKelas', 'pangkatSalary']));
        $profile->setRelation('kelas', $currentKelas);
        $profile->setRelation('employeeType', null);
        $profile->setRelation('orgStructure', null);

        $user = new User;
        $user->name = 'Karyawan Uji';
        $user->setRelation('profile', $profile);

        $payroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_160_000)]);
        $payroll->setRelation('user', $user);
        $payroll->payrollPeriod->company_id = $company->id;

        $export = new PayrollSlipRekapExport(collect([$payroll]), $company->id);
        $row = $export->collection()->get(1);
        $headings = array_flip($export->headings());

        // Flat 8% dari setting company - kenaikan berdasarkan langkah kelas sudah di-terminate
        // (2026-08-22), Kelas level 3 di sini tidak lagi mempengaruhi persennya.
        $this->assertSame(1_000_000.0, $row[$headings['Gaji Dasar']]);
        $this->assertSame('8%', $row[$headings['% Kenaikan']]);
        // "Gaji Pokok" tetap angka final HISTORIS dari payroll_items, bukan hasil recompute ini.
        $this->assertSame(1_160_000.0, $row[$headings['Gaji Pokok']]);
    }

    public function test_id_nik_and_bank_account_survive_leading_zero_in_actual_spreadsheet(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);

        $profile = new UserProfiles;
        $profile->setRelation('jobPosition', null);
        $profile->setRelation('pangkat', null);
        $profile->setRelation('kelas', null);
        $profile->setRelation('employeeType', null);
        $profile->setRelation('orgStructure', null);
        $profile->no_nik = '0033445566778899';
        $profile->bank_account_number = '0012345678';

        $user = new User;
        $user->name = 'Karyawan Uji';
        $user->nip = '00123';
        $user->setRelation('profile', $profile);

        $payroll = $this->makePayroll([$this->makeItem('gaji_pokok', 'earning', 1_000_000)]);
        $payroll->setRelation('user', $user);

        $export = new PayrollSlipRekapExport(collect([$payroll]), $company->id);
        $bytes = Excel::raw($export, ExcelWriter::XLSX);

        $tmpPath = tempnam(sys_get_temp_dir(), 'payroll-export-test') . '.xlsx';
        file_put_contents($tmpPath, $bytes);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $headings = array_flip($export->headings());
        $dataRow = 3; // baris 1 = heading, baris 2 = judul divisi "Tanpa Divisi", baris 3 = data.

        $columnLetter = fn (int $index) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $idCell = $sheet->getCell($columnLetter($headings['ID']) . $dataRow);
        $nikCell = $sheet->getCell($columnLetter($headings['NIK']) . $dataRow);
        $bankCell = $sheet->getCell($columnLetter($headings['NO REKENING']) . $dataRow);

        unlink($tmpPath);

        $this->assertSame('00123', $idCell->getValue());
        $this->assertSame('0033445566778899', $nikCell->getValue());
        $this->assertSame('0012345678', $bankCell->getValue());
    }
}
