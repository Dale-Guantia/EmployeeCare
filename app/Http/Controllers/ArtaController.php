<?php

namespace App\Http\Controllers;

use App\Models\ArtaSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArtaController extends Controller
{
    public function showForm()
    {
        return view('arta_form');
    }

    public function submitForm(Request $request)
    {
        // 1. Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'client_type'     => 'nullable|in:Citizen,Business,Government',
            'sex'             => 'nullable|in:Male,Female',
            'age'             => 'nullable|integer|min:1|max:100',
            'region'          => 'nullable|integer|between:1,17',
            'service_availed' => 'nullable|string|max:255',

            'cc1'             => 'nullable|integer|between:1,4',
            'cc2'             => 'nullable|integer|between:1,5',
            'cc3'             => 'nullable|integer|between:1,4',

            'sqd0'            => 'nullable|string',
            'sqd1'            => 'nullable|string',
            'sqd2'            => 'nullable|string',
            'sqd3'            => 'nullable|string',
            'sqd4'            => 'nullable|string',
            'sqd5'            => 'nullable|string',
            'sqd6'            => 'nullable|string',
            'sqd7'            => 'nullable|string',
            'sqd8'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Optional: If you want to handle errors on the frontend, you can return them.
            // But for a linear carousel form, redirecting back is standard.
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // 2. Extract validated data
        $data = $request->except('_token');

        // 3. Apply ARTA Business Rule:
        // If CC1 is 4 (Doesn't know what a CC is), CC2 and CC3 must automatically be recorded as N/A
        if ($request->input('cc1') == '4') {
            $data['cc2'] = 5; // '5' corresponds to 'Not Applicable' in CC2
            $data['cc3'] = 4; // '4' corresponds to 'Not Applicable' in CC3
        }

        // 4. Save directly to the database
        ArtaSurvey::create($data);

        // 5. Redirect back with the specific query parameter 'thank_you=1'
        // that your JavaScript listens for to jump to the QR code slide.
        return redirect()->to(url()->current() . '?thank_you=1')
            ->with('success', 'Thank you for your feedback! Your response has been successfully recorded.');
    }
}
