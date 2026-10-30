<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        return view('complaints.index', [
            'complaints' => Complaint::with(['region', 'assignee', 'farmer'])
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'counts' => Complaint::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => ComplaintStatus::options(),
            'categories' => ComplaintCategory::options(),
        ]);
    }

    public function show(Complaint $complaint): View
    {
        return view('complaints.show', [
            'complaint' => $complaint->load(['farmer', 'region', 'warehouse', 'assignee']),
            'staff' => User::where('is_active', true)->whereNotIn('role', ['agro_dealer', 'transporter'])->orderBy('name')->get(['id', 'name']),
            'statuses' => ComplaintStatus::options(),
        ]);
    }

    public function update(Request $request, Complaint $complaint, SmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'resolution' => ['required_if:status,resolved', 'nullable', 'string', 'max:2000'],
            'notify' => ['boolean'],
        ]);

        $status = ComplaintStatus::from($data['status']);
        $complaint->update([
            'status' => $status,
            'assigned_to' => $data['assigned_to'] ?? $complaint->assigned_to,
            'resolution' => $data['resolution'] ?? $complaint->resolution,
            'resolved_at' => $status === ComplaintStatus::Resolved ? now() : $complaint->resolved_at,
        ]);

        if ($status === ComplaintStatus::Resolved && $request->boolean('notify')) {
            $sms->send($complaint->phone, __('TFC: Lalamiko :ticket limeshughulikiwa. :resolution', [
                'ticket' => $complaint->ticket_number,
                'resolution' => str($complaint->resolution)->limit(100),
            ]), 'complaint', $complaint->farmer);
        }

        AuditLog::record('complaint.updated', "Complaint {$complaint->ticket_number} → {$status->value}", $complaint);

        return back()->with('success', __('Complaint updated.'));
    }
}
