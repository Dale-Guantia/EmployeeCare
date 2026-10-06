<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\Survey;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SurveyController extends Controller
{
    private const SURVEY_DEPARTMENT_ID = 1;

    public function showForm(Request $request)
    {
        $colors = ['#ff6ae6ff', '#b56bff', '#3496ff', '#57caff', '#1dffb0', '#58fa5d', '#e3f85d', '#ffd152', '#ff9a42', '#ff7572'];

        // Preserve the kiosk's existing order/colors without tying buttons to database IDs.
        $divisionStyles = [
            'Information Technology' => 'btn-it',
            'Administrative' => 'btn-admin',
            'Records' => 'btn-records',
            'Payroll' => 'btn-payroll',
            'Claims and Benefits' => 'btn-claims',
            'RSP' => 'btn-rsp',
            'Learning and Development' => 'btn-ld',
            'Performance Management' => 'btn-pm',
        ];

        $divisions = Division::where('department_id', self::SURVEY_DEPARTMENT_ID)
            // Department Head is an organizational grouping, not a survey service division.
            ->where('division_name', '!=', 'Department Head')
            ->orderBy('id')->get()
            ->sortBy(function ($division) use ($divisionStyles) {
                $position = array_search($division->division_name, array_keys($divisionStyles), true);
                return $position === false ? count($divisionStyles) : $position;
            });

        $services = Issue::where('department_id', self::SURVEY_DEPARTMENT_ID)->get();

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

    private function surveyStaff()
    {
        return User::where('department_id', self::SURVEY_DEPARTMENT_ID)
            ->whereHas('division', function ($q) {
                $q->where('department_id', self::SURVEY_DEPARTMENT_ID);
            })
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['div_head', 'hr_staff']);
            });
    }

    public function submitForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['bail', 'required', 'integer', Rule::exists('users', 'id')->where('department_id', self::SURVEY_DEPARTMENT_ID)],
            'issue_id' => ['bail', 'required', 'integer', Rule::exists('issues', 'id')->where('department_id', self::SURVEY_DEPARTMENT_ID)],
            'timeliness_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'handling_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'quality_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
            'overall_rating' => 'required|in:Very Dissatisfied,Dissatisfied,Satisfied,Very Satisfied',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->has('user_id')) {
                return;
            }

            $staff = $this->surveyStaff()->whereKey($request->input('user_id'))->first();
            if (! $staff) {
                $validator->errors()->add('user_id', 'Please select a staff member listed in this survey.');
                return;
            }

            if (! $validator->errors()->has('issue_id') && ! Issue::whereKey($request->input('issue_id'))
                ->where('department_id', self::SURVEY_DEPARTMENT_ID)
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

        /**
         * Duplicate protection:
         * If the exact same survey was submitted in the last 10 seconds,
         * do not create another record.
         */
        $duplicateSurvey = Survey::where('user_id', $request->input('user_id'))
            ->where('issue_id', $request->input('issue_id'))
            ->where('timeliness_rating', $request->input('timeliness_rating'))
            ->where('handling_rating', $request->input('handling_rating'))
            ->where('quality_rating', $request->input('quality_rating'))
            ->where('overall_rating', $request->input('overall_rating'))
            ->where('created_at', '>=', Carbon::now()->subSeconds(10))
            ->first();

        if (! $duplicateSurvey) {
            Survey::create([
                'user_id' => $request->input('user_id'),
                'issue_id' => $request->input('issue_id'),
                'created_at' => now(),
                'timeliness_rating' => $request->input('timeliness_rating'),
                'handling_rating' => $request->input('handling_rating'),
                'quality_rating' => $request->input('quality_rating'),
                'overall_rating' => $request->input('overall_rating'),
            ]);
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
