<?php

namespace App\Exports\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export data karyawan lengkap (akun+pribadi+pekerjaan), format ikut contoh Excel finance
 * (rekap gaji): dikelompokkan 2 tingkat DEPARTEMEN > DIVISI (org level 1 & 2, lihat
 * UserProfiles::getDepartmentAttribute()/getDivisionAttribute()) dengan baris judul disisipkan
 * di antara baris karyawan, nomor urut reset per grup divisi - pola sama persis dengan
 * PayrollSlipRekapExport (§ groupHeaderRow, sortWithinDivision), cuma ditambah 1 tingkat
 * (departemen) di atasnya karena data karyawan di sini lintas departemen.
 *
 * Karyawan tanpa departemen DAN tanpa divisi sama sekali dikelompokkan ke label khusus di
 * paling atas: "Presiden Direktur" kalau company-nya HOLDING (kode '0000'), "Manajemen" kalau
 * bukan - dicek per company employee sendiri (bukan 1 companyId tetap) karena hasil export bisa
 * lintas company kalau user yang browse-nya boleh lihat semua company. Company BUKAN holding yang
 * punya departemen tapi TIDAK punya divisi juga masuk "Manajemen" (top-level, bukan dinested ke
 * departemennya) - instruksi user.
 *
 * Kolom didefinisikan lewat columnDefinitions() (bukan array literal terpisah) supaya
 * headings()/employeeRow()/bindValue()/lebar kolom SATU sumber kebenaran - kolom "Perusahaan"
 * bisa switch tampil/tidak (lihat $includeCompanyColumn) tanpa harus mengubah index kolom lain
 * secara manual di banyak tempat.
 */
class KaryawanDataExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder, WithEvents
{
    private const NO_GROUP_LABEL_HOLDING = 'Presiden Direktur';

    private const NO_GROUP_LABEL_REGULAR = 'Manajemen';

    private const HOLDING_COMPANY_CODE = '0000';

    /** Baris (1-based, sudah termasuk offset heading) yang berisi judul departemen/divisi - buat styling di registerEvents(). */
    private array $departmentHeaderRows = [];

    private array $divisionHeaderRows = [];

    /** Baris (1-based) yang berisi data karyawan asli (bukan baris judul) - dipakai buat
     * rata-tengah kolom NIP TANPA ikut merata-tengahkan teks judul departemen/divisi yang
     * numpang di kolom yang sama (lihat headerRow()). */
    private array $employeeRows = [];

    /** @var array<string, string> company_id => code, di-cache sekali biar tidak N+1 per karyawan. */
    private array $companyCodes;

    /** @var array<int, array{label: string, get: \Closure, forceText: bool|string, width: ?int}> */
    private array $columns;

    /**
     * @param  Collection<int, User>  $employees
     * @param  bool  $includeCompanyColumn  Kolom "Perusahaan" cuma tampil kalau filter di
     *   halaman Karyawan sedang "Semua perusahaan" (companyId null) - kalau user sudah filter ke
     *   1 perusahaan tertentu, kolom itu berisi nilai yang sama di semua baris, mubazir.
     */
    public function __construct(protected Collection $employees, protected bool $includeCompanyColumn = true)
    {
        $this->companyCodes = Companies::whereIn('id', $employees->pluck('company_id')->unique())
            ->pluck('code', 'id')
            ->all();

        $this->columns = $this->buildColumns();
    }

    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    /** @return array<int, array{label: string, get: \Closure, forceText: bool|string, width: ?int}> */
    private function buildColumns(): array
    {
        $profileOf = fn (User $employee) => $employee->profile;

        $definitions = [
            ['label' => 'No', 'forceText' => false, 'width' => null, 'get' => fn ($_, $no) => $no],
            ['label' => 'NIP', 'forceText' => 'nip', 'width' => 19, 'get' => fn (User $e) => $e->nip],
            ['label' => 'Nama', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $e->name],
            ['label' => 'Email Akun', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $e->email],
            ['label' => 'Status Akun', 'forceText' => false, 'width' => 12, 'get' => fn (User $e) => $e->is_active ? 'Aktif' : 'Tidak Aktif'],
            ...($this->includeCompanyColumn ? [
                ['label' => 'Perusahaan', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $e->company?->name],
            ] : []),
            ['label' => 'Role', 'forceText' => false, 'width' => 22, 'get' => fn (User $e) => $e->getRoleNames()->join(', ')],
            ['label' => 'Nama Panggilan', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->nickname],
            ['label' => 'Jenis Kelamin', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => match ($profileOf($e)?->gender) {
                'laki_laki' => 'Laki-laki',
                'perempuan' => 'Perempuan',
                default => $profileOf($e)?->gender,
            }],
            ['label' => 'Status Perkawinan', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->marital_status],
            ['label' => 'Tempat Lahir', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->place_of_birth],
            ['label' => 'Tanggal Lahir', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->date_of_birth],
            ['label' => 'No. NIK', 'forceText' => true, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->no_nik],
            ['label' => 'No. KK', 'forceText' => true, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->no_kk],
            ['label' => 'No. BPJS Kesehatan', 'forceText' => true, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->no_bpjs_kes],
            ['label' => 'No. BPJS Ketenagakerjaan', 'forceText' => true, 'width' => 16, 'get' => fn (User $e) => $profileOf($e)?->no_bpjs_ket],
            ['label' => 'Bank', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->bank_name],
            ['label' => 'No. Rekening', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->bank_account_number],
            ['label' => 'Nama Pemilik Rekening', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->bank_account_name],
            ['label' => 'NPWP', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->npwp],
            ['label' => 'Negara', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->country],
            ['label' => 'Provinsi', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->province],
            ['label' => 'Kota/Kabupaten', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->city],
            ['label' => 'Kecamatan', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->district],
            ['label' => 'Kelurahan/Desa', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->village],
            ['label' => 'Alamat', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->address],
            ['label' => 'Kode Pos', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->postal_code],
            ['label' => 'RT', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->rt],
            ['label' => 'RW', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->rw],
            ['label' => 'No. Telepon', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->phone],
            ['label' => 'No. WhatsApp', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->whatsapp],
            ['label' => 'Email Pribadi', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->email_pribadi],
            ['label' => 'Agama', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->religion?->name],
            ['label' => 'Jabatan', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->jobPosition?->name],
            ['label' => 'Pangkat', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->pangkat?->name],
            ['label' => 'Kelas', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->kelas?->name],
            ['label' => 'Tipe Karyawan', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->employeeType?->name],
            ['label' => 'Tanggal Bergabung', 'forceText' => false, 'width' => null, 'get' => fn (User $e) => $profileOf($e)?->joining_date],
        ];

        return $definitions;
    }

    public function collection(): Collection
    {
        $isHolding = fn (User $employee) => ($this->companyCodes[$employee->company_id] ?? null) === self::HOLDING_COMPANY_CODE;

        // Grup atas ("Presiden Direktur"/"Manajemen") SELALU di paling atas ('0_'), baru
        // departemen asli diurutkan alfabetis di bawahnya ('1_') - sama pola dengan
        // PayrollSlipRekapExport. Karyawan TANPA divisi di company BUKAN holding masuk
        // "Manajemen" langsung (top-level, tidak dinested ke departemennya) MESKIPUN dia punya
        // departemen - instruksi user, beda dari company holding yang tetap dinested ke
        // departemen (subgrup "(Tanpa Divisi)") kalau departemennya ada.
        $byDepartment = $this->employees
            ->groupBy(function (User $employee) use ($isHolding) {
                $department = $employee->profile?->department?->name;
                $division = $employee->profile?->division?->name;

                if ($division) {
                    return $department ?? '';
                }

                if (! $isHolding($employee)) {
                    return self::NO_GROUP_LABEL_REGULAR;
                }

                return $department ?: self::NO_GROUP_LABEL_HOLDING;
            })
            ->sortBy(fn ($rows, $label) => (in_array($label, [self::NO_GROUP_LABEL_HOLDING, self::NO_GROUP_LABEL_REGULAR], true) ? '0_' : '1_') . $label, SORT_STRING);

        $rows = collect();
        $rowNumber = 1; // baris 1 = heading, data mulai baris 2

        foreach ($byDepartment as $departmentLabel => $departmentEmployees) {
            $isNoGroup = in_array($departmentLabel, [self::NO_GROUP_LABEL_HOLDING, self::NO_GROUP_LABEL_REGULAR], true);

            $rowNumber++;
            $this->departmentHeaderRows[] = $rowNumber;
            $rows->push($this->headerRow($departmentLabel ?: '(Tanpa Departemen)'));

            $byDivision = $isNoGroup
                ? collect([$departmentLabel => $departmentEmployees])
                : $departmentEmployees->groupBy(fn (User $e) => $e->profile?->division?->name ?? '(Tanpa Divisi)')
                    ->sortKeys(SORT_STRING);

            foreach ($byDivision as $divisionLabel => $divisionEmployees) {
                if (! $isNoGroup) {
                    $rowNumber++;
                    $this->divisionHeaderRows[] = $rowNumber;
                    $rows->push($this->headerRow($divisionLabel));
                }

                $no = 1; // reset per grup divisi (ikut permintaan format payroll)
                foreach ($this->sortWithinGroup($divisionEmployees) as $employee) {
                    $rowNumber++;
                    $this->employeeRows[] = $rowNumber;
                    $rows->push($this->employeeRow($employee, $no));
                    $no++;
                }
            }
        }

        return $rows;
    }

    /** Urutan dalam 1 grup: pangkat paling tinggi dulu, lalu tanggal bergabung paling lama,
     * baru nama - persis kriteria PayrollSlipRekapExport::sortWithinDivision(). Karyawan tanpa
     * pangkat/tanggal masuk ditaruh paling belakang di kriteria itu. */
    private function sortWithinGroup(Collection $employees): Collection
    {
        return $employees->sort(function (User $a, User $b) {
            $levelCompare = ($b->profile?->pangkat?->level ?? -INF) <=> ($a->profile?->pangkat?->level ?? -INF);
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            $joinA = $a->profile?->joining_date;
            $joinB = $b->profile?->joining_date;
            $tenureCompare = ($joinA ? strtotime($joinA) : PHP_INT_MAX) <=> ($joinB ? strtotime($joinB) : PHP_INT_MAX);
            if ($tenureCompare !== 0) {
                return $tenureCompare;
            }

            return strcmp((string) $a->name, (string) $b->name);
        })->values();
    }

    private function headerRow(string $label): array
    {
        return array_pad(['', $label], count($this->columns), '');
    }

    private function employeeRow(User $employee, int $no): array
    {
        return array_map(fn (array $column) => $column['get']($employee, $no), $this->columns);
    }

    /** NIP cuma dipaksa text kalau diawali '0' (nol di depan hilang kalau dianggap angka), kolom
     * bertanda forceText=true SELALU dipaksa text - sama teknik dengan
     * PayrollSlipRekapExport::bindValue() (TYPE_STRING lewat WithCustomValueBinder, BUKAN
     * literal petik di depan nilai - kalau diformat '@' SETELAH ditulis, nilai numerik-looking
     * sudah keburu dikonversi float & nol di depannya hilang duluan). */
    public function bindValue(Cell $cell, $value)
    {
        $columnIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
        $column = $this->columns[$columnIndex] ?? null;

        $forceText = $column
            && $value !== null && $value !== ''
            && ($column['forceText'] === true || ($column['forceText'] === 'nip' && str_starts_with((string) $value, '0')));

        if ($forceText) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->columns));

                foreach ($this->departmentHeaderRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'italic' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9CA3AF']],
                    ]);
                }

                foreach ($this->divisionHeaderRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    ]);
                }

                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                ]);

                // Baris data rata atas (bukan default bottom) - lebih rapi buat kolom yang
                // di-wrap (Role/Status Akun/No. BPJS Ketenagakerjaan) & kolom Alamat yang bisa panjang.
                $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                // Kolom bertanda 'width' (Status Akun/Role/No. BPJS Ketenagakerjaan) autosize-nya
                // melebar berlebihan - dipaskan manual & di-wrap, sisanya autosize seperti biasa.
                foreach ($this->columns as $index => $column) {
                    $letter = Coordinate::stringFromColumnIndex($index + 1);

                    if ($column['width']) {
                        $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth($column['width']);
                        $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getAlignment()->setWrapText(true);
                    } else {
                        $sheet->getColumnDimension($letter)->setAutoSize(true);
                    }
                }

                // NIP rata tengah - CUMA baris data karyawan, bukan seluruh kolomnya (baris judul
                // departemen/divisi numpang di kolom yang sama lewat headerRow(), kalau kena
                // center jadi ikut ke tengah juga - salah).
                $nipColumnIndex = array_search('NIP', array_column($this->columns, 'label'), true);
                if ($nipColumnIndex !== false) {
                    $letter = Coordinate::stringFromColumnIndex($nipColumnIndex + 1);
                    foreach ($this->employeeRows as $rowNumber) {
                        $sheet->getStyle("{$letter}{$rowNumber}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->freezePane('A2');
            },
        ];
    }
}
