<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\Survey;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SurveyController extends Controller
{
    public function showForm()
    {
        // 1. Check if user is logged in to avoid the "on null" error
        $user = backpack_user();

        $colors = ['#ff6ae6ff', '#b56bff', '#3496ff', '#57caff', '#1dffb0', '#58fa5d', '#e3f85d', '#ffd152', '#ff9a42', '#ff7572'];
        $divisions = Division::where('department_id', 1)->get();
        $services = Issue::where('department_id', 1)->get();
        $staffs = User::where('department_id', 1)
            ->whereHas('roles', function($q){
                $q->where('name', 'hr_staff');
            })->get();

        return view('survey_form', compact('divisions', 'staffs', 'services', 'colors'));
    }

    public function submitForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'issue_id' => 'required|exists:issues,id',
            'timeliness_rating' => 'required|string',
            'handling_rating' => 'required|string',
            'quality_rating' => 'required|string',
            'overall_rating' => 'required|string',
            // 'suggestions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        Survey::create([
            'user_id' => $request->input('user_id'),
            'issue_id' => $request->input('issue_id'),
            'timeliness_rating' => $request->input('timeliness_rating'),
            'handling_rating' => $request->input('handling_rating'),
            'quality_rating' => $request->input('quality_rating'),
            'overall_rating' => $request->input('overall_rating'),
            // 'suggestions' => $request->input('suggestions'),
        ]);

        return redirect()->to(url()->current() . '?thank_you=1')->with('success', 'Thank you for your feedback!');
    }
}
