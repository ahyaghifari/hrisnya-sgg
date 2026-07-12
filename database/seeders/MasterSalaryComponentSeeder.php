<?php

namespace Database\Seeders;

use App\Enum\Salary\CalculationTypeEnum;
use App\Enum\Salary\SalaryComponentCategoryEnum;
use App\Models\SalaryComponent\SalaryComponents;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterSalaryComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'name' => 'Gaji Pokok',
                'category' => SalaryComponentCategoryEnum::GAJI_POKOK->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'Tunjangan Jabatan',
                'category' => SalaryComponentCategoryEnum::TUNJANGAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'Tunjangan Transportasi',
                'category' => SalaryComponentCategoryEnum::TUNJANGAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'Jasa Pelayanan',
                'category' => SalaryComponentCategoryEnum::JASA_PELAYANAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'Tunjangan Masa Kerja',
                'category' => SalaryComponentCategoryEnum::TUNJANGAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            // LEMBUR sengaja tidak diseed — tidak pernah dipakai (lembur dihitung langsung
            // dari OvertimeRateRules, bukan lookup katalog), baris "Lembur" yang lama juga
            // sudah dihapus dari katalog (2026-07-12, tidak pernah dipakai/yatim).
            [
                'name' => 'Cuti',
                'category' => SalaryComponentCategoryEnum::CUTI->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'Izin',
                'category' => SalaryComponentCategoryEnum::IZIN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'Keterlambatan',
                'category' => SalaryComponentCategoryEnum::KETERLAMBATAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
            ],
            [
                'name' => 'BPJS Kesehatan',
                'category' => SalaryComponentCategoryEnum::BPJS->value,
                'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
            ],
            [
                'name' => 'BPJS Ketenaga Kerjaan',
                'category' => SalaryComponentCategoryEnum::BPJS->value,
                'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
            ],
            [
                'name' => 'Pajak Jasa Dokter',
                'category' => SalaryComponentCategoryEnum::PAJAK_JASA_DOKTER->value,
                'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
            ],
        ];

        $validNames = array_column($items, 'name');

        SalaryComponents::whereNotIn('name', $validNames)->delete();

        foreach ($items as $item) {
            // 'type' tidak diset manual di sini — otomatis diturunkan dari 'category'
            // lewat SalaryComponents::booted() (lihat SalaryComponentCategoryEnum::type()).
            SalaryComponents::updateOrCreate(
                ['name' => $item['name']],
                [
                    'category' => $item['category'],
                    'calculation_type' => $item['calculation_type'],
                    'is_active' => true,
                    'formula_key' => null,
                    'default_value' => null,
                    'note' => null,
                ]
            );
        }
    }
}
