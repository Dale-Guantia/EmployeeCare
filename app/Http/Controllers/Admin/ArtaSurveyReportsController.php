<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\ArtaSurvey;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ArtaSurveyReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // Using the same permission as your CSS reports
            if (!backpack_user() || !backpack_user()->can('survey-reports.view')) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $data = $this->getReportData();

        return view('admin.arta_survey_reports', array_merge([
            'title' => 'ARTA Survey Reports',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'ARTA Survey Reports' => false,
            ],
        ], $data));
    }

    public function downloadPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $data = $this->getReportData($startDate, $endDate);

        // Philippine Long / Legal Paper Size: 8.5 x 13 inches
        // Calculated in points (1 inch = 72 points) -> 8.5 * 72 = 612, 13 * 72 = 936
        $customPaperSize = array(0, 0, 612, 936);

        $pdf = Pdf::loadView('admin.pdf.arta_survey_pdf', array_merge($data, [
            'reportStartDate' => $startDate->format('F j, Y'),
            'reportEndDate' => $endDate->format('F j, Y'),
        ]))->setPaper($customPaperSize, 'landscape');

        return $pdf->stream(
            'arta-survey-report-' . $startDate->format('Ymd') . '-to-' . $endDate->format('Ymd') . '.pdf'
        );
    }

    protected function getReportData($startDate = null, $endDate = null)
    {
        $query = ArtaSurvey::query();

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Calling ->get() automatically fetches ALL columns from your database (CC1-3, SQD0-8, age, sex, etc.)
        $responses = $query->latest()->get();

        return [
            'responses' => $responses,
        ];
    }
}
