<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCompetitorCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'marketplace_search_query' => ['required', 'string', 'max:100', 'regex:/^\S+(?:\s+\S+){0,2}$/u'],
            'marketplace' => ['required', 'string', Rule::in(['ozon', 'wildberries'])],
        ];
    }
}
