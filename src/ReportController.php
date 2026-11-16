<?php

namespace ProductDiscounts\Reports;

use Illuminate\Routing\Controller;
use ProductDiscounts\Reports\Models\Basket;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportQuery $reports)
    {
        $filters = $request->validated();
        $query = $reports->build($filters);
        $summary = (clone $query)->selectRaw('COUNT(*) AS baskets, COALESCE(SUM(start_price),0) AS start_total, COALESCE(SUM(final_price),0) AS final_total, COALESCE(SUM(discount),0) AS discount_total')->first();
        $baskets = $query->withCount('actions')->orderByDesc('calculated_at')->orderByDesc('id')
            ->paginate($filters['length'] ?? 25)->withQueryString();
        return view('product-discounts-reports::index', compact('filters', 'summary', 'baskets'));
    }

    public function show(Basket $basket)
    {
        $basket->load('actions.doings.products');
        return view('product-discounts-reports::show', compact('basket'));
    }

    public function export(ReportRequest $request, string $mode, ReportQuery $reports, ReportExporter $exporter)
    {
        $path = $exporter->write($reports->build($request->validated()), $mode);
        return response()->download($path, 'baskets-'.$mode.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
