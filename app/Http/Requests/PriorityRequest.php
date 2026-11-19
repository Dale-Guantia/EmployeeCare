<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PriorityRequest extends FormRequest
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
        return $this->authorizeMasterData('priority', \App\Models\Priority::class);
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
            'priority_code' => \App\Services\PermanentCodeRules::validation(\App\Models\Priority::class, $this->route('id'))['priority_code'],
            'id' => ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$this->route('id')])],
            'priority_name' => 'required|string|max:255|unique:priorities,priority_name,' . $this->route('id'),
            'priority_color' => ['required', 'string', 'regex:/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i'],
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
