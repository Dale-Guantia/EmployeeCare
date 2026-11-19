<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use App\Models\Survey;
use App\Models\Issue;
use App\Services\IssueSelectionRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SurveyController extends Controller
{
    public function showForm(Request $request)
    {
        $departmentId = Department::idByCode(Department::HRDO);
        $colors = ['#ff6ae6ff', '#b56bff', '#3496ff', '#57caff', '#1dffb0', '#58fa5d', '#e3f85d', '#ffd152', '#ff9a42', '#ff7572'];

        // Preserve the kiosk's existing order/colors without tying buttons to database IDs.
        $divisionStyles = [
            Division::INFORMATION_TECHNOLOGY => 'btn-it',
            Division::ADMINISTRATIVE => 'btn-admin',
            Division::RECORDS => 'btn-records',
            Division::PAYROLL => 'btn-payroll',
            Division::CLAIMS => 'btn-claims',
            Division::RSP => 'btn-rsp',
            Division::LEARNING_AND_DEVELOPMENT => 'btn-ld',
            Division::PERFORMANCE_MANAGEMENT => 'btn-pm',
        ];

        $divisions = \App\Services\OfficeSelectionRules::divisions(Division::where('department_id', $departmentId))
            // Department Head is an organizational grouping, not a survey service division.
            ->where(function ($query) {
                $query->whereNull('division_code')->orWhere('division_code', '!=', Division::DEPARTMENT_HEAD);
            })
            ->orderBy('id')->get()
            ->sortBy(function ($division) use ($divisionStyles) {
                $position = array_search($division->division_code, array_keys($divisionStyles), true);
                return $position === false ? count($divisionStyles) : $position;
            });

        $services = IssueSelectionRules::options(Issue::where('department_id', $departmentId))->get();

        $staffs = $this->surveyStaff()->get();

        $cacheable = count($request->query()) === 0
            && ! $request->session()->has('success')
            && ! $request->session()->has('errors');

        return response()->view('survey_form', compact('divisions', 'staffs', 'services', 'colors', 'divisionStyles'))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Survey-Cacheable', $cacheable ? '1' : '0');
    }

    public function csrfToken()
    {
        return response()->json(['token' => csrf_token()])
            ->header('Cache-Control', 'no-store, private');
    }

    private function surveyStaff($retainedUserId = null)
    {
        $departmentId = Department::idByCode(Department::HRDO);
        return User::where('department_id', $departmentId)
            ->whereHas('division', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            })
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['div_head', 'hr_staff']);
            })->where(function ($query) use ($retainedUserId) {
                $query->where(function ($active) {
                    $active->where('is_active', true)
                        ->whereIn('division_id', \App\Services\OfficeSelectionRules::divisions(Division::query())->select('id'));
                });
                if ($retainedUserId !== null) {
                    $query->orWhere('id', $retainedUserId);
                }
            });
    }

    public function submitForm(Request $request)
    {
        $departmentId = Department::idByCode(Department::HRDO);
        // A saved survey's identical retry remains valid after its issue is archived.
        // SurveySubmission still verifies every submitted value before acknowledging it.
        $submissionId = $request->input('submission_id');
        $savedSurvey = is_string($submissionId) && Str::isUuid($submissionId)
            ? Survey::where('submission_id', strtolower($submissionId))->first()
            : null;

        $validator = Validator::make($request->all(), [
            'submission_id' => 'required|uuid',
            'user_id' => ['bail', 'required', 'integer', Rule::exists('users', 'id')->where('department_id', $departmentId)],
            'issue_id' => ['bail', 'required', 'integer', Rule::exists('issues', 'id')
                ->where('department_id', $departmentId)
                ->where(fn ($query) => IssueSelectionRules::options($query, $savedSurvey?->issue_id))],
            'timeliness_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'handling_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'quality_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'overall_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
        ], [
            'issue_id.exists' => 'The selected service is unavailable or archived. Please refresh the survey and choose an active service.',
        ]);

        $validator->after(function ($validator) use ($request, $savedSurvey, $departmentId) {
            if ($validator->errors()->has('user_id')) {
                return;
            }

            $staff = $this->surveyStaff($savedSurvey?->user_id)->whereKey($request->input('user_id'))->first();
            if (! $staff) {
                $validator->errors()->add('user_id', 'Please select a staff member listed in this survey.');
                return;
            }

            if (! $validator->errors()->has('issue_id') && ! Issue::whereKey($request->input('issue_id'))
                ->where('department_id', $departmentId)
                ->where('division_id', $staff->division_id)->exists()) {
                $validator->errors()->add('issue_id', 'Please select a service from the selected staff member\'s division.');
            }
        });

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'saved' => false,
                    'errors' => $validator->errors(),
                ], 422)->header('Cache-Control', 'no-store, private');
            }
            return redirect()->route('survey.form')->withErrors($validator)->withInput();
        }

        try {
            \App\Services\SurveySubmission::save(Survey::class, $validator->validated());
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['saved' => false, 'errors' => $e->errors()], 422)->header('Cache-Control', 'no-store, private');
            }
            return redirect()->route('survey.form')->withErrors($e->errors())->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'message' => 'Thank you for your feedback!',
            ])->header('Cache-Control', 'no-store, private');
        }

        return redirect()->to(url()->current() . '?thank_you=1')
            ->with('success', 'Thank you for your feedback!');
    }
}
