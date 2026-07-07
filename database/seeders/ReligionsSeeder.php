<?php

namespace Database\Seeders;

use App\Models\Master\Religions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReligionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $religions = [
            ['name' => 'Islam'],
            ['name' => 'Kristen'],
            ['name' => 'Katolik'],
            ['name' => 'Hindu'],
            ['name' => 'Buddha'],
            ['name' => 'Konghucu'],
            ['name' => 'Lainnya'],
        ];

        foreach ($religions as $religion) {
            Religions::create($religion);
        }
    }
}
