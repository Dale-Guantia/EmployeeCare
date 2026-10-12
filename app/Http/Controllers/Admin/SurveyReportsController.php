<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Survey;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class SurveyReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!backpack_user() || !backpack_user()->can('survey-reports.view')) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index()
    {
        $data = $this->getReportData();

        return view('admin.survey_reports', array_merge([
            'title' => 'Survey Reports',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Survey Reports' => false,
            ],
            'page' => 'resources/views/admin/survey_reports.blade.php',
            'controller' => 'app/Http/Controllers/Admin/SurveyReportsController.php',
        ], $data));
    }

    public function responses(Request $request)
    {
        return response()->json(\App\Services\SurveyResponseTable::data($request));
    }

    public function downloadPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'include_zero_activity' => 'nullable|boolean',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $includeZeroActivity = $request->boolean('include_zero_activity');

        $data = $this->getReportData($startDate, $endDate, $includeZeroActivity);

        try {
            $pdf = Pdf::loadView('admin.pdf.survey_pdf', array_merge($data, [
                'reportStartDate' => $startDate->format('F j, Y'),
                'reportEndDate' => $endDate->format('F j, Y'),
            ]))->setPaper('a4', 'portrait');

            return $pdf->stream(
                'customer-survey-report-' . $startDate->format('Ymd') . '-to-' . $endDate->format('Ymd') . '.pdf'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[SurveyReportsController] PDF generation failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not generate the PDF report. Try a narrower date range, or contact support if this continues.');
        }
    }

    protected function getReportData($startDate = null, $endDate = null, $includeZeroActivity = false)
    {
        $data = \App\Services\SurveyReportQueries::summary($startDate, $endDate, (bool) $includeZeroActivity);
        // The PDF contains summaries only; response details use the paginated endpoint.
        $data['responses'] = Survey::with(['staff.division', 'service'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })->latest()->orderByDesc('id')->limit(10)->get();
        return $data;
    }
}