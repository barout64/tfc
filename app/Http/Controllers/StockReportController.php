<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StockReport;
use App\Services\StockPositionImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/** TFC stock position reports: upload the weekly Excel, view it, and update centre stock from it. */
class StockReportController extends Controller
{
    public function index(): View
    {
        return view('stock-reports.index', [
            'reports' => StockReport::with(['lines', 'bulkSales.product', 'creator'])->latest('report_date')->latest('id')->paginate(20),
        ]);
    }

    public function show(StockReport $stockReport): View
    {
        $stockReport->load(['lines.warehouse', 'bulkSales.product', 'productSummaries', 'creator']);

        return view('stock-reports.show', ['report' => $stockReport, 'totals' => $stockReport->totals()]);
    }

    public function store(Request $request, StockPositionImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'report_date' => ['nullable', 'date'],
            'apply' => ['nullable', 'boolean'],
        ]);

        try {
            $data = $importer->parse($request->file('file')->getRealPath());
            $report = $importer->save($data, $request->user(), $request->file('file')->getClientOriginalName(), $request->input('report_date') ?: null);
            $moves = $request->boolean('apply') ? $importer->applyToStock($report, $request->user()) : null;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('The report could not be imported: :reason', ['reason' => $e->getMessage()]));
        }

        AuditLog::record('stock_report.imported', "Imported {$report->title}".($moves !== null ? " and updated centre stock ({$moves} movements)" : ''), $report);

        return redirect()->route('stock-reports.show', $report)->with('success', __('Report imported: :n centres.', ['n' => count($data['lines'])]).($moves !== null ? ' '.__('Centre stock updated.') : ''));
    }

    public function apply(Request $request, StockReport $stockReport, StockPositionImporter $importer): RedirectResponse
    {
        try {
            $moves = $importer->applyToStock($stockReport, $request->user());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Centre stock could not be updated: :reason', ['reason' => $e->getMessage()]));
        }
        AuditLog::record('stock_report.applied', "Centre stock matched to {$stockReport->title} ({$moves} movements)", $stockReport);

        return back()->with('success', __('Centre stock now matches this report (:n changes).', ['n' => $moves]));
    }
}
