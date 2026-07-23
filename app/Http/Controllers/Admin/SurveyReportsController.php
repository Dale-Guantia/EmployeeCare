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
        $surveyQuery = Survey::with(['staff.division', 'service']);

        if ($startDate && $endDate) {
            $surveyQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $responses = (clone $surveyQuery)
            ->latest()
            ->get();

        $staffs = User::query()
            ->where(function ($query) {
                $query->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'hr_staff');
                })->orWhere(function ($query) {
                    $query->whereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'div_head');
                    })->where('department_id', 1);
                });
            })
            ->with('division')
            ->get();

        $surveyRatings = $staffs->map(function ($staff) use ($responses) {
            $staffSurveys = $responses->where('user_id', $staff->id);

            return (object) [
                'id' => $staff->id,
                'name' => $staff->name,
                'division_name' => optional($staff->division)->division_name ?? 'N/A',
                'total_surveys' => $staffSurveys->count(),

                'very_dissatisfied_count' =>
                    $staffSurveys->where('timeliness_rating', 'Very Dissatisfied')->count() +
                    $staffSurveys->where('handling_rating', 'Very Dissatisfied')->count() +
                    $staffSurveys->where('quality_rating', 'Very Dissatisfied')->count() +
                    $staffSurveys->where('overall_rating', 'Very Dissatisfied')->count(),

                'dissatisfied_count' =>
                    $staffSurveys->where('timeliness_rating', 'Dissatisfied')->count() +
                    $staffSurveys->where('handling_rating', 'Dissatisfied')->count() +
                    $staffSurveys->where('quality_rating', 'Dissatisfied')->count() +
                    $staffSurveys->where('overall_rating', 'Dissatisfied')->count(),

                'satisfied_count' =>
                    $staffSurveys->where('timeliness_rating', 'Satisfied')->count() +
                    $staffSurveys->where('handling_rating', 'Satisfied')->count() +
                    $staffSurveys->where('quality_rating', 'Satisfied')->count() +
                    $staffSurveys->where('overall_rating', 'Satisfied')->count(),

                'very_satisfied_count' =>
                    $staffSurveys->where('timeliness_rating', 'Very Satisfied')->count() +
                    $staffSurveys->where('handling_rating', 'Very Satisfied')->count() +
                    $staffSurveys->where('quality_rating', 'Very Satisfied')->count() +
                    $staffSurveys->where('overall_rating', 'Very Satisfied')->count(),

                'details' => [
                    'timeliness' => [
                        'very_satisfied'    => $staffSurveys->where('timeliness_rating', 'Very Satisfied')->count(),
                        'satisfied'         => $staffSurveys->where('timeliness_rating', 'Satisfied')->count(),
                        'dissatisfied'      => $staffSurveys->where('timeliness_rating', 'Dissatisfied')->count(),
                        'very_dissatisfied' => $staffSurveys->where('timeliness_rating', 'Very Dissatisfied')->count(),
                    ],
                    'handling' => [
                        'very_satisfied'    => $staffSurveys->where('handling_rating', 'Very Satisfied')->count(),
                        'satisfied'         => $staffSurveys->where('handling_rating', 'Satisfied')->count(),
                        'dissatisfied'      => $staffSurveys->where('handling_rating', 'Dissatisfied')->count(),
                        'very_dissatisfied' => $staffSurveys->where('handling_rating', 'Very Dissatisfied')->count(),
                    ],
                    'quality' => [
                        'very_satisfied'    => $staffSurveys->where('quality_rating', 'Very Satisfied')->count(),
                        'satisfied'         => $staffSurveys->where('quality_rating', 'Satisfied')->count(),
                        'dissatisfied'      => $staffSurveys->where('quality_rating', 'Dissatisfied')->count(),
                        'very_dissatisfied' => $staffSurveys->where('quality_rating', 'Very Dissatisfied')->count(),
                    ],
                    'overall' => [
                        'very_satisfied'    => $staffSurveys->where('overall_rating', 'Very Satisfied')->count(),
                        'satisfied'         => $staffSurveys->where('overall_rating', 'Satisfied')->count(),
                        'dissatisfied'      => $staffSurveys->where('overall_rating', 'Dissatisfied')->count(),
                        'very_dissatisfied' => $staffSurveys->where('overall_rating', 'Very Dissatisfied')->count(),
                    ],
                ],
            ];
        });

        if (! $includeZeroActivity) {
            $surveyRatings = $surveyRatings
                ->filter(function ($staff) {
                    return $staff->total_surveys > 0;
                })
                ->values();
        }

        $summaryCards = [
            'total_surveys' => $responses->count(),
            'very_satisfied' =>
                $responses->where('timeliness_rating', 'Very Satisfied')->count() +
                $responses->where('handling_rating', 'Very Satisfied')->count() +
                $responses->where('quality_rating', 'Very Satisfied')->count() +
                $responses->where('overall_rating', 'Very Satisfied')->count(),

            'satisfied' =>
                $responses->where('timeliness_rating', 'Satisfied')->count() +
                $responses->where('handling_rating', 'Satisfied')->count() +
                $responses->where('quality_rating', 'Satisfied')->count() +
                $responses->where('overall_rating', 'Satisfied')->count(),

            'dissatisfied' =>
                $responses->where('timeliness_rating', 'Dissatisfied')->count() +
                $responses->where('handling_rating', 'Dissatisfied')->count() +
                $responses->where('quality_rating', 'Dissatisfied')->count() +
                $responses->where('overall_rating', 'Dissatisfied')->count(),

            'very_dissatisfied' =>
                $responses->where('timeliness_rating', 'Very Dissatisfied')->count() +
                $responses->where('handling_rating', 'Very Dissatisfied')->count() +
                $responses->where('quality_rating', 'Very Dissatisfied')->count() +
                $responses->where('overall_rating', 'Very Dissatisfied')->count(),

            'staff_rated' => $responses->pluck('user_id')->unique()->count(),
        ];

        return [
            'responses' => $responses,
            'surveyRatings' => $surveyRatings,
            'summaryCards' => $summaryCards,
        ];
    }
}
