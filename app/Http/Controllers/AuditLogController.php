<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('audit-logs.index', [
            'logs' => AuditLog::with('user')
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
                ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
