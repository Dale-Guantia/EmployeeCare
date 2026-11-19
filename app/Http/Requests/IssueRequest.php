<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\AuthorizesMasterDataLifecycle;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return $this->authorizeMasterData('issue', \App\Models\Issue::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $issue = null;
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $issue = \App\Models\Issue::findOrFail($this->route('id'));
        }

        return [
            'reason' => 'nullable|string|max:1000',
            'department_id' => ['required', 'integer', \App\Services\OfficeSelectionRules::departmentRule($issue?->department_id)],
            'division_id' => [\Illuminate\Validation\Rule::requiredIf(fn () => \App\Services\OfficeSelectionRules::issueDivisionRequired($this->input('department_id'), $issue)),
                'nullable', 'integer', \App\Services\OfficeSelectionRules::divisionRule($this->input('department_id'), $issue?->division_id)],
            'priority_id' => \App\Services\PrioritySelectionRules::validation($issue?->priority_id),
            'id' => $issue ? ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$issue->id])] : ['prohibited'],
            'issue_description' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('issues', 'issue_description')
                ->where('department_id', filter_var($this->input('department_id'), FILTER_VALIDATE_INT) !== false ? $this->input('department_id') : null)
                ->where('division_id', filter_var($this->input('division_id'), FILTER_VALIDATE_INT) !== false ? $this->input('division_id') : null)->ignore($issue?->id)],
            'icon' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
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
            'priority_id.exists' => 'Please choose an active priority or leave the priority empty. You may retain the archived priority already assigned to this issue.',
        ];
    }
}
