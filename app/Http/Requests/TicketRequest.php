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
        $ticket = null;
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $ticket = \App\Models\Ticket::visibleTo(backpack_user())->findOrFail($this->route('id') ?: $this->input('id'));
        }
        $rules = [
            'is_custom_issue' => 'required|boolean',
            'issue_id' => 'required_if:is_custom_issue,0|nullable|integer|exists:issues,id',
            'custom_issue' => 'required_if:is_custom_issue,1|nullable|string|max:255',
            'message' => 'required|string|max:65535',
            'status_id' => 'sometimes|nullable|integer|exists:statuses,id',
            'priority_id' => 'sometimes|nullable|integer|exists:priorities,id',
            'assigned_to' => \App\Services\TicketAssignmentRules::validation(backpack_user(), $ticket),
            'attachments' => ['bail', 'nullable', 'array', 'max:' . \App\Services\AttachmentStorage::MAX_FILES, function ($attribute, $value, $fail) {
                $retained = [];
                if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                    $id = $this->route('id') ?: $this->input('id');
                    $ticket = \App\Models\Ticket::visibleTo(backpack_user())->findOrFail($id);
                    $retained = $ticket->attachments ?: [];
                }

                foreach ($value as $item) {
                    if (is_string($item) && in_array($item, $retained, true)) {
                        continue;
                    }
                    // Validate the actual items without wildcard expansion, including unusual array keys.
                    $validator = \Illuminate\Support\Facades\Validator::make(['upload' => $item], [
                        'upload' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt|max:20480',
                    ]);
                    if ($validator->fails()) {
                        $fail('Attachments must be existing files on this ticket or new uploads of an allowed type (maximum 20 MB).');
                        return;
                    }
                }
            }],
            'department_id' => 'required_if:is_custom_issue,1|nullable|integer|exists:departments,id',
            'division_id' => ['required_if:is_custom_issue,1', 'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('divisions', 'id')->where('department_id', is_scalar($this->input('department_id')) ? $this->input('department_id') : null)],
        ];

        return $rules;
    }

    protected function prepareForValidation()
    {
        if (in_array($this->input('is_custom_issue'), [true, 1, '1'], true)) {
            $this->merge(['issue_id' => null]);
        } elseif (in_array($this->input('is_custom_issue'), [false, 0, '0'], true)) {
            $issue = is_scalar($this->input('issue_id')) ? \App\Models\Issue::find($this->input('issue_id')) : null;
            $this->merge(['custom_issue' => null, 'department_id' => $issue->department_id ?? null,
                'division_id' => $issue->division_id ?? null]);
        }
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
            'attachments.*.max'   => 'Each attachment must not exceed 20MB.',

            // Department & Division
            'department_id.required_if' => 'Please select a Department when submitting a custom issue.',
            'division_id.required_if'   => 'Please select a Division when submitting a custom issue.',
        ];
    }
}
