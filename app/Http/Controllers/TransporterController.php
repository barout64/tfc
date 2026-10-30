<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Season;
use App\Models\Transporter;
use App\Models\TransporterPayment;
use App\Services\InsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransporterController extends Controller
{
    public function index(InsightService $insights): View
    {
        $performance = $insights->transporterPerformance(Season::current())->keyBy(fn ($row) => $row->transporter->id);

        return view('transporters.index', [
            'transporters' => Transporter::withCount(['vehicles', 'drivers'])->orderBy('name')->paginate(25),
            'performance' => $performance,
        ]);
    }

    public function show(Transporter $transporter, InsightService $insights): View
    {
        return view('transporters.show', [
            'transporter' => $transporter->load(['vehicles', 'drivers']),
            'performance' => $insights->transporterPerformance(Season::current())->firstWhere(fn ($row) => $row->transporter->id === $transporter->id),
            'trips' => $transporter->dispatches()->with(['fromWarehouse', 'toWarehouse', 'vehicle', 'items.batch.product'])->latest()->take(15)->get(),
            'payments' => $transporter->payments()->with('dispatch')->latest()->take(15)->get(),
        ]);
    }

    public function create(): View
    {
        return view('transporters.form', ['transporter' => new Transporter(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $transporter = Transporter::create($this->validated($request));
        AuditLog::record('transporter.created', "Registered transporter {$transporter->name}", $transporter);

        return redirect()->route('transporters.show', $transporter)->with('success', __('Transporter saved. Now add vehicles and drivers.'));
    }

    public function edit(Transporter $transporter): View
    {
        return view('transporters.form', compact('transporter'));
    }

    public function update(Request $request, Transporter $transporter): RedirectResponse
    {
        $transporter->update($this->validated($request));

        return redirect()->route('transporters.show', $transporter)->with('success', __('Transporter updated.'));
    }

    public function storeVehicle(Request $request, Transporter $transporter): RedirectResponse
    {
        $data = $request->validate([
            'plate_number' => ['required', 'string', 'max:30', 'unique:vehicles,plate_number'],
            'type' => ['required', Rule::in(['truck', 'trailer', 'wagon'])],
            'capacity_tonnes' => ['required', 'numeric', 'min:1', 'max:200'],
        ]);
        $transporter->vehicles()->create([...$data, 'plate_number' => strtoupper($data['plate_number'])]);

        return back()->with('success', __('Vehicle added.'));
    }

    public function storeDriver(Request $request, Transporter $transporter): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'license_number' => ['nullable', 'string', 'max:50'],
        ]);
        $transporter->drivers()->create($data);

        return back()->with('success', __('Driver added.'));
    }

    public function payments(Request $request): View
    {
        $payments = TransporterPayment::with(['transporter', 'dispatch.fromWarehouse', 'dispatch.toWarehouse'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('transporters.payments', [
            'payments' => $payments,
            'totals' => TransporterPayment::selectRaw('status, SUM(net_amount) as amount, COUNT(*) as total')->groupBy('status')->get()->keyBy('status'),
        ]);
    }

    public function updatePayment(Request $request, TransporterPayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'pay'])],
            'payment_reference' => ['required_if:action,pay', 'nullable', 'string', 'max:100'],
        ]);

        if ($data['action'] === 'approve' && $payment->status === 'pending') {
            $payment->update(['status' => 'approved', 'approved_by' => $request->user()->id]);
        } elseif ($data['action'] === 'pay' && $payment->status === 'approved') {
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'payment_reference' => $data['payment_reference']]);
        } else {
            return back()->with('error', __('Payment cannot move to that status.'));
        }

        AuditLog::record('transporter_payment.'.$data['action'], "Transporter payment #{$payment->id} {$data['action']}", $payment);

        return back()->with('success', __('Payment updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tin' => ['nullable', 'string', 'max:30'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'rate_per_tonne_km' => ['required', 'numeric', 'min:0'],
            'loss_penalty_per_bag' => ['required', 'numeric', 'min:0'],
            'contract_expires_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);
    }
}
