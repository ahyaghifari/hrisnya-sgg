<?php

namespace Database\Seeders;

use App\Models\DoctorService\DoctorServiceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DoctorServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Rawat Jalan dr. Spesialis / Umum',
                'description' => 'Pelayanan rawat jalan untuk konsultasi dan tindakan dokter spesialis/umum.',
            ],
            [
                'name' => 'Rawat Inap Umum',
                'description' => 'Pelayanan rawat inap umum termasuk konsultasi dan perawatan.',
            ],
            [
                'name' => 'Rawat Inap Operasi - Bedah Umum, Gigi Anak, THT',
                'description' => 'Operasi bedah umum, gigi anak, dan THT.',
            ],
            [
                'name' => 'Rawat Inap Operasi - Ortopedi & Traumatologi',
                'description' => 'Operasi ortopedi dan traumatologi.',
            ],
            [
                'name' => 'Rawat Inap Operasi - Urologi',
                'description' => 'Operasi urologi.',
            ],
            [
                'name' => 'Rawat Inap Operasi - Bedah Saraf',
                'description' => 'Operasi bedah saraf.',
            ],
            [
                'name' => 'Rawat Inap Operasi - Bedah Gigi & Mulut',
                'description' => 'Operasi khusus gigi dan mulut.',
            ],
            [
                'name' => 'Rawat Inap Operasi Kebidanan Bukan SC',
                'description' => 'Operasi kebidanan non-cesar.',
            ],
            [
                'name' => 'Rawat Inap Operasi SC (1 SEP / Bayi Sehat)',
                'description' => 'Operasi SC untuk bayi sehat (1 SEP).',
            ],
            [
                'name' => 'Rawat Inap Operasi SC (2 SEP / Bayi Sakit)',
                'description' => 'Operasi SC untuk bayi sakit (2 SEP).',
            ],
            [
                'name' => 'Rawat Inap Partus Normal (1 SEP / Bayi Sehat)',
                'description' => 'Persalinan normal untuk bayi sehat (1 SEP).',
            ],
            [
                'name' => 'Rawat Inap Partus Normal (2 SEP / Bayi Sakit)',
                'description' => 'Persalinan normal untuk bayi sakit (2 SEP).',
            ],
        ];

        foreach ($categories as $category) {
            DoctorServiceCategory::firstOrCreate(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
