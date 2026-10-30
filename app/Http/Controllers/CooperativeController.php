<?php

namespace App\Http\Controllers;

use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\District;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CooperativeController extends Controller
{
    public function index(): View
    {
        return view('cooperatives.index', [
            'cooperatives' => Cooperative::with(['region', 'crop'])->withCount('farmers')->withSum('sales', 'total_bags')->orderBy('name')->paginate(25),
        ]);
    }

    public function show(Cooperative $cooperative): View
    {
        return view('cooperatives.show', [
            'cooperative' => $cooperative->load(['region', 'crop']),
            'farmers' => $cooperative->farmers()->latest()->take(20)->get(),
            'sales' => $cooperative->sales()->with('warehouse')->latest('sold_at')->take(20)->get(),
        ]);
    }

    public function create(): View
    {
        return view('cooperatives.create', [
            'regions' => Region::orderBy('name')->get(),
            'districts' => District::orderBy('name')->get(['id', 'region_id', 'name']),
            'crops' => Crop::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'registration_no' => ['nullable', 'string', 'max:50', 'unique:cooperatives,registration_no'],
            'region_id' => ['required', 'exists:regions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'crop_id' => ['nullable', 'exists:crops,id'],
            'chairperson' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'members_count' => ['required', 'integer', 'min:0'],
        ]);

        $cooperative = Cooperative::create($data);

        return redirect()->route('cooperatives.show', $cooperative)->with('success', __('Cooperative saved.'));
    }
}
