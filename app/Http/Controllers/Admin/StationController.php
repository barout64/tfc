<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The TFC administrator: the stations (vituo) that report daily sales. */
class StationController extends Controller
{
    public function index(): View
    {
        return view('admin.stations.index', [
            'stations' => Station::query()->withCount('users')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.stations.form', ['station' => new Station(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $station = new Station;
        $this->save($request, $station);
        Activity::record('added_station', null, $station->name);

        return redirect()->route('admin.stations.index')->with('status', "{$station->name} added.");
    }

    public function edit(Station $station): View
    {
        return view('admin.stations.form', ['station' => $station]);
    }

    public function update(Request $request, Station $station): RedirectResponse
    {
        $this->save($request, $station);
        Activity::record('changed_station', null, $station->name);

        return redirect()->route('admin.stations.index')->with('status', "{$station->name} saved.");
    }

    private function save(Request $request, Station $station): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('stations')->ignore($station->id)],
            'location' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);

        $station->fill([
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ])->save();
    }
}
