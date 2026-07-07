<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssessmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $type = [
            [
                'name' => 'Performance Appraisal'
            ],
            [
                'name' => 'Culture Assessment'
            ],
            [
                'name' => 'Competency Assessment'
            ]
        ];

        foreach ($type as $key => $value) {
            \App\Models\Assessment\AssessmentTypes::create($value);
        }
    }
}
