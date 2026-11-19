<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
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
        return $this->authorizeMasterData('department', \App\Models\Department::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'reason' => 'nullable|string|max:1000',
            'department_code' => \App\Services\PermanentCodeRules::validation(\App\Models\Department::class, $this->route('id'))['department_code'],
            'id' => ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$this->route('id')])],
            'department_name' => 'required|string|max:255|unique:departments,department_name,' . $this->route('id'),
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
            //
        ];
    }
}
