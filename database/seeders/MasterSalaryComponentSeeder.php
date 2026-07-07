<?php

namespace Database\Seeders;

use App\Enum\Salary\CalculationTypeEnum;
use App\Enum\Salary\SalaryComponentNameEnum;
use App\Enum\Salary\SalaryComponentTypeEnum;
use App\Models\Payroll\MasterSalaryComponents;
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
                'name' => SalaryComponentNameEnum::GAJI_POKOK->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::EARNING->value,
            ],
            [
                'name' => SalaryComponentNameEnum::TUNJANGAN_JABATAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::EARNING->value,
            ],
            [
                'name' => SalaryComponentNameEnum::TUNJANGAN_TRANSPORTASI->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::EARNING->value,
            ],
//            [
//                'name' => SalaryComponentNameEnum::INSENTIF->value,
//                'calculation_type' => CalculationTypeEnum::FIXED->value,
//                'type' => SalaryComponentTypeEnum::EARNING->value,
//            ],
            [
                'name' => SalaryComponentNameEnum::JASA_PELAYANAN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::EARNING->value,
            ],
            [
                'name' => SalaryComponentNameEnum::TUNJANGAN_MASA_KERJA->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::EARNING->value,
            ],
            [
                'name' => SalaryComponentNameEnum::LEMBUR->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::EARNING->value,
            ],
            [
                'name' => SalaryComponentNameEnum::CUTI->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::DEDUCTION->value,
            ],
            [
                'name' => SalaryComponentNameEnum::IZIN->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::DEDUCTION->value,
            ],
            [
                'name' => SalaryComponentNameEnum::LATE_ARRIVAL->value,
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'type' => SalaryComponentTypeEnum::DEDUCTION->value,
            ],
            [
                'name' => SalaryComponentNameEnum::BPJS_KESEHATAN->value,
                'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
                'type' => SalaryComponentTypeEnum::DEDUCTION->value,
            ],
            [
                'name' => SalaryComponentNameEnum::BPJS_KETENAGA_KERJAAN->value,
                'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
                'type' => SalaryComponentTypeEnum::DEDUCTION->value,
            ],
            [
                'name' => SalaryComponentNameEnum::DOCTOR_TAX->value,
                'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
                'type' => SalaryComponentTypeEnum::DEDUCTION->value,
            ],
        ];

        $validNames = array_column($items, 'name');

        MasterSalaryComponents::whereNotIn('name', $validNames)->delete();

        foreach ($items as $item) {
            MasterSalaryComponents::updateOrCreate(
                ['name' => $item['name']],
                [
                    'calculation_type' => $item['calculation_type'],
                    'type' => $item['type'],
                    'is_active' => true,
                    'formula_key' => null,
                    'default_value' => null,
                    'note' => null,
                ]
            );
        }
    }
}
