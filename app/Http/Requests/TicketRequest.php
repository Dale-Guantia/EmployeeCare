<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'issue_id' => 'required_if:is_custom_issue,0|nullable',
            'custom_issue' => 'required_if:is_custom_issue,1|nullable',
            'message' => 'required',
            'attachments' => 'nullable|array',
            'department_id' => 'required_if:is_custom_issue,1|nullable',
            'division_id' => 'required_if:is_custom_issue,1|nullable'
        ];

        // Only validate if it's actually an uploaded file
        if ($this->hasFile('attachments')) {
            $rules['attachments.*'] = 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:2048';
        }

        return $rules;
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            // Attachments
            'attachments.*.mimes' => 'Allowed file types: jpg, jpeg, png, pdf, doc, docx, xls, xlsx.',
            'attachments.*.max'   => 'Each attachment must not exceed 2MB.',

            // Department & Division
            'department_id.required_if' => 'Please select a Department when submitting a custom issue.',
            'division_id.required_if'   => 'Please select a Division when submitting a custom issue.',
        ];
    }
}
