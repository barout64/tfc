<?php

namespace App\Http\Controllers;

use App\Models\Advisory;
use App\Models\Crop;
use App\Models\Farmer;
use App\Models\Region;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Agronomic advice published on the portal and optionally pushed by SMS to matching farmers.
 */
class AdvisoryController extends Controller
{
    public function index(): View
    {
        return view('advisories.index', [
            'advisories' => Advisory::with(['crop', 'region'])->latest()->paginate(15),
            'crops' => Crop::orderBy('name')->get(),
            'regions' => Region::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'sms_text' => ['nullable', 'string', 'max:320'],
            'crop_id' => ['nullable', 'exists:crops,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
        ]);

        $advisory = Advisory::create([...$data, 'published_at' => now(), 'created_by' => $request->user()->id]);

        $sent = 0;
        if (! empty($data['sms_text']) && $request->boolean('send_sms')) {
            Farmer::query()
                ->where('status', 'active')
                ->when($data['crop_id'] ?? null, fn ($q, $id) => $q->where('main_crop_id', $id))
                ->when($data['region_id'] ?? null, fn ($q, $id) => $q->where('region_id', $id))
                ->chunkById(500, function ($farmers) use ($sms, $data, &$sent) {
                    foreach ($farmers as $farmer) {
                        $sms->send($farmer->phone, 'TFC: '.$data['sms_text'], 'advisory', $farmer);
                        $sent++;
                    }
                });
            $advisory->update(['sms_recipients' => $sent]);
        }

        return back()->with('success', __('Advisory published.').($sent ? ' '.__('SMS sent to :count farmers.', ['count' => number_format($sent)]) : ''));
    }
}
