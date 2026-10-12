<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueRequest extends FormRequest
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
        return [
            'department_id' => 'required|integer|exists:departments,id',
            'division_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('divisions', 'id')->where('department_id', is_scalar($this->input('department_id')) ? $this->input('department_id') : null)],
            'priority_id' => 'required|integer|exists:priorities,id',
            'issue_description' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
        ];
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
            //
        ];
    }
}
