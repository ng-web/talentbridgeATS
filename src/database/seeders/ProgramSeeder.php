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
                'name' => 'Summer Work & Travel Program',
                'age_range' => 'Ages 18-30',
                'description' => 'Work abroad during your summer break, meet friends from all over the world, and explore new destinations. Perfect for university students seeking seasonal work and cultural exchange.',
                'benefits' => [
                    'Earn while gaining work experience',
                    'Immerse yourself in a new culture',
                    'Build career skills and confidence',
                    'Travel during your free time',
                ],
                'typical_roles' => 'Hospitality, tourism, retail, amusement parks, attractions.',
                'display_order' => 1,
            ],
            [
                'name' => 'Internship Abroad Program',
                'age_range' => 'Ages 18-35',
                'description' => 'Gain professional experience in your field while living overseas. Perfect for students and recent graduates looking to stand out in the job market.',
                'benefits' => [
                    'Strengthen your resume with international experience',
                    'Build a global professional network',
                    'Improve career readiness',
                    'Experience life in a different country',
                ],
                'fields_available' => 'Business, marketing, education, hospitality, IT, and more.',
                'display_order' => 2,
            ],
            [
                'name' => 'Cultural Exchange & Volunteer Program',
                'age_range' => 'Ages 18+',
                'description' => 'Travel with purpose by contributing to community projects abroad. Combine meaningful service with cultural immersion.',
                'benefits' => [
                    'Support education, conservation, and community development',
                    'Learn new languages and traditions',
                    'Work alongside locals and fellow volunteers',
                    'Discover unique destinations off the beaten path',
                ],
                'display_order' => 3,
            ],
            [
                'name' => 'Au Pair Program',
                'age_range' => 'Ages 18-26',
                'description' => 'Live with a host family abroad, care for children, and enjoy cultural immersion. Great for travelers who love kids and want an affordable way to explore.',
                'benefits' => [
                    'Receive free accommodation, meals, and a stipend',
                    'Become part of a welcoming family',
                    'Improve language skills naturally',
                    'Travel during your free time',
                ],
                'display_order' => 4,
            ],
            [
                'name' => 'Camp Counselor Program',
                'age_range' => 'Ages 18-30',
                'description' => 'Spend your summer guiding and inspiring campers in a fun, energetic setting abroad.',
                'benefits' => [
                    'Lead sports, arts, outdoor adventures, and more',
                    'Build leadership and teamwork skills',
                    'Make lifelong friends from around the world',
                    'Explore your host country during breaks',
                ],
                'display_order' => 5,
            ],
            [
                'name' => 'H-2B Program',
                'age_range' => 'Ages 18+',
                'description' => 'Work in the United States for a temporary, seasonal position in industries like hospitality, landscaping, or construction. Ideal for those looking for longer-term seasonal work and cultural exchange.',
                'benefits' => [
                    'Gain U.S. work experience',
                    'Earn competitive wages',
                    'Experience American culture firsthand',
                    'Build valuable skills for your career',
                ],
                'display_order' => 6,
            ],
        ];

        $activeSlugs = collect($programs)
            ->map(fn (array $program) => Str::slug($program['name']))
            ->all();

        foreach ($programs as $program) {
            Program::updateOrCreate(
                ['slug' => Str::slug($program['name'])],
                [
                    'name' => $program['name'],
                    'age_range' => $program['age_range'],
                    'description' => $program['description'],
                    'benefits' => $program['benefits'],
                    'typical_roles' => $program['typical_roles'] ?? null,
                    'fields_available' => $program['fields_available'] ?? null,
                    'display_order' => $program['display_order'],
                    'is_active' => true,
                ]
            );
        }

        Program::whereNotIn('slug', $activeSlugs)->update(['is_active' => false]);
    }
}
