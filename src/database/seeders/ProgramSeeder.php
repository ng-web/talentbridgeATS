<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            [
                'name' => 'Local Employment Opportunities',
                'description' => 'Browse current local job openings and employment pathways.',
            ],
            [
                'name' => 'Work-Study Abroad Program',
                'description' => 'Explore structured work-study opportunities and related placements.',
            ],
            [
                'name' => 'Internship and Early Career Pathways',
                'description' => 'Discover internships and early-career development opportunities.',
            ],
        ];

        foreach ($programs as $program) {
            Program::updateOrCreate(
                ['slug' => Str::slug($program['name'])],
                [
                    'name' => $program['name'],
                    'description' => $program['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}