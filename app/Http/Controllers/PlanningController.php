<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DistributionTarget;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Region;
use App\Models\Season;
use App\Services\InsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Season targets per region & product, and subsidised price setting.
 */
class PlanningController extends Controller
{
    public function index(InsightService $insights): View
    {
        $season = Season::current();

        return view('planning.index', [
            'season' => $season,
            'progress' => $insights->targetProgress($season),
            'targets' => DistributionTarget::with(['region', 'product'])->where('season_id', $season->id)->get()->groupBy('region_id'),
            'prices' => ProductPrice::with(['product', 'region'])->where('season_id', $season->id)->latest('effective_from')->get(),
            'regions' => Region::orderBy('name')->get(),
            'products' => Product::active()->get(),
            'stock' => $insights->stockByProduct()->keyBy('id'),
        ]);
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'region_id' => ['required', 'exists:regions,id'],
            'product_id' => ['required', 'exists:products,id'],
            'target_tonnes' => ['required', 'numeric', 'min:0'],
        ]);

        DistributionTarget::updateOrCreate(
            ['season_id' => Season::current()->id, 'region_id' => $data['region_id'], 'product_id' => $data['product_id']],
            ['target_tonnes' => $data['target_tonnes']],
        );

        return back()->with('success', __('Target saved.'));
    }

    public function storePrice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'market_price' => ['required', 'numeric', 'min:0'],
            'subsidy_price' => ['required', 'numeric', 'min:0', 'lte:market_price'],
            'effective_from' => ['required', 'date'],
        ]);

        $price = ProductPrice::create([...$data, 'season_id' => Season::current()->id]);
        AuditLog::record('price.set', "Price set for {$price->product->code}: TZS {$price->subsidy_price}", $price, $data);

        return back()->with('success', __('Price saved.'));
    }
}
