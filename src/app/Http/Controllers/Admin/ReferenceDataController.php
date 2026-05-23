<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\EmploymentType;
use App\Models\JobCategory;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReferenceDataController extends Controller
{
    public function index(): View
    {
        return view('admin.reference-data.index', [
            'countries'       => Country::orderBy('name')->get(),
            'locations'       => Location::with('country')->orderBy('name')->get(),
            'categories'      => JobCategory::orderBy('name')->get(),
            'employmentTypes' => EmploymentType::orderBy('name')->get(),
        ]);
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:countries,name'],
        ]);

        Country::create(['name' => trim($request->string('name')->toString()), 'is_active' => true]);

        return back()->with('success', 'Country added.');
    }

    public function destroyCountry(Country $country): RedirectResponse
    {
        if ($country->locations()->exists()) {
            return back()->with('error', 'Cannot delete a country that has locations. Remove its locations first.');
        }

        $country->delete();

        return back()->with('success', 'Country removed.');
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name'       => ['required', 'string', 'max:150'],
        ]);

        $exists = Location::where('country_id', $request->integer('country_id'))
            ->where('name', trim($request->string('name')->toString()))
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'This location already exists for the selected country.']);
        }

        Location::create([
            'country_id' => $request->integer('country_id'),
            'name'       => trim($request->string('name')->toString()),
            'is_active'  => true,
        ]);

        return back()->with('success', 'Location added.');
    }

    public function destroyLocation(Location $location): RedirectResponse
    {
        $location->delete();

        return back()->with('success', 'Location removed.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:job_categories,name'],
        ]);

        JobCategory::create(['name' => trim($request->string('name')->toString()), 'is_active' => true]);

        return back()->with('success', 'Category added.');
    }

    public function destroyCategory(JobCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Category removed.');
    }

    public function storeEmploymentType(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:employment_types,name'],
        ]);

        EmploymentType::create(['name' => trim($request->string('name')->toString()), 'is_active' => true]);

        return back()->with('success', 'Employment type added.');
    }

    public function destroyEmploymentType(EmploymentType $employmentType): RedirectResponse
    {
        $employmentType->delete();

        return back()->with('success', 'Employment type removed.');
    }
}
