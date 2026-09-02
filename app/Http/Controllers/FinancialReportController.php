<?php

namespace App\Http\Controllers;

use App\Models\FinancialReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    /**
     * Display a listing of financial reports for public transparency.
     */
    public function index(Request $request): View
    {
        $query = FinancialReport::query()
            ->with(['creator:id,name,surnames', 'media'])
            ->orderByDesc('year');

        $selectedYear = null;

        if ($request->filled('year')) {
            $selectedYear = (int) $request->input('year');
            $query->where('year', $selectedYear);
        } elseif ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            if (preg_match('/\b(19\d\d|20\d\d)\b/', $search, $matches)) {
                $selectedYear = (int) $matches[1];
                $query->where('year', $selectedYear);
            } elseif (ctype_digit($search)) {
                $selectedYear = (int) $search;
                $query->where('year', $selectedYear);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $reports = $query->paginate(9)->withQueryString();

        $availableYears = FinancialReport::query()
            ->pluck('year')
            ->unique()
            ->sortDesc()
            ->values();

        return view('landing.financial-reports.index', [
            'reports' => $reports,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'syndicate' => config('syndicate'),
        ]);
    }
}
