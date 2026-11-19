<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatusRequest extends FormRequest
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
        return $this->authorizeMasterData('status', \App\Models\Status::class);
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
            'status_code' => \App\Services\PermanentCodeRules::validation(\App\Models\Status::class, $this->route('id'))['status_code'],
            'id' => ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$this->route('id')])],
            'status_name' => 'required|string|max:255|unique:statuses,status_name,' . $this->route('id'),
            'is_active' => 'sometimes|boolean',
            'status_color' => ['required', 'string', 'regex:/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i'],
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
