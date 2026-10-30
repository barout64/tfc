<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\CropRecommendation;
use App\Models\Product;
use App\Models\Season;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Products, seasons, crop recommendations and suppliers.
 */
class MasterDataController extends Controller
{
    public function index(): View
    {
        return view('master-data.index', [
            'products' => Product::orderBy('id')->get(),
            'seasons' => Season::latest('starts_on')->get(),
            'crops' => Crop::with('recommendations.product')->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:products,code'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(['planting', 'top_dressing', 'compound', 'lime', 'other'])],
            'package_kg' => ['required', 'integer', 'min:1', 'max:1000'],
            'nutrients' => ['nullable', 'string', 'max:30'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'low_stock_bags' => ['required', 'integer', 'min:0'],
        ]);
        Product::create($data);

        return back()->with('success', __('Product added.'));
    }

    public function storeSeason(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:seasons,name'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);
        Season::create($data);

        return back()->with('success', __('Season added.'));
    }

    public function activateSeason(Season $season): RedirectResponse
    {
        DB::transaction(function () use ($season) {
            Season::query()->update(['is_active' => false]);
            $season->update(['is_active' => true]);
        });

        return back()->with('success', __(':season is now the active season.', ['season' => $season->name]));
    }

    public function storeRecommendation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'crop_name' => ['required', 'string', 'max:60'],
            'product_id' => ['required', 'exists:products,id'],
            'stage' => ['required', Rule::in(['planting', 'top_dressing'])],
            'bags_per_acre' => ['required', 'numeric', 'min:0.1', 'max:20'],
        ]);

        $crop = Crop::firstOrCreate(['name' => ucfirst($data['crop_name'])]);
        CropRecommendation::updateOrCreate(
            ['crop_id' => $crop->id, 'product_id' => $data['product_id'], 'stage' => $data['stage']],
            ['bags_per_acre' => $data['bags_per_acre']],
        );

        return back()->with('success', __('Recommendation saved.'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        Supplier::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:60'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
        ]));

        return back()->with('success', __('Supplier added.'));
    }
}
