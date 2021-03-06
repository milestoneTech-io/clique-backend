<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class JSONAPIRelationshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'data' => 'present|array|nullable',
            //for single resources
            'data.id' => [Rule::requiredIf($this->has('data.type')), 'string'],
            'data.type' => [Rule::requiredIf($this->has('data.id')), Rule::in(array_keys(config('jsonapi.resources')))],
            //for collections
            'data.*.id' => 'required|string',
            'data.*.type' => ['required', Rule::in(array_keys(config('jsonapi.resources')))],
        ];
    }
}
