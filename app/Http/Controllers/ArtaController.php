<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\Survey;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ArtaController extends Controller
{
    public function showForm()
    {
        // $colors = ['#ff6ae6ff', '#b56bff', '#3496ff', '#57caff', '#1dffb0', '#58fa5d', '#e3f85d', '#ffd152', '#ff9a42', '#ff7572'];

        // $divisions = Division::where('department_id', 1)->get();

        // $services = Issue::where('department_id', 1)->get();

        // $staffs = User::where('department_id', 1)
        //     ->whereHas('roles', function ($q) {
        //         $q->whereIn('name', ['div_head', 'hr_staff']);
        //     })
        //     ->get();

        return view('arta_form');
    }

    public function submitForm(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'user_id' => 'required|exists:users,id',
        //     'issue_id' => 'required|exists:issues,id',
        //     'timeliness_rating' => 'required|string',
        //     'handling_rating' => 'required|string',
        //     'quality_rating' => 'required|string',
        //     'overall_rating' => 'required|string',
        // ]);

        // if ($validator->fails()) {
        //     return redirect()->back()->withErrors($validator)->withInput();
        // }

        // /**
        //  * Duplicate protection:
        //  * If the exact same survey was submitted in the last 10 seconds,
        //  * do not create another record.
        //  */
        // $duplicateSurvey = Survey::where('user_id', $request->input('user_id'))
        //     ->where('issue_id', $request->input('issue_id'))
        //     ->where('timeliness_rating', $request->input('timeliness_rating'))
        //     ->where('handling_rating', $request->input('handling_rating'))
        //     ->where('quality_rating', $request->input('quality_rating'))
        //     ->where('overall_rating', $request->input('overall_rating'))
        //     ->where('created_at', '>=', Carbon::now()->subSeconds(10))
        //     ->first();

        // if (! $duplicateSurvey) {
        //     Survey::create([
        //         'user_id' => $request->input('user_id'),
        //         'issue_id' => $request->input('issue_id'),
        //         'created_at' => now(),
        //         'timeliness_rating' => $request->input('timeliness_rating'),
        //         'handling_rating' => $request->input('handling_rating'),
        //         'quality_rating' => $request->input('quality_rating'),
        //         'overall_rating' => $request->input('overall_rating'),
        //     ]);
        // }

        // return redirect()->to(url()->current() . '?thank_you=1')
        //     ->with('success', 'Thank you for your feedback!');
    }
}
