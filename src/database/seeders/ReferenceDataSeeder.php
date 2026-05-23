<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\EmploymentType;
use App\Models\JobCategory;
use App\Models\Location;
use Illuminate\Database\Seeder;

final class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Countries
        $countries = [
            'Jamaica',
            'United States',
            'Canada',
            'United Kingdom',
        ];

        foreach ($countries as $name) {
            Country::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $jamaica = Country::where('name', 'Jamaica')->first();
        $usa     = Country::where('name', 'United States')->first();
        $canada  = Country::where('name', 'Canada')->first();
        $uk      = Country::where('name', 'United Kingdom')->first();

        // Locations
        $locationsByCountry = [
            $jamaica->id => [
                'Kingston', 'Montego Bay', 'Ocho Rios', 'Negril', 'Spanish Town',
                'Portmore', 'Mandeville', 'May Pen', 'St. Ann\'s Bay', 'Falmouth',
                'Black River', 'Savanna-la-Mar', 'Port Antonio', 'Linstead',
            ],
            $usa->id => [
                'Orlando, FL', 'Miami, FL', 'Cape Cod, MA', 'New York, NY',
                'Los Angeles, CA', 'Chicago, IL', 'Virginia Beach, VA',
                'Myrtle Beach, SC', 'Ocean City, MD', 'Williamsburg, VA',
                'Rehoboth Beach, DE', 'Wildwood, NJ', 'Bar Harbor, ME',
                'Aspen, CO', 'Jackson Hole, WY', 'Hilton Head, SC',
            ],
            $canada->id => [
                'Toronto, ON', 'Vancouver, BC', 'Calgary, AB', 'Banff, AB',
                'Whistler, BC', 'Montreal, QC', 'Ottawa, ON', 'Niagara Falls, ON',
            ],
            $uk->id => [
                'London', 'Edinburgh', 'Manchester', 'Birmingham',
                'Liverpool', 'Bristol', 'Glasgow', 'Brighton',
            ],
        ];

        foreach ($locationsByCountry as $countryId => $locations) {
            foreach ($locations as $name) {
                Location::firstOrCreate(
                    ['country_id' => $countryId, 'name' => $name],
                    ['is_active' => true]
                );
            }
        }

        // Job Categories
        $categories = [
            'Hospitality & Hotels',
            'Food & Beverage',
            'Customer Service',
            'Entertainment & Recreation',
            'Childcare',
            'Retail & Sales',
            'Landscaping & Grounds',
            'Agriculture',
            'Amusement & Theme Parks',
            'Administration',
            'Transportation',
            'Program Operations',
            'Music & Entertainment',
            'Sports & Fitness',
            'Healthcare Support',
            'Other',
        ];

        foreach ($categories as $name) {
            JobCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        // Employment Types
        $types = [
            'Full Time',
            'Part Time',
            'Seasonal',
            'Contract',
            'Temporary',
        ];

        foreach ($types as $name) {
            EmploymentType::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
