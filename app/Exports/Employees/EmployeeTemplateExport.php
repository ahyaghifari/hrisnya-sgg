<?php

namespace App\Exports\Employees;

use App\Exports\Employees\Sheets\EmployeeTemplateSheet;
use App\Exports\Employees\Sheets\MasterDataSheet;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use App\Models\Master\Religions;
use App\Models\Role;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeeTemplateExport implements WithMultipleSheets
{
    public function __construct(protected ?string $companyId = null) {}

    public function sheets(): array
    {
        $cid = $this->companyId;

        $religion    = Religions::orderBy('name')->value('name'); // agama: master data global, tidak per company
        $jobPosition = JobPositions::when($cid, fn ($q) => $q->where('company_id', $cid))->orderBy('name')->value('name');
        $pangkat     = Pangkats::when($cid, fn ($q) => $q->where('company_id', $cid))->orderBy('name')->value('name');
        $kelas       = Kelas::when($cid, fn ($q) => $q->where('company_id', $cid))->orderBy('name')->value('name');
        $employeeType = EmployeeTypes::when($cid, fn ($q) => $q->where('company_id', $cid))->orderBy('name')->value('name');
        $role        = Role::when($cid, fn ($q) => $q->where('company_id', $cid))->orderBy('name')->value('name');

        $sampleRow = [
            'Budi Santoso',          // Nama*
            'budi.santoso@email.com',// Email*
            'Password123!',          // Password*
            '12345678910',                      // NIP (auto-generated)
            'Budi',                  // Panggilan
            'Laki-laki',             // Jenis Kelamin
            'Surabaya',              // Tempat Lahir
            '1990-05-15',            // Tanggal Lahir
            '3578021505900001',      // No NIK
            '3578021505900002',      // No KK
            '0001234567890',         // No BPJS Kesehatan
            '9876543210001',         // No BPJS Ketenagakerjaan
            '1234567890',            // No Rekening
            'Budi Santoso',          // Nama Rekening
            '12.345.678.9-012.000',  // NPWP
            'Indonesia',             // Negara
            'Jawa Timur',            // Provinsi
            'Surabaya',              // Kota
            'Sukolilo',              // Kecamatan
            'Keputih',               // Kelurahan
            'Jl. Contoh No. 1',      // Alamat
            '60111',                 // Kode Pos
            '01',                    // RT
            '02',                    // RW
            '081234567890',          // No Telepon
            '081234567890',          // No WhatsApp
            $religion ?? '',         // Agama
            'BCA',                   // Bank
            $jobPosition ?? '',      // Jabatan
            $pangkat ?? '',          // Pangkat
            $kelas ?? '',            // Kelas
            $employeeType ?? '',     // Tipe Karyawan
            '2024-01-01',            // Tanggal Bergabung
            $role ?? '',             // Role
        ];

        return [
            new EmployeeTemplateSheet($sampleRow),

            new MasterDataSheet(
                'Agama',
                'Nama Agama',
                Religions::orderBy('name')->pluck('name')->toArray()
            ),

            new MasterDataSheet(
                'Jabatan',
                'Nama Jabatan',
                JobPositions::when($cid, fn ($q) => $q->where('company_id', $cid))
                    ->orderBy('name')
                    ->pluck('name')
                    ->toArray()
            ),

            new MasterDataSheet(
                'Pangkat',
                'Nama Pangkat',
                Pangkats::when($cid, fn ($q) => $q->where('company_id', $cid))
                    ->orderByDesc('level')
                    ->pluck('name')
                    ->toArray()
            ),

            new MasterDataSheet(
                'Kelas',
                'Nama Kelas',
                Kelas::when($cid, fn ($q) => $q->where('company_id', $cid))
                    ->orderBy('name')
                    ->pluck('name')
                    ->toArray()
            ),

            new MasterDataSheet(
                'Tipe_Karyawan',
                'Nama Tipe Karyawan',
                EmployeeTypes::when($cid, fn ($q) => $q->where('company_id', $cid))
                    ->orderBy('name')
                    ->pluck('name')
                    ->toArray()
            ),

            new MasterDataSheet(
                'Role',
                'Nama Role',
                Role::when($cid, fn ($q) => $q->where('company_id', $cid))
                    ->orderBy('name')
                    ->pluck('name')
                    ->toArray()
            ),
        ];
    }
}
