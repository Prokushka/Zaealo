<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCardAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'attributes' => ['required', 'array', 'max:300'],
            'attributes.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
