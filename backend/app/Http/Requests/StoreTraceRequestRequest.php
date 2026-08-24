<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTraceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'max:180'],
            'description' => ['bail', 'required', 'string', 'max:5000'],
            'requested_amount' => [
                'bail',
                'required',
                'string',
                'regex:/\A\d{1,15}(?:\.\d{1,4})?\z/',
                'not_regex:/\A0+(?:\.0{1,4})?\z/',
            ],
            'currency_code' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{3}\z/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        foreach (['title', 'description', 'requested_amount', 'currency_code'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $input[$field] = trim($value);
            }
        }

        if (isset($input['currency_code'])) {
            $input['currency_code'] = strtoupper($input['currency_code']);
        }

        $this->merge($input);
    }
}
