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
            'issue_id' => \App\Services\IssueSelectionRules::validation($ticket?->issue_id),
            'custom_issue' => 'required_if:is_custom_issue,1|nullable|string|max:255',
            'message' => 'required|string|max:65535',
            'status_id' => ['sometimes', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('statuses', 'id')->where(function ($query) use ($ticket) {
                $query->where(function ($active) {
                    $active->where('is_active', true)->whereIn('status_code', \App\Models\Status::CORE_CODES);
                });
                if ($ticket?->status_id) {
                    $query->orWhere('id', $ticket->status_id);
                }
            })],
            'priority_id' => \App\Services\PrioritySelectionRules::validation($ticket?->priority_id),
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
            'department_id' => ['required_if:is_custom_issue,1', 'nullable', 'integer',
                \App\Services\OfficeSelectionRules::departmentRule($ticket?->department_id)],
            'division_id' => ['nullable', 'integer',
                \App\Services\OfficeSelectionRules::divisionRule($this->input('department_id'), $ticket?->division_id)],
        ];

        if ($ticket) {
            $rules['id'] = ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$ticket->id])];
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        if (in_array($this->input('is_custom_issue'), [true, 1, '1'], true)) {
            $this->merge(['issue_id' => null]);
        } elseif (in_array($this->input('is_custom_issue'), [false, 0, '0'], true)) {
            $issue = is_scalar($this->input('issue_id')) ? \App\Models\Issue::find($this->input('issue_id')) : null;
            $ticket = ($this->isMethod('PUT') || $this->isMethod('PATCH'))
                ? \App\Models\Ticket::visibleTo(backpack_user())->findOrFail($this->route('id')) : null;
            // A reassigned ticket keeps its current office when its issue is unchanged.
            $location = $ticket && (string) $ticket->issue_id === (string) $this->input('issue_id') ? $ticket : $issue;
            $this->merge(['custom_issue' => null, 'department_id' => $location->department_id ?? null,
                'division_id' => $location->division_id ?? null]);
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
            'issue_id' => 'issue or problem',
            'is_custom_issue' => 'custom issue option',
            'custom_issue' => 'custom issue description',
            'department_id' => 'department',
            'division_id' => 'division',
            'assigned_to' => 'assigned staff member',
            'message' => 'ticket details',
            'attachments' => 'attachments',
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
            'issue_id.required_if' => 'Please select an issue or problem, or choose to describe a custom issue.',
            'issue_id.exists' => 'The selected issue is unavailable or archived. Please choose an active issue. You may retain the archived issue already assigned to this ticket.',
            'priority_id.exists' => 'Please choose an active priority or leave the priority empty. You may retain the archived priority already assigned to this ticket.',
            'custom_issue.required_if' => 'Please briefly describe your issue or problem.',
            'custom_issue.max' => 'Please keep the issue description within 255 characters.',
            'message.required' => 'Please provide details so our team can help with your concern.',
            'message.max' => 'Your ticket details are too long. Please shorten them and try again.',
            'attachments.max' => 'You can attach up to 20 files to a ticket.',
            'division_id.exists' => 'Please select a division that belongs to your chosen department.',
            // Attachments
            'attachments.*.mimes' => 'Allowed file types: jpg, jpeg, png, pdf, doc, docx, xls, xlsx.',
            'attachments.*.max'   => 'Each attachment must not exceed 20MB.',

            // Department & Division
            'department_id.required_if' => 'Please select a Department when submitting a custom issue.',
            'division_id.required_if'   => 'Please select a Division when submitting a custom issue.',
        ];
    }
}
