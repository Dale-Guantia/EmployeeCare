<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DivisionRequest extends FormRequest
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
        return $this->authorizeMasterData('division', \App\Models\Division::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $entry = ($this->isMethod('PUT') || $this->isMethod('PATCH'))
            ? \App\Models\Division::findOrFail($this->route('id')) : null;
        return [
            'reason' => 'nullable|string|max:1000',
            'division_code' => \App\Services\PermanentCodeRules::validation(\App\Models\Division::class, $this->route('id'))['division_code'],
            'division_name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('divisions', 'division_name')->where('department_id', is_scalar($this->input('department_id')) ? $this->input('department_id') : null)->ignore($entry?->id)],
            'department_id' => ['required', 'integer', \App\Services\OfficeSelectionRules::departmentRule($entry?->department_id)],
            'is_active' => 'sometimes|boolean',
            'id' => $entry ? ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$entry->id])] : ['prohibited'],
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
